# 技术设计:yansongda/supports 的 TypePHP 原生类型优化

> **时间**:2026-09-05
> **作者**:GLM 5.3 Flash + yansongda
> **状态**:经过人工审核确认(2026-09-05 修订:吸收 plan-reviewer 初审意见,见文末修订记录)

---

## 1. 背景与问题

**现状**:supports 是纯 PHP 基础库(Arr/Str/Collection/Config/Pipeline + 3 个 trait,约 1950 行),运行于 Zend PHP ≥ 8.2,被 yansongda/pay 等下游消费。前置调研确认的 typephp 硬冲突为 3 处代码点(`Collection::toString` 带参 / `Pipeline::through` 与 `Collection::except` 两处 `func_get_args`);原第 4 项(psra/container 未显式声明)已由 PR #38 解决,仅剩 `Arr::wrapXml` 隐式依赖的 ext-simplexml/ext-libxml 未声明。

**调研结论**(均已验证——typephp 官方文档 + 编译器源码):

1. **typephp 的"原生类型"只有 `int`/`float`/`bool` 三个**(映射 `int64_t`/`double`/`bool`,官方宣称 100-300x)。**`string`/`array`/`object` 仍是 ZVAL**(`php::Str`/`php::Array`),收益来自"无 opcode 分发 + 内部函数直调",常规数倍级。
2. **`std::vector(Type::Int)` 等强类型容器是 typephp 特有运行时构造,无 Zend PHP polyfill**,且要求编译期固定元素类型(仅 `Type::Any` 装得下 mixed)。supports 作为 composer 库必须双运行时可跑 → **库源码不直接使用 std 容器**。
3. **官方建议**:"业务边界保持 PHP Array/对象,std 容器只用于热点内部" —— 对库而言,std 容器的正确位置在**下游项目**(如 pay 的签名计算),不在 supports。
4. supports 的 static 数组缓存模式(`Str::charsArray`、`Arrayable::$cache`)正是 typephp 专门 benchmark 的 `static-cache` 场景(编译为 C++ 静态存储、零重建),**保留代码即可受益**。
5. typephp 编译环境要求 PHP 8.4–8.5(release 资产基于 8.5 **ZTS** 构建,另需 CMake 3.24+、GMP/MPFR、可加载的 libphp;libphp 自动安装器仅在 Linux 交互终端启用,CI 需显式配置)——macOS 本机编译路径不可行,typephp 验证以 CI(linux-x64)为准。

**困境**:
1. 现有签名大量使用联合类型(`int|string`、`callable|string`、`float|int`),typephp 下联合 = `mixed/any`(Var),函数边界无法原生化;
2. `call_user_func`、`func_get_args`、动态方法名等模式阻断编译器直调;
3. 无任何 typephp 编译/性能验证基建,"支持"不可持续;
4. 原生类型的语义差异(Int += Float 截断、局部类型固定)与 PHP 行为不同,盲目加 `use native_types` 有行为风险。

**目标**:
- **双运行时正确**:Zend PHP 8.2–8.5 全量单测绿;typephp(linux-x64)编译通过 + 冒烟一致;
- **可度量的性能提升**:静态方法/标量边界直调 + 原生化,benchmark 报告改前 vs 改后;
- **BC 破坏可控**:破坏性变更集中在 3 个 API 点(toString 重命名、through/except 变参化),**不收窄任何联合类型签名**,提供 UPGRADE 指南(已确认接受 BC);
- **下游引导**:文档明确 std 容器的正确使用位置在下游热点。

---

## 2. 整体方案

**核心思路**:**把 supports 改造为"typephp 静态分析友好"的双运行时库——不引入任何 typephp 特有语法,而是通过签名打磨 + 直调化 + 缓存模式保留,让 typephp 在下游编译时自动生成原生/直调代码**。

```
                    ┌─────────────────────────────────────────┐
                    │           yansongda/supports             │
                    │  (标准 PHP 源码,Zend 8.2-8.5 可跑)        │
                    ├─────────────────────────────────────────┤
  改造层(本方案)     │ A. BC 改造:toString→toQueryString 等     │
                    │ B. 签名/直调打磨:int|bool 边界原生        │
                    │ C. static 缓存保留(编译期常量)            │
                    ├─────────────────────────────────────────┤
  基建层(本方案)     │ D. typephp 冒烟(tests/typephp + CI job)  │
                    │ E. benchmark(Zend vs typephp 对比报告)    │
                    └────────────────┬────────────────────────┘
                                     │ composer 依赖
                                     ▼
                    ┌─────────────────────────────────────────┐
                    │   下游项目(如 yansongda/pay)              │
                    │   - 热点路径自行使用 std 容器/natives      │
                    │   - tpc 编译时把 vendor/supports 一并纳入 │
                    └─────────────────────────────────────────┘
```

**文件结构变更**:

```
src/                              # B 层签名打磨(+3 文件小改)、A 层 BC 改造
composer.json                     # + ext-simplexml、ext-libxml(psra/container 已于 #38 声明)
tests/typephp/                    # [新增] 冒烟:project.yml + main.php + expected/
tests/benchmark/                  # [新增] 双运行时对比脚本
docs/typephp.md                   # [新增] 编译方式/兼容层级/fallback 清单/下游 std 容器指引
UPGRADE.md                        # [新增] BC 变更说明
.github/workflows/*.yml           # 确认 PHP 8.4/8.5 矩阵(已存在);+ typephp-smoke、benchmark workflow、spike workflow(临时)
```

---

## 3. 详细设计

### 3.1 A 层:BC 改造(typephp 硬冲突,4 项)

| # | 位置 | 现状 | 变更 | BC 影响 |
|---|------|------|------|---------|
| A1 | `src/Collection.php:305` | `toString(string $separator = '&')` | 重命名 **`toQueryString(string $separator = '&')`**,旧名删除 | 下游调用需改名(`Arr::toString` 静态方法不受影响,保留) |
| A2 | `src/Pipeline.php:37` | `through(mixed $pipes)` + `func_get_args()` | `through(mixed ...$pipes)`,内部启发式还原单数组参数,保持 `through(['a','b'])` 与 `through('a','b')` 双形态 | 边缘歧义:单管道为 callable-array 时用 `is_callable` 启发式区分;单测覆盖 5 种调用形态 |
| A3 | `src/Collection.php:101` | `except(mixed $keys)` + `func_get_args()` | `except(mixed ...$keys)`(key 不可能为 callable,`1===count && is_array` 直接展开,无歧义) | 无(key 只能 int\|string) |
| A4 | `composer.json` | psr/container 已声明(PR #38);wrapXml 隐式依赖 simplexml/libxml | 显式声明 `ext-simplexml`、`ext-libxml` | 无(此前靠依赖链) |

### 3.2 B 层:签名与直调打磨(优化主体)

| # | 位置 | 变更 | typephp 收益 | 行为影响 |
|---|------|------|-------------|---------|
| B1 | ~~`Str::startsWith` 收窄~~ **撤销**:int 支持是 2024-11 特意添加的特性(#27),收窄属无谓 BC;typephp 下联合 = mixed 走 fallback(方法体内已有 `strval`,损失可忽略) | 不改 | — | 无 |
| B2 | `Arr::first`:`call_user_func($callback, $v, $k)` → `$callback($v, $k)` | 直调化 | `?callable` 参数已知签名,可静态直调 | 无 |
| B3 | `Arr::sortRecursive` 等引用 foreach、`Arr::forget/set/pull` 引用参数 | **不改代码**,spike 实测后仅在失败时调整 | 引用路径 typephp 支持,预期通过 | 无 |
| B4 | `src/Str.php` 头部 `use native_types;` | **spike 决定**:Zend 下 no-op 确认 + 编译通过后,为 `Str::random`/`uuidV4` 的 int 局部变量($len/$size/位运算)获得 int64 原生加速 | int 循环计数/位运算原生 | 若 spike 发现 Int+=Float 截断或类型固定报错则不加(supports 代码内未发现此类模式,预期安全) |
| B5 | `Arr/Collection/Functions/Traits/Pipeline` | **不加** `use native_types`(mixed 容器操作为主,Var dominates 的 Zend 语义正是需保留的行为) | — | 无 |
| B6 | 不收窄 `Arr::flatten(float\|int $depth)`、`every/sortBy(callable\|string)`、`Str::startsWith(int\|string)` | 保留联合(优化收益 < 行为风险) | 该路径维持 Var | 无 |

**边界**:B 层是"编译器友好化",不重写任何算法;`Accessable` 的 getter 解析与 `Pipeline` 的动态分发是下游契约(源码注释"勿改"),**保持不动**,其 fallback 特性记入 docs/typephp.md 兼容层级表。

### 3.3 C 层:static 缓存(零代码变更)

`Str::charsArray()`(约 150 行字面量)、`languageSpecificCharsArray()`、`Arrayable::toArray()` 的 `static $cache[static::class] ??=` 全部**保持现状**——该模式与 typephp `static-cache` benchmark 场景同类,预期受益于编译期静态化(注意:benchmark 实测模式为类静态属性,supports 为函数级 static 变量,机制待 T0.5 实测佐证,性能主张不作为承诺)。

### 3.4 D 层:typephp 冒烟与 CI

```
tests/typephp/
├── project.yml        # name: supports-smoke, mode: bin, sources: [../../vendor/psr/container/src, ../../src, main.php]
├── main.php           # 零顶层可执行语句;main(): void 覆盖 Arr/Str(含 slug/ascii)/Collection/Config/Pipeline 主路径
├── zend-run.php       # 仅 Zend 侧使用:require autoload + require main.php + main(),用于生成 expected.txt
└── expected.txt       # 期望输出,运行比对
```

main.php 保持无顶层 require(typephp 禁止全局可执行语句);ContainerInterface 定义经 sources 纳入,无需 autoload。CI(linux-x64)新增 typephp-smoke job:setup-php 8.5(**phpts: ts**,与 release 资产 ZTS 匹配)+ `apt install libgmp-dev libmpfr-dev cmake` + 下载 tpc release(锁定版本)→ `tpc project.yml -o build/smoke` → 运行产物 diff `expected.txt`。phpunit 矩阵 8.4/8.5 已存在(tester.yml),仅需确认。

### 3.5 E 层:benchmark

参考 typephp `benchmark/` 结构,双运行时**同一 CI runner 内对比**(先 Zend 后 tpc 产物;与“同机本地对比”方法论有取舍,已在风险表记录):`Str::snake/slug/random/uuidV4`、`Arr::collapse/merge/dot/camelCaseKey`、`Collection::map/sortBy/链式`、`Pipeline` 全链。**报告 ratio,不设 CI 硬阈值**(共享 runner 抖动)。预期(推断,以实测为准):string/array 路径 2–5x,int 边界原生,Pipeline ~1x。

### 3.6 接口契约标注

| 契约 | 状态 |
|------|------|
| typephp 硬冲突 4 项(toString/func_get_args×2/严格参数计数) | **已验证**(编译器源码 `KEYWORD_METHOD_MAP`/`genFuncGetArgs`/`ParameterCountCheckGenerator`) |
| int/float/bool 原生、string/array ZVAL、类型提升规则 | **已验证**(NATIVE_TYPES.md) |
| std 容器无 Zend polyfill、自动转 PHP Array、foreach 结构修改禁改 | **已验证**(STD_CONTAINERS.md;无 polyfill 为检索结论,未实测安装) |
| static-cache 模式受 typephp 优化 | **部分验证**(存在专项 benchmark/static-cache,但其模式为类静态属性;supports 为函数级 static 变量,机制待 T0.2/T0.5 实测佐证) |
| `use native_types` 在 Zend 下 no-op、签名不带 use 时是否仍按声明映射 | **推断(未实测)** → spike S3/S1 |
| 引用重绑定/反射/static::class 实际可编译性 | **推断(未实测)** → spike S4/S5 |
| `Arr::toString` 静态调用不受关键字拦截、`toArray()` declared-method 豁免 | **推断(文档仅佐证实例方法路径)** → T0.2 归类表显式记录 |

---

## 4. 推进策略

```
Phase 0  spike 闸门(先行,阻塞后续)
├── CI 内准备 tpc(setup-php 8.5 ZTS + 系统依赖 + release),src 全量编译 --dry,收集报错清单
├── S2 Zend 容器基线;S3 use native_types 双运行时验证(spike 文件经仓库内 spike-tmp/ 挂载进容器)
├── S4/S5 static::class 缓存 / 引用重绑定 编译+行为验证
└── 验证点:报错面 ≤ 预期(仅已知 4 项 + 少量)→ 继续;引用/反射大面积失败 → 回对话调整
Phase 1  代码改造
├── A1–A4 + B2 + 单测更新(串行执行,避免并发全量验收互踩)
└── 验证点:phpunit 全量 + phpstan + cs-fix 全绿(CI 矩阵 8.2–8.5)
Phase 2  基建
├── tests/typephp 冒烟 + CI job 编译清零
└── 验证点:CI typephp job 绿(编译 + 输出 diff 一致)
Phase 3  度量与文档
├── benchmark 双运行时报告;B4 按 spike 结论落地或放弃
└── docs/typephp.md + UPGRADE.md + CHANGELOG
```

**回滚**:全部为仓库内变更,`git revert` 即回滚;下游(pay)适配为独立 PR 不阻塞。无线上部署环节。

## 5. 风险与对策

| 风险 | 严重度 | 对策 |
|------|--------|------|
| typephp 处于 beta(官方称 2026-10-01 前全面开源),行为可能变动 | 高 | 冒烟 CI 锁定 tpc 版本号;升级 typephp 必须重跑冒烟;方案不含 typephp 特有语法,编译器演进对代码零侵入 |
| `through` 变参化的 callable-array 管道歧义 | 中 | `is_callable` 启发式 + 单测覆盖 5 种调用形态;歧义场景写入 UPGRADE |
| 下游 pay 使用旧 `Collection::toString` | 中 | 用户已接受 BC;UPGRADE.md + 下游同步适配 PR 列入交付物 |
| 引用/反射/魔术接口在 AOT 下静默行为差异 | 中 | 冒烟 main.php 覆盖上述路径 + 断言;phpunit 8.4/8.5 常驻矩阵 |
| `use native_types` 语义差异(Int+=Float 截断/类型固定) | 中 | 仅 spike 通过后用于 Str.php 单文件;出现即撤,默认不加 |
| tpc 本机(macOS)不可行(libphp 安装器仅 Linux 交互终端;brew 无 libphp.dylib) | 低 | 全部 typephp 编译验证走 CI;本机仅容器 Zend 侧 |
| typephp beta 期 release 资产变动/撤下 | 中 | CI 锁定版本号 + 资产文件名;T0.1 记录确切 URL,资产 404 时 STOP 上报 |
| setup-php ZTS + embed SAPI 组合可用性未实测 | 中 | typephp-smoke job 首步 `php -v` 验证 ZTS;NTS 仅作诊断性尝试(tpc 资产仅 ZTS,大概率 ABI 不匹配),失败即 STOP + evidence 记录 |
| expected.txt(8.3 生成)与 tpc 产物(8.5)输出漂移 | 低 | diff 失败先排查版本因素(json/浮点格式化),必要时屏蔽易漂移行并记录 |
| benchmark 抖动导致误判 | 低 | 不设 CI 阈值;同机对比、best-of-N 取样(沿用 typephp 方法) |

## 6. 验证与度量

- **正确性**:CI 绿 = phpunit(8.2–8.5,矩阵已存在)+ phpstan L6 + cs-fix + typephp-smoke job(编译成功且 `diff` 为空);
- **性能**:`tests/benchmark/run.php` 输出 JSON 报告,样例:

```json
{ "env": {"php": "8.5.0", "tpc": "v1.x"}, "results": [
  { "case": "Str::snake x100k",   "zend_ms": 210, "typephp_ms": 55, "ratio": 3.8 },
  { "case": "Collection map x10k","zend_ms": 180, "typephp_ms": 60, "ratio": 3.0 } ] }
```

- 发布基线:版本发布前跑一次 benchmark 归档到 `docs/typephp.md`。

---

## 修订记录

- 2026-09-05 初版(GLM + yansongda 批准)。
- 2026-09-05 修订(吸收 plan-reviewer 初审,0 BLOCKER / 8 MAJOR / 12 MINOR):A4 修正(psra/container 已由 #38 声明,仅补 ext-*);B1 撤销 startsWith 收窄(回退 #27 属无谓 BC);CI 表述修正(8.4/8.5 矩阵已存在);typephp 编译验证全面转 CI(macOS 本机不可行,ZTS/GMP/MPFR/CMake/libphp 约束);冒烟增加 zend-run wrapper 与 slug/ascii 覆盖;Wave 1 改串行;风险表补 3 项。
- 2026-09-05 二修(吸收 plan-reviewer 复审,0 BLOCKER / 3 MAJOR / 14 MINOR,均落地):composer require 重排为字母序(方案 b);新增 CI 交互协议(worker 无 push 权限,STOP 等用户回传 run log/artifact);Wave 3 改串行(T3.2 依赖 T3.1 报告);边缘形态(TypeError)单测固定 + UPGRADE 记录;NTS 回退降为诊断性尝试;行号/表述等 14 项 MINOR 修正。
- 2026-09-05 三修(吸收 plan-reviewer 终轮,0 BLOCKER / 3 MAJOR / 10 MINOR,均落地):workflows 改动权限细化(spike 归 Wave 0、benchmark 归 T3.1、tester/style 归 T2.2);删除并行安全说明中 T3.1‖T3.2 残留;T3.1 新增 benchmark-typephp.yml 作为 CI 载体且报告数据同源自同一次 run;另 10 项 MINOR(计数/文件名/触发点说明/Unreleased 措辞等)。复审轮次达流程上限,终轮后由用户裁决进入实施。
- 2026-09-05 四修(吸收 plan-reviewer 第 3 轮复审,用户指定额外轮次;0 BLOCKER / 2 MAJOR / 7 MINOR,均落地):规则 3 补 T2.2 对 spike workflow 的处置授权;F1 审计改为基线 commit 比对并补 CHANGELOG/README 白名单;补嵌套单参数组对偶边缘形态(旧 TypeError→新展开成功)单测与 UPGRADE;CLI 契约补 --runtime;矩阵/依赖/称谓等表述同步。
