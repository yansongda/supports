# TypePHP（tpc AOT）兼容与优化说明

本文档说明 `yansongda/supports` 对 [Swoole TypePHP](https://github.com/swoole/typephp)（`tpc` AOT 编译器，v0.7.0 beta 实测）的兼容性现状、编译方式、性能数据与下游使用约束。所有结论均来自本库 CI 实测（同机双运行时冒烟 + benchmark），未实测的部分会明确标注。

## 目录

- [兼容层级](#兼容层级)
- [编译方式（下游 project.yml 示例）](#编译方式下游-projectyml-示例)
- [benchmark 数据](#benchmark-数据)
- [`use native_types` 决策记录](#use-native_types-决策记录)
- [下游使用约束 / 已知差异清单](#下游使用约束--已知差异清单)
- [std 容器使用指引](#std-容器使用指引)
- [CI 说明](#ci-说明)

## 兼容层级

本库在 typephp 下的兼容性分三个层级：

| 层级 | 范围 | 现状 |
|---|---|---|
| ① 原生边界 | 标量类型边界转换（NATIVE_TYPES 语义） | `int`/`float`/`bool` 编译为原生类型（`php::Int` = `zend_long`、`php::Float` = `double`、`php::Bool` = `bool`）；`string`/`array`/`object` 仍为 ZVAL（`php::Str`/`php::Array`/`php::Object`）。本库未声明 `use native_types`（决策见下文） |
| ② 直调 | 本库公开方法（Collection / Arr / Str / Pipeline / Config 等） | **全部冒烟实证通过**：typephp 冒烟 main 覆盖 41 个 SMOKE 断言点（`tests/typephp/`），CI 双运行时输出逐字节 diff 全绿 |
| ③ 运行时 fallback | 动态分发路径 | `Pipeline` 的管道动态分发（字符串管道经容器解析、`$pipe(...)` 动态调用）与 `Accessable` trait 的 getter 动态属性解析（`$this->{$k}`）仍走运行时逻辑，AOT 直调收益有限——如实描述，不夸大 |

## 编译方式（下游 project.yml 示例）

下游项目将本库编译进 AOT 二进制时，`project.yml` 的 `sources` 需同时包含本库源码、`psr/container` 接口与业务入口（实测 schema，字段以官方仓库根 project.yml 为准）：

```yaml
name: my-app
build-mode: bin
sources:
  - vendor/yansongda/supports/src
  - vendor/psr/container/src
  - main.php
```

要点：

- `build-mode: bin`（生成 AOT 二进制，入口为全局 `main()`）；本库所有源文件均为零顶层可执行语句（仅 `declare(strict_types=1)` / `use` / 类与函数定义），满足 typephp 对全局作用域的 Intentional Rule 限制。
- 业务入口 `main.php` 必须定义全局 `main()`（无参或 `(int $argc, array $argv)`，返回 `void`）；bin 模式下由二进制启动时调用。
- **tpc 标准调用模式：`cd` 到 tpc 解压根目录 + 所有参数一律绝对路径**。tpc 二进制内部以 CWD 相对方式加载其自带的 `vendor/autoload.php`，从其它目录调用会 Fatal error：

  ```bash
  tar -xzf tpc_v0.7.0_linux_x64.tar.gz -C /opt/typephp-dist
  cd /opt/typephp-dist/tpc_v0.7.0_linux_x64
  ./tpc /absolute/path/to/your/project.yml \
        -o /absolute/path/to/your/project/build/my_app_bin
  ```

- 编译产物（bin 模式二进制）独立运行，不依赖 tpc 包目录。
- **`-o` 输出名会被规范化为合法 C 标识符**：连字符 `-` 自动转下划线 `_`（如 `-o bench-tpc-bin` 实际产出 `bench_tpc_bin`），运行时请按下划线名取产物。

## benchmark 数据

CI 同机（同一 GitHub Actions runner）双运行时实测：Zend = 容器内 PHP 8.5.10 ZTS CLI，typephp = tpc v0.7.0 AOT 二进制（SAPI: embed），rounds=5 取最优（best-of-5）。**ratio = typephp_ms / zend_ms**，大于 1 表示 typephp 慢于 Zend。

数据来源：Benchmark run `34135976686` artifact（bench-report.json），10 case 原样引用，未做任何修饰：

| case | n | zend_ms | typephp_ms | ratio |
|---|---:|---:|---:|---:|
| str_snake | 100000 | 69.134 | 200.085 | 2.894 |
| str_slug | 50000 | 748.653 | 2178.230 | 2.910 |
| str_random | 10000 | 11.755 | 22.762 | 1.936 |
| str_uuid_v4 | 10000 | 14.225 | 24.129 | 1.696 |
| arr_collapse | 20000 | 6.835 | 57.540 | 8.418 |
| arr_merge | 20000 | 16.866 | 80.518 | 4.774 |
| arr_dot | 10000 | 8.145 | 44.416 | 5.453 |
| arr_camel_case_key | 10000 | 26.940 | 114.609 | 4.254 |
| collection_map_filter_sort | 5000 | 34.431 | 144.041 | 4.183 |
| pipeline_full_chain | 2000 | 4.993 | 27.496 | 5.507 |

**方法论与取舍说明**：

- 同机同 PHP 版本对比消除了跨机器/跨版本误差，结论方向可信；但 CI 共享 runner 存在负载噪声，绝对耗时与本地裸机会有出入——**判断性能请看 ratio 的量级而非绝对毫秒数**。
- tpc v0.7.0 为 beta，编译日志可见 `-O0`（无优化编译）。在当前版本下本库工作负载 typephp 全面慢于 Zend（ratio 1.696 ~ 8.418），未来 tpc 开启优化编译后需重测，本表数据不代表 tpc 正式版表现。
- 复现脚本：`tests/benchmark/`（`run.php` 支持 `--runtime=zend|typephp --rounds=N --output=<file>` 与 `--merge` 合并报告），CI 由 `.github/workflows/benchmark-typephp.yml` 双触发自动执行。

## `use native_types` 决策记录

**决策：本库 src 不加 `use native_types`**（正向结论，非失败）。plan 预设三条件 AND，实测对照如下：

| # | 条件 | 结论 | 依据 |
|---|---|---|---|
| 1 | Zend no-op 成立 | ✅ | `use native_types;` 在 Zend 下为 Warning 级 no-op（`The use statement with non-compound name 'native_types' has no effect`），输出语义不变（实测 `12.5`/`4`） |
| 2 | tpc 编译通过 | ✅ | tpc 编译通过（冒烟 CI 绿），且 `Int += Float` 截断语义实证（10 + 2.5 → `12`，NATIVE_TYPES.md 明言 intentional semantics） |
| 3 | benchmark int 路径收益 > 0 | ❌ | str_snake 2.894 / str_slug 2.910 / str_random 1.936 / str_uuid_v4 1.696，int 热点路径全面慢于 Zend（`-O0` 背景） |

补充考量：

- `use native_types` 为**文件级**语义：声明后该文件内 `Int += Float` 复合赋值会截断回 int（Zend 下变量提升为 float）。在收益为负时引入此行为差异风险没有意义。
- 本库 src 中存在 `int`/`float` 混合运算路径，截断语义审计成本高、收益为零。
- **未来 tpc 版本（开启优化编译、消除 -O0 背景后）将重新评估**，届时重跑 benchmark 并重审本库 `$int += $float` 形态路径。

## 下游使用约束 / 已知差异清单

以下全部为本库 CI 实测结论（非推测），下游在 typephp 下使用本库时需注意：

### ① tpc 闭包实参计数严格（最重要）

传给 `Collection::map` / `Collection::filter` / `Arr::first` / `Collection::sortBy` 等方法的**回调闭包必须声明完整参数表**。Zend 会静默忽略闭包多余的实参，tpc 在严格实参计数下抛出 `ArgumentCountError`。例如：

```php
// map 内部以 (item, key) 双参调用回调 —— 闭包必须声明两个参数
$collection->map(function ($item, $key = null) {
    return $item;
});
```

本库内部使用的全部闭包（含 `Arr::shuffle` 的 usort 回调、`valueRetriever` 返回的回调等）已在 v4.2.0 补全契约签名，下游只需注意**自己传入的回调**。

### ② foreach 引用遍历 + 循环体内递归组合的行为差异

`foreach ($array as &$v)` 引用遍历且循环体内递归自调用的组合在 tpc 下行为异常（实测输出为空）。规避方式是改用键位赋值：

```php
// tpc 下有行为差异的写法（本库已淘汰）
foreach ($array as &$value) {
    $value = recurse($value);
}

// 等价且 tpc 兼容的写法（本库 sortRecursive 现行实现）
foreach ($array as $key => $value) {
    $array[$key] = recurse($value);
}
```

本库唯一受影响的 `Arr::sortRecursive` 已改为键位赋值形态（签名与可观察行为不变，等价性经 8 用例逐字节比对验证），并已列入 [UPGRADE.md](../UPGRADE.md) 说明。

### ③ `-o` 输出名规范化

tpc 将 `-o` 输出名规范化为合法 C 标识符，连字符自动转下划线（见上文编译方式一节）。

### ④ `use native_types` 文件级语义（当前不加）

`use native_types;` 是文件级声明：声明后 `Int += Float` 复合赋值截断（10 + 2.5 → `12`，Zend 下为 `12.5`）。基于 benchmark 实测当前无收益，本库不加（决策记录见上文）；下游若自行声明需自行审计此类赋值路径。

### ⑤ declare 仅支持 `strict_types=1`

typephp 仅接受 `declare(strict_types=1)`（`ticks`/`encoding` 等不支持）。本库全部源文件均为 `declare(strict_types=1)`，天然合规。

### ⑥ 保留关键字方法（`toString` 已更名为 `toQueryString`）

typephp 将 `toInt()`/`toString()`/`toArray()` 等（方法名大小写不敏感，另有禁用自定义的 `toAny()`/`toRef()`）作为保留关键字方法，在普通对象方法语义**之前**解析拦截，同名应用方法不会被正常调用。因此 `Collection::toString()` 已更名为 `toQueryString()`（`Arr::toString()` 静态方法保留不变），迁移写法见 [UPGRADE.md](../UPGRADE.md)。下游业务代码若存在自定义的 `toString()` 方法，在 typephp 下同样会被拦截，需自行评估。

## std 容器使用指引

typephp 提供一组 `std` 强类型容器（基于 PHPX Box 承载具体 C++ 模板实例），用于在 AOT 编译场景下替换性能敏感路径中的 PHP Array，可让编译器获得确定的元素类型、键类型与容器结构，生成更直接的 C++ 代码（官方宣称数组访问性能提升可达 10 倍，见官方 STD_CONTAINERS.md）。

- 边界处理：PHP Array 与 std 容器之间的传递跨越 Zend Object / Native Class Object 存储边界，跨边界高频转换可能抵消收益——std 容器适合**热点路径整体替换**，不适合与 PHP Array 混用来回转换。
- 本库公开 API 仍以 PHP Array 为数据契约（`array` 参数与返回值），不改变；下游若在自身热路径引入 std 容器，与本库互操作时注意边界成本。
- 概念细节以官方文档 STD_CONTAINERS.md 与 OBJECT_STORAGE_AND_PASSING_MODELS.md 为准。

## CI 说明

本库内置三类 typephp 相关 CI 基建（随仓库 workflows 提供）：

| 基建 | 触发 | 说明 |
|---|---|---|
| `typephp-smoke` job（tester.yml） | 每个 PR 自动运行 | 下载 tpc release + 构建 libphpx + 编译 `tests/typephp/main.php`（含本库全部公开方法冒烟），双运行时输出逐字节 diff 校验 |
| `benchmark-typephp` workflow | `workflow_dispatch` + `pull_request` 双触发 | 同机顺序跑 Zend → tpc 编译 → 双侧 benchmark → 合并 ratio 报告，三份 JSON artifact 上传 |
| 容器镜像 | — | `ghcr.io/yansongda/docker-php:zts-8.5-trixie`：PHP 8.5.10 ZTS，内置 `libphp.so`（embed 库）与本库所需扩展（mbstring/ctype/simplexml/libxml），两个 job 均基于此镜像运行 |
