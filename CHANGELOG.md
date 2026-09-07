# Changelog

## [Unreleased]

### Added

- composer.json 显式声明 `ext-simplexml` 与 `ext-libxml` 依赖（`Collection::toXml()`/`Arr::wrapXml()` 实际所需，此前未显式声明）
- TypePHP（tpc AOT）兼容基建：`tests/typephp/` 编译冒烟（tester.yml `typephp-smoke` job，每 PR 自动跑）与 `tests/benchmark/` Zend vs typephp 双运行时对比（benchmark workflow，workflow_dispatch + pull_request 双触发）
- 新增 `docs/typephp.md`（TypePHP 兼容层级、编译方式、benchmark 数据与已知差异清单）与 `UPGRADE.md`（升级指南）

### Changed

- ! `Collection::toString()` 更名为 `Collection::toQueryString()`：TypePHP 将 `toString()` 等作为保留关键字方法拦截，故更名；`Arr::toString()` 静态方法保留不变（迁移写法见 UPGRADE.md）
- ! `Collection::except()` 改为变参签名（`mixed ...$keys`）：兼容 TypePHP 严格参数计数；主用形态行为不变，仅 `except(['a'], ['b'])` 由旧静默丢弃多余实参改为执行期 `TypeError`（形态对照见 UPGRADE.md）
- ! `Pipeline::through()` 改为变参签名（`mixed ...$pipes`）：兼容 TypePHP 严格参数计数；主用形态行为不变，仅 `through(['a'], 'b')` 由旧静默丢弃改为执行期 `TypeError`；`through([$obj, 'method'])` 语义由"对象 + 字符串两管道"修正为"单个 callable-array 管道"（与 illuminate 主流用法一致）

### Fixed

- `Str::ascii()`/`Str::slug()`/`Str::kebab()` 在 `strict_types=1` 下对任意输入必抛 `TypeError` 的 bug（`charsArray()` 数字字符串键被 PHP 规范化为 int 键，`str_replace()` 收到 int 抛错）
- `Arr::sortRecursive()` 内部实现改写为键位赋值形态：避免「foreach 引用遍历 + 循环体内递归」组合在 TypePHP 下的行为差异，签名与可观察行为不变
- `Arr::shuffle()` 内部 usort 回调闭包补全 (value, key) 双参签名，兼容 TypePHP 严格实参计数
- `Collection::valueRetriever()` 返回的回调闭包补可选 `$key` 参数（every/sortBy 以双参调用），兼容 TypePHP 严格实参计数

## [v4.1.1] - 2026-09-05

### Fixed

- composer.json 显式声明 `psr/container` 依赖（`^1.1.1 || ^2.0`）：`Pipeline` 生产代码直接使用 `Psr\Container\ContainerInterface`，此前该包仅作为传递依赖存在，下游单独安装时可能出现 `Class not found` (#38)

## [v4.1.0] - 2026-09-02

### Changed
- PHP 最低版本从 8.0 提升至 8.2
- `Collection::has()`/`Collection::only()` 改为键存在性语义（键值为 `null` 时 `has` 返回 `true`、`only` 保留该键），与 `Arr::has()` 一致
- `Collection::first()`/`Collection::last()` 空集合时返回 `null`（原为 `false`）
- `Str::uuidV4()` 改用加密安全随机源 `random_bytes()`，符合 RFC 4122 v4（原为 `mt_rand()`）
- `Collection` 构造函数对不含点语法的键直接赋值，大数据量下性能提升约 4.5 倍（含点语法的键仍走展开逻辑）
- `Traits/Accessable::offsetUnset()` 宿主类存在 `forget()` 方法时执行删除（原为空实现）

### Removed
- composer.json 中过时的 `suggest` 项（Logger 相关能力已在 v4.0.0 移除）

### Fixed
- `Arr::merge()` 标量值被数组覆盖时抛出 `TypeError` 的 bug
- `Arr::toString()` 遇到数组/对象值时抛出 `InvalidArgumentException`（原为产生 warning 并拼接出无效字符串；标量与 `null` 的拼接行为不变，签名串场景不受影响）
- `Arr::wrapXml()` 非法 XML 抛出 `InvalidArgumentException`（原为 warning 并返回空数组），并禁用网络实体加载（XXE 加固）

## [v4.0.0] - 2022-07-15

### Changed
- PHP 最低版本改为 8.0

### Removed
- 删除 Yansongda\Supports\Logger\StdoutHandler
- 删除 Yansongda\Supports\Logger
