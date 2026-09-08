# 升级指南（UPGRADE）

本文件描述 `yansongda/supports` 各版本间的破坏性变更（BC）与行为变化，帮助下游从旧版本平滑升级。新增能力见 [CHANGELOG.md](CHANGELOG.md)。

## 4.1.x → 4.2.0（Unreleased）

### `Collection::toString()` 标记 `@deprecated`（软废弃，继续可用）

`Collection::toString()` 自 4.2.0 起标记废弃（phpdoc `@deprecated` + PHP 8 原生 `#[\Deprecated]` 注解，PHP 8.4+ 调用时自动触发 deprecation 提示，8.2/8.3 依赖 IDE/静态工具经 phpdoc 识别），实现改为委托新增的 `Collection::toQueryString()`，行为完全一致。本方法将在后续大版本移除，建议迁移；`Arr::toString()` 静态方法不受影响。

```php
// 旧写法（4.1.x，4.2.0 起标记废弃，仍可用）
$collection->toString();
$collection->toString(';');

// 新写法（4.2.0+，推荐）
$collection->toQueryString();
$collection->toQueryString(';');
```

```php
// 静态方法不变
Arr::toString(['a' => 1, 'b' => 2]); // 'a=1&b=2'
```

### `Collection::except()` 与 `Pipeline::through()` 改为变参签名（BC）

两方法改为变参签名（`except(mixed ...$keys)` / `through(mixed ...$pipes)`），主用形态行为不变。五种常用调用形态对照如下（except 与 through 同构）：

| 调用形态 | 4.1.x 行为 | 4.2.0+ 行为 |
|---|---|---|
| `except(['a', 'b'])` / `through(['a', 'b'])` | 正常 | **不变**（扁平单数组展开兼容） |
| `except('a', 'b')` / `through('a', 'b')` | 正常 | **不变** |
| `except('a')` / `through('a')` | 正常 | **不变** |
| 嵌套单实参 `except([['a', 'b']])` / `through([['a', 'b']])` | 执行期 TypeError | **不变**（仍为执行期 TypeError） |
| 多实参数组 `except(['a'], ['b'])` / `through(['a'], 'b')` | **静默丢弃**首参之外的实参（仅删除 `'a'`） | **执行期 `TypeError`**（不再静默丢弃） |

说明：

- 上述第 5 行为唯一的真实行为变化：旧实现对 `is_array($keys)` 为真的首参直接取用、后续实参被静默忽略；新版变参签名下该形态会落到非法管道/键值而抛 `TypeError`。若依赖旧行为请改为显式的单一数组实参写法。
- `through([$obj, 'method'])`（callable-array）语义变化：旧实现按"对象 `$obj` 与字符串 `'method'` 两个管道"处理（字符串管道再经容器解析）；新实现识别为**单个 callable-array 管道**直接调用（与 illuminate 主流用法一致），`through([[$obj, 'method']])`（外层数组包裹）行为不变。

### `Str::ascii()` / `Str::slug()` / `Str::kebab()` 修复 strict_types 下的 TypeError（bugfix）

修复前上述方法（`ascii` 及其上游路径）在 `strict_types=1` 下对**任意输入**必抛 `TypeError`（`charsArray()` 数组字面量的数字字符串键被 PHP 规范化为 int 键，`str_replace()` 收到 int 抛错）。修复后正常工作，如：

```php
Str::ascii('déjà ü');            // 'deja u'
Str::slug('Hello_World 5° C');   // 'hello-world-50-c'
Str::kebab('hello world');       // 'hello-world'
```

属 bugfix：修复前 100% 抛异常、无可依赖行为，故无 BC 风险。

### `Arr::sortRecursive()` 内部实现改写（无 BC）

内部实现由引用遍历改为键位赋值形态。签名 `sortRecursive(array $array): array` 与可观察行为完全不变（嵌套 assoc/list 混合、4 层深嵌套等 8 用例 var_export 逐字节比对等价），无需任何下游调整。
