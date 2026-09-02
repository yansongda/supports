# Changelog

## [Unreleased]

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
