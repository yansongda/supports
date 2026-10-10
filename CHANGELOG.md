# Changelog

## [v4.1.3] - 2026-10-10

### Added

- 新增 `Yansongda\Supports\Json` 工具类：`Json::encode()`/`Json::decode()` 薄封装原生 `json_encode()`/`json_decode()`，失败即抛 `\JsonException`（`code` 为 `JSON_ERROR_*`），不再需要靠 `false` 返回值或 `json_last_error()` 判断失败 (#42)
  - `Json::encode(mixed $value, int $flags = JSON_UNESCAPED_UNICODE, int $depth = 512): string`
  - `Json::decode(string $json, bool $assoc = true, int $depth = 512, int $flags = 0): mixed`（默认返回关联数组）
  - `encode()` 默认 flags 为 `JSON_UNESCAPED_UNICODE`（原生为 `0`，中文会被转义为 `\uXXXX`），传 `0` 可还原原生转义行为；`$flags`/`$depth` 参数名对齐原生函数，其余行为与原生一致
  - 使用示例（运行环境要求不变，仍为 PHP >= 8.2）：

    ```php
    use Yansongda\Supports\Json;

    Json::encode(['name' => '中文']);  // {"name":"中文"}
    Json::decode('{"name":"中文"}');   // ['name' => '中文']
    Json::decode('{bad');             // 抛 \JsonException，原生此处返回 null
    ```

## [v4.1.2] - 2026-09-09

### Added

- composer.json 显式声明 `ext-simplexml` 与 `ext-libxml` 依赖（`Collection::toXml()`/`Arr::wrapXml()` 实际所需，此前未显式声明）
- 新增 `Collection::toQueryString()` 方法：查询串构造（等价原 `toString()` 能力，`Arr::toString()` 静态方法不变）

### Changed

- `Collection::toString()` 标记 `@deprecated`（phpdoc + PHP 8 原生 `#[\Deprecated]` 注解，PHP 8.4+ 调用时自动触发 deprecation 提示）：继续可用，实现改为委托新增的 `toQueryString()`，行为不变；将在后续大版本移除，建议迁移
- `Arr::sortRecursive()` 内部实现由引用遍历改写为键位赋值形态，`Arr::shuffle()`/`Collection::valueRetriever()` 内部回调闭包签名补全：均为内部实现优化，签名与可观察行为不变
- `Collection::except()` 改为变参签名（`mixed ...$keys`）：主用形态行为不变，仅 `except(['a'], ['b'])` 由旧静默丢弃多余实参改为执行期 `TypeError`
- `Pipeline::through()` 改为变参签名（`mixed ...$pipes`）：主用形态行为不变，仅 `through(['a'], 'b')` 由旧静默丢弃改为执行期 `TypeError`；`through([$obj, 'method'])` 语义由"对象 + 字符串两管道"修正为"单个 callable-array 管道"（与 illuminate 主流用法一致）

### Fixed

- `Str::ascii()`/`Str::slug()`/`Str::kebab()` 在 `strict_types=1` 下对任意输入必抛 `TypeError` 的 bug（`charsArray()` 数字字符串键被 PHP 规范化为 int 键，`str_replace()` 收到 int 抛错）

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
