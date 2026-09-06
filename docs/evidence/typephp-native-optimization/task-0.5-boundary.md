# 2026-09-06 15:53:20 — T0.5 阶段 1（边界模式 main.php + Zend 基线固化 + workflow CI 步骤，待 tpc 回传）

## 本阶段完成事项

1. 精读 src 源码模式（只读），创建 `spike-tmp/boundary/main.php`（自包含、零顶层可执行语句、5 模式独立函数 + PAT_* 标记行）。
2. Zend 侧本地 Docker 实测完成，基线固化至 `spike-tmp/boundary/zend-output.txt`（纯输出文本）。
3. `.github/workflows/spike-typephp.yml` 追加 `Spike boundary` 步骤组（编译 → 运行 → diff）。
4. 临时 commit：`d1e8248 chore(spike): 边界模式双运行时验证快照(spike-tmp 临时入库,闸门后删除)`（main.php + zend-output.txt + workflow，**注明：spike-tmp/ 临时入库系 CI 交互协议所需**，闸门后删除）。evidence 本阶段不 commit。

## 源码模式精读要点（模式 → src 位置映射）

| 模式函数 | src 来源 | 覆盖点 |
|---|---|---|
| `pattern_static_cache()` | `src/Traits/Arrayable.php` toArray | 函数级 `static $cache = []` + `??=` 短路 + `ReflectionClass::getProperties()` + `getName()` + `$t->{$method}()` getter 分派 + `$t->{$k}` 动态属性读 + `$t->{'id'} = 9` 动态属性写 + `$t::class` 动态类名 + `method_exists`；二次取缓存验证 `??=` 右侧不执行（`===` 同一实例） |
| `pattern_reference_rebind()` | `src/Arr.php` forget（:191 附近） | 引用参数 `array &$array`、`$original = &$array`、`$array = &$original` 回置、`$array = &$array[$part]` 嵌套引用重绑定、`continue 2`（while+foreach 双层跳出）、顶层 unset 分支；keys `['top','b.c','e.f.g','missing.y.z']` 分别命中四条路径 |
| `pattern_foreach_reference()` | `src/Arr.php` sortRecursive（:460） | `foreach ($array as &$value)` 引用遍历、递归调用、`ksort`/`sort` 分支、isAssoc 判定（简化为 array_filter(array_keys,'is_string')） |
| `pattern_magic_interfaces()` | `src/Collection.php` 类声明 | `implements \ArrayAccess, \Countable, \IteratorAggregate, \JsonSerializable` + offsetExists/offsetGet/offsetSet（含 append）/offsetUnset/getIterator（\ArrayIterator）/count/jsonSerialize + **declared `toArray(): array` 豁免观察**（呼应 T0.2 结论②） |
| `pattern_func_static()` | `src/Str.php` charsArray（:317） | 函数级 `static $charsArray;` **未初始化声明** + `isset($charsArray)` 判定 + 大字面量（含 unicode 字符）数组 + 二次调用缓存短路（`===`） |

机械性偏差记录：
- 原 Arrayable/forget/sortRecursive 为类方法/static 方法内实现，本文件**复刻为普通函数**（自包含要求）；trait 内 `static::class` 以实例 `$t::class` 等价替换（PHP 8.0+ 语义相同）。
- `Str::studly`/`Str::snake` 不复刻（属 Str 关键字观察范围外，getter 分派用 `ucfirst` 简化，不影响模式覆盖点）。
- **单名 use 语句去噪**：初版含 `use ArrayAccess;` 等 7 行，Zend 8.3 对"non-compound name"use 产生 Warning 且 CLI 下混入 stdout（污染基线）→ 改为 FQCN 直引（`\ArrayAccess` 等），语义等价、输出纯净。此行为本身记入 learning（typephp 对单名 use 的处理留给 T3.2 观察）。

## Zend 侧实测（本地 Docker，基线已固化）

- 环境：`registry.cn-shenzhen.aliyuncs.com/yansongda/php:cli-8.3-alpine`（PHP 8.3.30）；命令 `php -l`（No syntax errors detected）+ `php -r 'require ".../main.php"; main();'`。
- **退出码 0**，stderr 0 字节。
- `spike-tmp/boundary/zend-output.txt` 全文（= main() 5 行输出）：
  ```
  PAT_STATIC_CACHE: {"id":9,"name":"demo"} cached=true
  PAT_REF_REBIND: {"b":{"d":3},"e":{"f":[]},"missing":{"y":5}}
  PAT_FOREACH_REF: {"a":{"c":3,"d":4},"list":[1,2,3],"z":{"a":1,"b":2}}
  PAT_MAGIC_IFACE: exists=true gone=false count=3 iter=a=1,c=3,0=4 json={"a":1,"c":3,"0":4} toArray={"a":1,"c":3,"0":4}
  PAT_FUNC_STATIC: count=4 sample=["\u00b0","\u2080","\u06f0","\uff10"] cached=true
  ```
- 要点：REF_REBIND 中 `"e":{"f":[]}` 为 PHP json_encode 对空数组的原生行为（双运行时一致即可）；`cached=true` ×2 证明 `??=`/isset 缓存短路语义。

## workflow 步骤设计（插入 native_types 步骤组之后、Spike Summary 之前）

1. `Spike boundary (compile single file)`（continue-on-error）：`tpc spike-tmp/boundary/main.php -o build/boundary/main`（单文件编译依据同 T0.4），输出+退出码入 `$SPIKE_DIR/boundary-compile.txt`、`boundary-compile-exit-code.txt`。
2. `Spike boundary (run compiled binary)`（continue-on-error）：`./build/boundary/main 2>&1 | tee $SPIKE_DIR/boundary-typephp.txt`，退出码 → `boundary-typephp-exit-code.txt`。
3. `Spike boundary (diff against Zend baseline)`（continue-on-error）：`diff $SPIKE_DIR/boundary-typephp.txt spike-tmp/boundary/zend-output.txt`（zend-output.txt 已随仓库 checkout）→ `boundary-diff.txt` + `boundary-diff-exit-code.txt`；**diff 退出码 0 = 双运行时行为一致**（diff 空）。
4. 输出文件全入 `$SPIKE_DIR/`，自动随既有 artifact 上传。

## 待回传项清单（阶段 2 依据）

1. `boundary-compile.txt` / `boundary-compile-exit-code.txt`（编译是否通过；FAIL 则逐报错归因到模式）。
2. `boundary-typephp.txt` / `boundary-typephp-exit-code.txt`（tpc 侧 5 行 PAT_* 输出）。
3. `boundary-diff.txt` / `boundary-diff-exit-code.txt`（**0 = happy 全 PASS**；非 0 则逐行差异归因 PAT_* 前缀）。
4. 阶段 2 将按模式逐个 PASS/FAIL 归档；FAIL 若涉及引用重绑定/foreach 引用/反射/魔术接口核心路径且无规避写法 → STOP 上报。

## Acceptance 状态

- [x] main.php 覆盖全部 5 类模式且零顶层语句、自包含（grep 复核无 require/Yansongda 引用、无 use 单名语句）
- [x] Zend 侧 Docker 实测完成（exit 0，zend-output.txt 固化 5 行基线）
- [x] workflow 步骤追加，YAML 自检通过
- [x] 临时 commit `d1e8248`（仅 spike-tmp/boundary/ 与 workflow）
- [x] evidence 阶段 1 小节 + learning 追加
- [ ] （待 CI）tpc 编译/运行/diff 结果 → 阶段 2 每模式 PASS/FAIL 结论
- [ ] Wave 0 收尾（闸门通过后由 main agent 另行指派）：删除 spike-tmp/ 整目录，本次不做

# 2026-09-06 16:03 main agent 亲自验证（T0.5 阶段 1）

1. main.php 内容级审查（通读 240 行）：自包含（无任何 require/引用 Yansongda 类）、零顶层可执行语句（仅 declare/类/函数定义）、5 类模式齐全且忠实对应 src 模式（static $cache[::class] ??= 短路 + === 同实例断言 / forget 引用重绑定 + continue 2 / foreach &$v + ksort/sort / 四魔术接口 + declared toArray / 函数级 static + isset + unicode 大字面量）；PAT_* 标记行设计支持单模式归因。
2. Zend 侧复跑（Docker，main() 实际调用）：退出码 0，输出与我 diff `spike-tmp/boundary/zend-output.txt` **完全一致**。
3. workflow 追加步骤位置确认（grep step 名）：boundary 组位于 native_types 之后、Spike Summary 之前；YAML 自检复跑 OK。
4. commit `d1e8248` 仅 3 文件（main.php / zend-output.txt / workflow）。
5. 结论：**阶段 1 验证通过**；每模式 PASS/FAIL 待 CI 回传后阶段 2 定稿。机械性偏差（单名 use 改 FQCN 避免 Warning 污染基线）合理已记录。
