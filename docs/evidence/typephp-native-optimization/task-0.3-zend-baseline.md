# 2026-09-06 15:21:29

## 任务

T0.3 Zend 基线（改造前）：在容器内跑 `composer test`、`composer analyse`、`composer cs-fix`，记录全绿。不修改任何源码/配置。

## 执行环境

- Docker 28.1.1 daemon UP，全部命令经容器执行（本机 macOS 无 PHP）：
  ```bash
  docker run --rm -v "$PWD":/app -w /app \
    registry.cn-shenzhen.aliyuncs.com/yansongda/php:cli-8.3-alpine \
    sh -c "COMPOSER_ALLOW_SUPERUSER=1 <命令>"
  ```
- 镜像内 PHP 8.3.30 NTS；vendor/ 已存在，未执行 composer install；网络正常（无 DNS fix 需要）。

## 命令与结果

### 1. composer test

命令原文：

```bash
docker run --rm -v "$PWD":/app -w /app \
  registry.cn-shenzhen.aliyuncs.com/yansongda/php:cli-8.3-alpine \
  sh -c "COMPOSER_ALLOW_SUPERUSER=1 composer test"
```

退出码：**0**

输出摘要：

```
PHPUnit 11.5.56 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.3.30
Configuration: /app/phpunit.xml

.......................................................           55 / 55 (100%)

Time: 00:00.208, Memory: 10.00 MB

OK (55 tests, 10101 assertions)
```

（另有 Composer 提示 "could not detect the root package ... defaulting to '1.0.0'"，属容器内无 git 元数据的正常噪音，不影响结果。）

### 2. composer analyse

命令原文：

```bash
docker run --rm -v "$PWD":/app -w /app \
  registry.cn-shenzhen.aliyuncs.com/yansongda/php:cli-8.3-alpine \
  sh -c "COMPOSER_ALLOW_SUPERUSER=1 composer analyse"
```

退出码：**0**

输出摘要：

```
 9/9 [▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓] 100%

 [OK] No errors
```

（phpstan level 6 ./src，9 个文件全部通过。）

### 3. composer cs-fix

命令原文：

```bash
docker run --rm -v "$PWD":/app -w /app \
  registry.cn-shenzhen.aliyuncs.com/yansongda/php:cli-8.3-alpine \
  sh -c "COMPOSER_ALLOW_SUPERUSER=1 composer cs-fix"
```

退出码：**0**

输出摘要：

```
PHP CS Fixer 3.95.24 Adalbertus by Fabien Potencier, Dariusz Ruminski and contributors.
PHP runtime: 8.3.30
Loaded config default from "/app/.php-cs-fixer.php".
Running analysis on 7 cores with 10 files per process.
 ...
 9/9 [▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓] 100%

Found 0 of 9 files that can be fixed in 1.928 seconds, 10.00 MB memory used
```

（cs-fix 为 --dry-run 仅检查模式；0 个文件待修复。）

附带提示（警告，不影响退出码）：

```
You are running PHP CS Fixer on PHP 8.3.30, but the minimum PHP version supported
by your project in composer.json is PHP 8.2. ...
```

## 结论

**Zend 基线全绿**：

| 命令 | 退出码 | 关键结果 |
| --- | --- | --- |
| composer test | 0 | OK (55 tests, 10101 assertions) |
| composer analyse | 0 | No errors（9/9 文件，phpstan level 6） |
| composer cs-fix | 0 | Found 0 of 9 files that can be fixed |

闸门条件满足，Wave 0 可继续。

## 偏差记录

- 无方案性偏差。机械性偏差：无（三条命令一次通过，未触发 composer install / DNS fix）。
- 仅新增本 evidence 文件，未修改任何源码/配置。

# 2026-09-06 15:26 main agent 亲自验证（T0.3）

1. git 层面：`git show --stat HEAD` → commit `86378bd chore(spike): Zend 基线记录`，仅含 evidence 1 个文件（119 行新增），无越界；`git status --porcelain` 仅有预期未跟踪目录（docs/implementation/、docs/learning/ 待 T0.1 补 gitignore；docs/typephp-native-optimization.md 为既有本地文件）。
2. 命令复跑（Docker cli-8.3-alpine，亲自执行）：
   - `composer test` → `OK (55 tests, 10101 assertions)`，EXIT=0
   - `composer analyse` → `[OK] No errors`
   - `composer cs-fix` → `Found 0 of 9 files that can be fixed`
3. 结论：与 worker 报告及 evidence 记录一致，**验证通过**，T0.3 勾选 [x]。
