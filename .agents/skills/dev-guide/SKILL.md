---
name: dev-guide
description: Use when developing or testing yansongda/supports. Covers commands, code standards, and project structure.
---

# yansongda/supports 开发指南

## 开发命令

**对 `src/` PHP 代码的任何修改，需运行以下三项检查：**

```bash
composer cs-fix && composer analyse && composer test
```

本地无 PHP 时使用 Container 备选，详见 `container-dev` Skill。

### 测试
```bash
composer test       # PHPUnit 11.5 + Mockery 1.6
```
- Mock HTTP 客户端/容器依赖，避免真实外部请求
- 测试类如使用 PHPUnit 元数据，必须用 PHP attributes（如 `#[CoversNothing]`），不要用 doc-comment 注解（PHPUnit 12 起不再支持）

### 代码风格
```bash
composer cs-fix     # 代码格式化检查（dry-run）
```
- 规则集：`@PhpCsFixer` + `declare_strict_types`，见 `.php-cs-fixer.php`

### 静态分析
```bash
composer analyse    # PHPStan level 5
```

### CI 矩阵

- `tester.yml`：PHPUnit，PHP 8.2 / 8.3 / 8.4 / 8.5
- `style.yml`：php-cs-fixer（PHP 8.3）+ PHPStan，PHP 8.2 / 8.3 / 8.4 / 8.5

## 代码规范

- `declare(strict_types=1);` 必须
- `use` 导入，禁止在代码中直接写 `\Yansongda\Supports\...` 完整命名空间
- 联合类型中 `null` 排在末尾（如 `int|string|null`）
- 公共 API 保持向后兼容；`require.php` 最低版本为 8.2，禁止使用更高版本语法

## 项目结构

```
src/
├── Arr.php           # 数组工具（accessible/get/set/has/pluck/...）
├── Collection.php    # 集合类（实现 ArrayAccess/Countable/IteratorAggregate/JsonSerializable）
├── Config.php        # 配置类（继承 Collection）
├── Functions.php     # 命名空间函数：collect()/value()/data_get()（Yansongda\Supports 下，勿加 function_exists guard）
├── Pipeline.php      # 管道
├── Str.php           # 字符串工具
└── Traits/
    ├── Accessable.php    # ArrayAccess 访问能力
    ├── Arrayable.php     # toArray 能力
    └── Serializable.php  # 序列化能力（防御式 method_exists guard 勿删，见 @phpstan-ignore-next-line）
tests/               # 与 src/ 一一对应；tests/Stubs/ 为测试桩
```

## 常见错误

- 忽略 `declare(strict_types=1);`
- 直接写完整命名空间
- 给 `Functions.php` 中的命名空间函数加 `function_exists('xxx')` guard（无效且多余：guard 查的是全局函数，composer files autoload 已保证单次加载）
- 修改 `Traits/Serializable.php` 时删除防御式 `method_exists` 检查（trait 可被无 `toArray/set` 方法的类复用）
