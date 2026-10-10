# 技术设计文档：新增 `Yansongda\Supports\Json` JSON 解析类

> **时间**：2026-10-10
> **作者**：DeepSeek V4.1 Flash + yansongda
> **状态**：经过人工审核确认

## 1. 背景与问题

**现状**（均已读源码验证）

包内 JSON 处理散落在三处，直调原生函数、策略各异：

- `src/Traits/Serializable.php:42` `toJson(int $option = JSON_UNESCAPED_UNICODE): string` 直接 `return json_encode(...)`；
- `src/Arr.php:530` `wrapJson(string $json): ?array` 用 `json_decode($json, true)` 后非数组即返回 `null`；
- `src/Arr.php:562` `wrapXml()` 内部用 `json_decode(json_encode($xmlObject))` 做 XML→数组。

无统一 JSON 工具类，下游（如 yansongda/pay）需各自处理错误。

**困境**

1. **失败静默/类型错误**：`json_encode` 失败返回 `false`，而 `Serializable::toJson(): string` 返回类型是 `string` → `strict_types=1` 下变成难以定位的 `TypeError`，而非可读的 JSON 错误。
2. **失败与合法值不可区分**：`json_decode('null')` 合法返回 `null`，只看返回值无法判断失败。
3. **错误信息丢失**：调用方拿不到 `JSON_ERROR_*` 错误码，无法按错误类型分支处理（如 `JSON_ERROR_UTF8` 可转码重试）。
4. **无静态保证**：返回值可能为 `false`，漏判不会被 PHPStan 拦住。

**目标**

- **失败必抛**：任何 `json_last_error() !== JSON_ERROR_NONE` 都抛异常，绝不返回 `false`/半成品。
- **默认中文友好**：默认 flags 为 `JSON_UNESCAPED_UNICODE`，可被显式覆盖。
- **零行为变更**：纯新增文件，不改动现有 `Arr`/`Collection`/`Traits` 对外行为。
- **PHP >= 8.2 兼容**；PHPStan level 6 通过；主路径 + 错误路径均有用例。

## 2. 整体方案

**核心思路：新增无状态静态工具类 `Json`，薄封装原生函数，用「错误码非 0 即抛 `\JsonException`」把返回值收敛为 `string` / `mixed`，让失败只能以异常形式被感知。**

```
调用方 ──► Json::encode($value, $flags, $depth) ──► json_encode ──┬─ 错误码=NONE ──► string
        │                                                        └─ 错误码≠NONE ──► throw \JsonException(code=JSON_ERROR_*, msg=json_last_error_msg())
        └──► Json::decode($json, $assoc, $depth, $flags) ──► json_decode ── 同上（不看返回值，只看错误码）
```

文件结构（`+` 新增，其余不动）：

```
src/
+ ├── Json.php                 # 新增：encode / decode
  ├── Arr.php / Collection.php / Config.php / Pipeline.php / Str.php / Functions.php
  └── Traits/{Accessable,Arrayable,Serializable}.php
tests/
+ └── JsonTest.php             # 新增
docs/                         # 本设计文档 + implementation plan + evidence
CHANGELOG.md / README.md       # 本次不改（见 §4）
```

## 3. 详细设计

### 3.1 对外 API 契约

```php
// 参数名：$flags/$depth 对齐原生 PHP；decode 的 $assoc 对齐 Hyperf\Codec\Json
// （原生为 $associative——勿用命名实参混用；PHP 8 命名实参使参数名属公开契约）
Json::encode(mixed $value, int $flags = JSON_UNESCAPED_UNICODE, int $depth = 512): string
Json::decode(string $json, bool $assoc = true, int $depth = 512, int $flags = 0): mixed
```

| 参数 | 默认 | 语义 | 与原生差异 |
|---|---|---|---|
| `encode($value)` | 必填 | 任意值；`JsonSerializable`（含 `Collection`）走原生语义 | 无 |
| `encode($flags)` | `JSON_UNESCAPED_UNICODE` | 传 `0` 即完全原生转义行为 | **默认值不同**（原生 `0`） |
| `encode($depth)` / `decode($depth)` | `512` | 递归深度 | 无；**encode 不校验 depth**（8.5.10 实测）：标量可正常返回，数组越限返回 `false`（`JSON_ERROR_DEPTH`）→ 抛 `JsonException`；**decode** 传 `0`/负数由 PHP 前置抛 `ValueError` |
| `decode($assoc)` | `true` | 返回关联数组 | 默认值与参数名均与原生不同（原生 `?bool $associative = null` → stdClass），对齐 `Hyperf\Codec\Json::decode` |
| `decode($flags)` | `0` | 与原生一致 | 无需传 `JSON_THROW_ON_ERROR`（本类已内建抛错） |

行为样例（错误码/消息以 Task 0 实测快照为准）：

| 调用 | 结果 |
|---|---|
| `Json::encode(['name' => '中文'])` | `{"name":"中文"}` |
| `Json::encode(['u' => 'https://a/b'])` | `{"u":"https:\/\/a\/b"}`（默认只含 UNICODE，斜杠仍转义） |
| `Json::encode(['u' => 'https://a/b'], JSON_UNESCAPED_UNICODE \| JSON_UNESCAPED_SLASHES)` | `{"u":"https://a/b"}` |
| `Json::encode(NAN)` | 抛 `JsonException`，code `JSON_ERROR_INF_OR_NAN`(7) |
| `Json::encode($recursiveArray)` | 抛 `JsonException`，code `JSON_ERROR_RECURSION`(6) |
| `Json::encode([[1]], 0, 1)`（depth 越限） | 抛 `JsonException`，code `JSON_ERROR_DEPTH`(1)；注意 `encode` + `depth=0` 对标量（如 `'x'`）会正常返回（PHP 原生行为） |
| `Json::decode('{"a":1}')` | `['a' => 1]` |
| `Json::decode('{"a":1}', false)` | `stdClass`，属性 `a = 1` |
| `Json::decode('null')` | `null`，**不抛**（合法 JSON） |
| `Json::decode('{')` | 抛 `JsonException`，code `JSON_ERROR_SYNTAX`(4) |
| `Json::decode($超深JSON)` | 抛 `JsonException`，code `JSON_ERROR_DEPTH`(1) |

### 3.2 错误处理语义（核心决策）

**决策：以 `json_last_error()` 错误码为准，而非返回值。**

```
$result = json_encode($value, $flags, $depth)
if (JSON_ERROR_NONE === json_last_error() && false !== $result) return $result
throw new JsonException(json_last_error_msg(), json_last_error())
```

`decode` 同理，但**只判错误码**（因 `'null'` 合法返回 `null`）。

| 项 | 决定 | 理由 |
|---|---|---|
| 异常类型 | 内置 `\JsonException` | 与 `JSON_THROW_ON_ERROR` 抛出的类型一致，下游 `catch (\JsonException)` 一处即可；不新增包内异常类（后续若引入 `Supports\Exceptions\*`，可让新类 extends 它，不破坏兼容） |
| 异常 code | `json_last_error()`（`JSON_ERROR_*`） | 下游可按错误类型分支；`JsonException::__construct(string $message, int $code, ?Throwable $previous)`（已验证） |
| 异常 message | `json_last_error_msg()` | 引擎标准英文消息，不做二次包装 |
| 严格性 | 即使传入 `JSON_PARTIAL_OUTPUT_ON_ERROR`，错误码非 0 仍抛 | 「绝不返回半成品」，**比原生 THROW 更严格**：手册明文 `JSON_PARTIAL_OUTPUT_ON_ERROR` 优先于 `JSON_THROW_ON_ERROR`，实测 `json_encode([NAN], PARTIAL\|THROW)` 返回 `"[0]"` 且不抛（8.2/8.4/8.5 一致）；本类按错误码判定故必抛，属有意设计；PARTIAL 压制**仅作用于 encode 侧**（decode 侧 `PARTIAL\|THROW` 实测仍抛 `JsonException(4)`） |
| 参数非法 | `decode`：`depth <= 0` 由 PHP 前置抛 `ValueError`（透传）；`encode`：不校验 depth，行为随结构而定（见 §3.1） | 属调用方编程错误，不做兜底掩盖；8.2/8.4/8.5 实测一致 |
| **备选实现**（仅在 `json_last_error()` 被弃用时启用；当前 8.5.10 实测未弃用） | `json_encode($v, ($flags & ~JSON_PARTIAL_OUTPUT_ON_ERROR) \| JSON_THROW_ON_ERROR, $depth)` | **与主实现不等价**：不剥离 `PARTIAL` 位时 PARTIAL 场景会静默返回半成品（与主实现相反、用例 9 失败）；启用时必须①**仅 encode 侧**剥离 PARTIAL 位（decode 侧无需：实测 `json_decode('{', true, 512, PARTIAL\|THROW)` 仍抛 `JsonException(4)`，PARTIAL 压制只作用于 encode）②同步 docblock 与用例 9③task-0 保留两个 `PARTIAL\|THROW` 探针 |

### 3.3 默认 flags 决策：不加 `JSON_UNESCAPED_SLASHES`

**结论：默认仅 `JSON_UNESCAPED_UNICODE`；需要时由调用方显式传。**

理由（完整调研见附录 B）：

1. **字节级签名敏感性**：`/` 转义与否两种输出都合法（RFC 8259 仅强制转义 `"`、`\`、控制字符），差异纯属字节层面，因此它是签名/摘要/缓存 key 敏感的开关，必须是显式的单一事实来源，不能藏进默认值。
2. **下游 pay 现状**：需要它的 3 处（`BestpayTrait::stringifySignValue()`、`Bestpay/V1/AddPayloadSignPlugin`、`Alipay/V2/VerifySignaturePlugin`）**恰恰都是协议/官方 SDK 字节对齐场景且已显式传**；其余 6+ 处用 `JSON_UNESCAPED_UNICODE` 或原生 `0`。pay 的请求体与签名串共用预生成的 `_body` 字符串（`get_radar_body()`），不依赖全局默认值。
3. **PHP 生态一致**：Guzzle、Laravel、Hyperf 的默认值都不含 `JSON_UNESCAPED_SLASHES`；加进默认值会让"省略 flags"的调用偏离生态基线，且与官方 PHP SDK/文档示例（裸 `json_encode`）字节不一致。
4. **安全默认**：PHP 默认不转义 `<` `>`，`/` 的转义是 JSON 内联进 HTML `<script>` 时对 `</script>` 逃逸的唯一防线（nette/utils issue #235 将其标为 "dangerous default"）。
5. **收益可忽略**：每个 `/` 省 1 字节，URL 密集 payload 通常 < 0.5%；需要"干净 JSON"时显式传 `JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES` 成本为 0。

**何时该显式加（写入类 docblock）**

| 场景 | 是否加 | 说明 |
|---|---|---|
| 与 fastjson / JS / Python 输出字节对齐（签名取串、缓存 key、digest、跨语言快照） | 加 | pay 已有 3 处此类需求 |
| `application/json` 响应或日志，追求 URL 可读/体积 | 可选加 | 收益小，无直接安全风险 |
| 结果内联进 HTML `<script>` | 不加 | 需另配 `JSON_HEX_TAG \| JSON_HEX_AMP \| JSON_HEX_APOS \| JSON_HEX_QUOT` |
| 需与 PHP 官方 SDK/文档示例字节一致 | 不加（或显式传 `0`） | 官方示例连 Unicode 都转义 |
| 拿不准 | 不加 | 多余转义一眼可见；少转义引发的是静默的验签/缓存类疑难 bug |

### 3.4 明确不做（非目标）

- 不改 `Traits\Serializable::toJson()`（其失败即 `TypeError` 的问题留作后续独立 PR，改动异常类型属 BC 变更）。
- 不改 `Arr::wrapJson()` / `Arr::wrapXml()`。
- 不加 `validate()` / `isValid()` / `decodeOrNull()`（无当前需求）。
- 不做 `Arrayable` trait 的 `toArray()` 鸭子类型识别：`Collection` 已实现 `JsonSerializable`，原生语义已覆盖；鸭子类型会让结果依赖对象是否恰好有某方法，不可预测。
- 不新增包内异常类 / `src/Exceptions/`。

### 3.5 测试设计（`tests/JsonTest.php`）

风格与仓库现状一致：`self::assertSame`、`$this->expectException`、`$this->expectExceptionCode`，不引入 coverage 注解（现有 tests 均无）。错误路径断言**类 + code**，不断言英文消息文本（避免跨 8.2–8.5 漂移导致 CI 抖动）。

| 分组 | 用例 |
|---|---|
| encode | 默认：Unicode 不转义 + 斜杠转义；显式 `0` 恢复原生转义；显式 `UNICODE\|SLASHES` 生效 |
| encode | `Collection`（`JsonSerializable`）输出与 `toArray()` 一致；空数组 `[]` |
| encode | 失败：`NAN`（`JSON_ERROR_INF_OR_NAN`）、递归数组（`JSON_ERROR_RECURSION`）、非法 UTF-8（`JSON_ERROR_UTF8`）、depth 越限（`[[1]]` + `$depth=1` → `JSON_ERROR_DEPTH`） |
| encode | `JSON_PARTIAL_OUTPUT_ON_ERROR` 下仍抛（严格性） |
| decode | 默认关联数组；`$assoc=false` 返回 `stdClass` |
| decode | `'null'`/`'0'`/`'"x"'`/`'true'` 不抛且类型正确 |
| decode | flags 透传（`JSON_BIGINT_AS_STRING`：大整数返回 string） |
| decode | 失败：`'{'`（`JSON_ERROR_SYNTAX`）、超 depth（`JSON_ERROR_DEPTH`）、非法 UTF-8（`JSON_ERROR_UTF8`） |

### 3.6 兼容性与性能

- 纯新增，无 BC 风险；`composer.json` 无改动（`ext-json` 自 PHP 8.0 起恒为内置，无需声明）。
- 相比直调原生多一次 `json_last_error()` 调用（纳秒级）；无状态、无需缓存。
- 下游迁移（P2）**不得统一替换为默认值**：`json_encode($x, JSON_UNESCAPED_UNICODE)` → `Json::encode($x)`（等价）；`json_encode($x, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)` → 保留显式 flags；**裸 `json_encode($x)`（flags `0`）→ 必须显式传 `0`**，否则中文转义形态变化属字节级行为变更。

## 4. 推进策略

| 阶段 | 内容 | 验证点 |
|---|---|---|
| P1（本 PR） | `src/Json.php` + `tests/JsonTest.php` + evidence | CI 三件套全绿（测试矩阵 8.2/8.3/8.4/8.5） |
| P2（后续，需另行确认） | `Serializable::toJson()` 复用 `Json::encode`；`Arr::wrapJson()` 错误感知变体；下游 pay 逐点迁移（见 §3.6 约束） | 属行为变更，单独方案 + 单独 PR |
| 发布 | feat → 下个 minor `v4.2.0`；CHANGELOG 由 releasing-php-package 流程在 release commit 中统一补（与仓库历史一致） | — |

**回滚**：纯新增文件，`git revert <commit>` 或删除 `src/Json.php`、`tests/JsonTest.php` 即可，无配置/数据影响。

## 5. 风险与对策

| 风险 | 严重度 | 对策 |
|---|---|---|
| 未来版本弃用 `json_last_error()` | 低 | 当前已排除：8.5.10 实测（`error_reporting=E_ALL`）无 E_DEPRECATED，RFC `deprecations_php_8_5` 页无 json 相关条目；Task 0 保留弃用扫描作回归探针，若未来弃用按 §3.2 备选切换（**encode 侧须剥离 PARTIAL 位**；decode 侧无需——实测 PARTIAL 不压制 decode 的 THROW） |
| 默认只含 `JSON_UNESCAPED_UNICODE`，调用方误以为斜杠也不转义 | 低 | 测试固化「默认转义斜杠」+ docblock 常用 flags 组合表（§3.3） |
| 传 `JSON_PARTIAL_OUTPUT_ON_ERROR` 仍抛异常，与原生「部分输出」预期不符 | 低 | 类 docblock 声明「任何 JSON 错误均抛异常」（比原生 THROW 更严格，见 §3.2）；确有需求后续再加透传变体 |
| `json_last_error()` 依赖进程全局状态，`jsonSerialize()` 内再调 JSON 函数理论上可污染错误码 | 低 | 保留为已知边界（本次用例未覆盖）；若未来出现实测案例，可按 §3.2 备选实现消除（注意 PARTIAL 差异） |
| depth 非法时异常类型不统一（`decode` → `ValueError`；`encode` → 视结构可能 `JsonException(JSON_ERROR_DEPTH)` 或正常返回） | 低 | docblock 分列声明 encode/decode 语义（§3.1、§3.2）+ 新增 encode depth 用例固化；不做兜底掩盖 |
| 非法 UTF-8 解码行为跨版本差异 | 低 | 已实测 8.2/8.4/8.5 一致返回 `JSON_ERROR_UTF8`(5)，用例 17 保留；Task 0 快照作为复核 |
| 下游误用默认值产出与协议不一致的字节串 | 低 | §3.6 迁移约束 + §3.3 决策表；pay 侧签名路径均已显式传 flags |

## 6. 验证与可观测性

库无运行时上报通道，观测手段即 CI + 容器实测：

```bash
# 本地无 PHP（php -v 不可用）；本机只有 Docker → 优先 docker run（无 DNS fix，无需 COMPOSER_ALLOW_SUPERUSER）
docker run --rm -v "$(pwd)":/app -w /app \
  registry.cn-shenzhen.aliyuncs.com/yansongda/php:cli-8.5-alpine \
  composer test
# 同样方式执行 composer analyse（PHPStan lvl 6）、composer cs-fix（dry-run，仅检查）
# 若在 Apple Container 环境：改用 container-dev skill 的 container run 形态 + nameserver 8.8.8.8 fix
```

**关键指标**：`JsonTest` 用例全绿；PHPStan 零新增错误；php-cs-fixer 零 diff；CI 矩阵 8.2/8.3/8.4/8.5 全绿。

**实测覆盖说明（2026-10-10 评审期）**：本机仅 8.5.10 全量实测；关键 JSON 语义（PARTIAL/THROW 优先级、depth 行为、错误码、非法 UTF-8）另在 8.4.25 与 8.2.33 抽查一致；**PHP 8.3 未实测**（无镜像），由 CI 矩阵覆盖。

## 附录 A：参考实现对照

| 来源 | 契约 | 状态 |
|---|---|---|
| `Hyperf\Codec\Json`（hyperf/codec master） | `encode(mixed $data, int $flags = JSON_UNESCAPED_UNICODE, int $depth = 512): string`；`decode(string $json, bool $assoc = true, int $depth = 512, int $flags = 0): mixed`；内部 `$flags \| JSON_THROW_ON_ERROR`，捕获后包装为 `Hyperf\Codec\Exception\InvalidArgumentException` | **已验证（读过源码）**；本方案签名一致，仅异常类型改为直接抛 `\JsonException` |
| Guzzle `Utils::jsonEncode($value, int $options = 0, int $depth = 512): string` | 默认 `0`；错误检测为 `JSON_ERROR_NONE !== json_last_error()` → 抛 `InvalidArgumentException` | **已验证（读过源码）**；与 §3.2 实现形态一致 |
| Laravel `Illuminate\Database\Eloquent\Casts\Json::encode(mixed $value, int $flags = 0)` | 默认 `0` | **已验证（读过源码）** |
| 原生 PHP | `json_encode(mixed $value, int $flags = 0, int $depth = 512)`；`json_decode(string $json, ?bool $associative = null, int $depth = 512, int $flags = 0)`；`JSON_UNESCAPED_SLASHES = 64`、`JSON_UNESCAPED_UNICODE = 256` | 已验证（官方文档） |
| 本包现状 | `Serializable::toJson(int $option = JSON_UNESCAPED_UNICODE): string` 直返 `json_encode`；`Arr::wrapJson(): ?array` 非数组返 `null` | **已验证（读过源码）** |

## 附录 B：`JSON_UNESCAPED_SLASHES` 调研结论（2026-10-10）

**规范**：RFC 8259 §7 仅要求转义 `"`、`\`、U+0000–U+001F；`/`（U+002F）在 ABNF 中既可出现在 `escape` 列表，也落在 `unescaped` 范围 → 两种输出均合法，属实现自由（**已验证，读过 RFC 原文**）。

**PHP 生态默认值**（均**已验证读过源码**）：Guzzle `0`、Laravel `0`、Hyperf 仅 `UNICODE`；PHP 手册亦无官方推荐（仅有社区留言称之为 "clean json"，非官方建议）。

**下游 pay 真实用法**（**已验证，读过源码与注释**）：

| 位置 | 必须加的原因 | 不加的后果 |
|---|---|---|
| `src/Traits/BestpayTrait.php:188` | 对齐官方 Java SDK fastjson `AssembleUtil.translateMapData`（key 升序、null 保留、`/` 与非 ASCII 均不转义） | 含 URL 的嵌套字段（如聚合收款码 `codeUrl`）拼串为 `\/` → **验签失败** |
| `src/Plugin/Bestpay/V1/AddPayloadSignPlugin.php:70-71` | 同上（`commonParams`/`bizContent` 对齐 fastjson 默认序列化） | 签名不一致 |
| `src/Plugin/Alipay/V2/VerifySignaturePlugin.php:68` | 加密响应签名源为带双引号的 base64 密文原文，`/` 不能被转义 | 取串与官方 SDK 不一致 → 验签失败 |
| 其余 6+ 处（`PaypalTrait:152`、`Alipay/V2/FormatPayloadBizContentPlugin:25`、`Allinpay:73`、`Douyin:59/84`、`Provider/Bestpay:136` 等） | 默认 `0` 或仅 `JSON_UNESCAPED_UNICODE` | — |

**请求体与签名一致性机制**：pay 预生成 JSON 字符串存入 `_body`，签名插件与 `AddRadarPlugin` 读同一字符串（`get_radar_body()` → `$payload->get('_body')`，`yansongda-artful/src/Functions.php:85`），故 flags 在每个协议集成点内是单一事实来源，不依赖全局默认值。

**安全**（二手证据）：`JSON_UNESCAPED_SLASHES` 会让 `</script>` 原样输出，内联 HTML `<script>` 场景可逃逸 → XSS；nette/utils issue #235 称其为 dangerous default。**证据等级**：RFC/手册/源码为原文验证；nette issue、Node/Python/Go 行为、Symfony/Laravel 响应默认值为二手证据，不影响结论方向。

**最终决策**：默认不加（§3.3）。
