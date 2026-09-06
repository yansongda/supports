# 2026-09-06 15:45:43 — T0.4 阶段 1（nt.php + Zend 侧实测定论 + workflow CI 步骤，待 tpc 回传）

## 本阶段完成事项

1. 创建 `spike-tmp/nt/nt.php`（语句与 todo 原文逐句一致，仅排版多行化）。
2. Zend 侧本地 Docker 实测完成（结论已定，见下）。
3. `.github/workflows/spike-typephp.yml` 追加 `Spike native_types` 步骤组。
4. 临时 commit：`8587cd1 chore(spike): use native_types 双运行时行为快照(spike-tmp 临时入库,闸门后删除)`，仅含 `spike-tmp/nt/nt.php` + workflow 两文件（**注明：spike-tmp/ 临时入库系 CI 交互协议所需**，闸门后删除）。evidence 本阶段不 commit。

## NATIVE_TYPES.md 文档依据（Binary Operation Type Promotion Rules 节）

- **Rule 2 原文 Note**："native-type variables **do not change their own type** during operations. For example, `Int += Float` executes `int64_t += double` in C++, and the result is **truncated to int64_t**, which differs from PHP behavior (in PHP the variable becomes float). This is intentional semantics of `use native_types`."
- Rule 2 示例：`use native_types;` 顶层声明后 `$a = 10` 为 `php::Int`、`$b = 2.5` 为 `php::Float`；`$a + $b` → Float + Float → double 加法（但赋回 Int 变量时按 Note 截断）。Rule 1：任一侧为 Var（未 use native_types）→ ZendVM 全套 juggling 语义。
- 推广矩阵：Int × Float → **Float**（表达式结果类型），而 `Int += Float` 复合赋值按 Note 截断回 Int——本 spike 用 `$a += 2.5` 直击 Note 描述的截断行为。
- 完整矩阵中 Int×Int → Int（对应 `$b += 1` 基线不变项）。

## spike-tmp/nt/nt.php 内容

```php
<?php

declare(strict_types=1);

use native_types;

function main(): void
{
    $a = 10;
    $a += 2.5;
    echo $a, PHP_EOL;
    $b = strlen('abc');
    $b += 1;
    echo $b, PHP_EOL;
}
```

## Zend 侧实测（本地 Docker，已定论）

- 环境：`registry.cn-shenzhen.aliyuncs.com/yansongda/php:cli-8.3-alpine`（PHP 8.3.30 NTS，Docker 模板见 learning）。
- 命令：`docker run --rm -v "$PWD":/app -w /app <镜像> sh -c "php -l spike-tmp/nt/nt.php && php -r 'require \"spike-tmp/nt/nt.php\"; main();'"`
- 实际输出：
  ```
  Warning: The use statement with non-compound name 'native_types' has no effect in spike-tmp/nt/nt.php on line 5
  No syntax errors detected in spike-tmp/nt/nt.php

  Warning: The use statement with non-compound name 'native_types' has no effect in /app/spike-tmp/nt/nt.php on line 5
  12.5
  4
  ```
- 退出码：**0**。
- **结论（Zend no-op 成立）**：输出 `12.5`（int+=float → float 提升）与 `4`，符合预期；`use native_types;` 在 Zend 下为 no-op 且仅产生 **Warning（非 fatal）** → 不触发 QA failure 预案（"不采用 use native_types"），spike 继续走 typephp 验证。
- ⚠️ 记录：该 Warning 在 Zend 8.3 下必然出现（`use <非复合名>` 的 PHP 语言层行为）；T3.2 全量冒烟若在 Zend 侧运行含 `use native_types` 的文件需容忍该 Warning（提示性，退出码不受影响）。
- 说明：文件作用域隔离（`use native_types` 只影响当前文件、不泄漏到 include 的无声明文件）无法由本单文件 spike 证明，T3.2 以全量冒烟为准。

## workflow 步骤设计（插入 list dry build artifacts 之后、Spike Summary 之前）

1. `Spike native_types (compile single file)`（continue-on-error: true）：
   ```bash
   TPC_BIN="$(find "$TPC_DIST" -type f -name tpc | head -n1)"
   mkdir -p build/nt
   "$TPC_BIN" spike-tmp/nt/nt.php -o build/nt/nt 2>&1 | tee "$SPIKE_DIR/nt-compile.txt"
   # 退出码 → $SPIKE_DIR/nt-compile-exit-code.txt
   ```
   依据：COMPILER_CLI.md Common Examples 首例即单文件输入 `bin/tpc.php app.php`，`-o, --output <file>` 为输出文件名 → **单文件编译无需 nt/project.yml**（机械性结论，记录 learning）。
2. `Spike native_types (run compiled binary)`（continue-on-error: true）：`./build/nt/nt 2>&1 | tee "$SPIKE_DIR/nt-typephp.txt"`，退出码 → `nt-typephp-exit-code.txt`。若编译步骤失败则运行步骤以 127 退出码留下诊断。
3. 预期输出（typephp 侧，待 CI 实证）：`12`（Int += Float 截断）与 `4`。
4. 输出文件全入 `$SPIKE_DIR/`，自动随既有 `Upload Spike Evidence` artifact 上传。

## 待回传项清单（阶段 2 依据）

1. `nt-compile.txt`（tpc 单文件编译输出）+ `nt-compile-exit-code.txt`。
2. `nt-typephp.txt`（二进制运行输出，**期望两行 `12`、`4`**；若为 `12.5`/`4` 则语义差异不成立，evidence 如实记录并上报）。
3. `nt-typephp-exit-code.txt`（期望 0）。
4. 附带确认：`-o build/nt/nt` 相对路径基准（若二进制不在 `$GITHUB_WORKSPACE/build/nt/nt`，从 run log/诊断定位实际路径，机械性调整）。

## Acceptance 状态

- [x] nt.php 创建且 Zend 侧 Docker 实测完成（12.5/4，退出码 0，非 fatal）
- [x] workflow 步骤追加，YAML 自检通过
- [x] 临时 commit `8587cd1`（仅 spike-tmp/nt/ 与 workflow）
- [x] evidence 阶段 1 小节 + learning 追加
- [ ] （待 CI）tpc 侧输出 12/4 + 退出码 → 阶段 2 定稿结论

# 2026-09-06 15:55 main agent 亲自验证（T0.4 阶段 1）

1. Zend 侧复跑（Docker cli-8.3-alpine，`php -r 'require ...; main();'`）：输出 `12.5`、`4` 两行，退出码 0，与 worker 报告一致；`use native_types;` 触发 `Warning: The use statement with non-compound name 'native_types' has no effect`（stderr 噪音，非 fatal）。
2. **T3.2 前瞻结论（main agent 补充）**：已核查 phpunit.xml——未配置 failOnWarning/failOnPhpunitWarning（PHPUnit 11 默认 false），故 src/Str.php 若加 `use native_types;`，其 Zend 侧 Warning 不会导致 `composer test` 失败，仅产生输出噪音。T3.2 决策时此结论可引用。
3. `spike-tmp/nt/nt.php` 内容与 todo 原文逐句一致；workflow 新增两步（compile/run，均 continue-on-error，输出双文件入 $SPIKE_DIR）位置正确；YAML 自检复跑 OK。
4. commit `8587cd1` 仅 2 文件（spike-tmp/nt/nt.php + workflow）。
5. 结论：**阶段 1（Zend 侧）验证通过**；tpc 侧（预期 12/4）待 CI 回传后阶段 2 定稿。

# 2026-09-06 22:04:39 — 阶段 2：双运行时结论（定稿）

## CI 回传结果（run 34037668106，PR #40，conclusion=success）

- **tpc 侧**（artifact `nt-typephp.txt`）：实际输出两行 **`12`**、**`4`**；`nt-typephp-exit-code.txt` = `nt binary exit code: 0`；编译步骤 `nt-compile-exit-code.txt` = `tpc nt.php compile exit code: 0`（Build successful，g++ 链接 -lphpx -lphp -lgmp -lgmpxx -lmpfr）。
- **Zend 侧**（阶段 1 已录）：输出 **`12.5`**、**`4`**，退出码 0（伴随 `use native_types` 的 Warning，非 fatal）。

## 定稿结论

1. **`use native_types` 在 Zend 下为 Warning 级 no-op 成立**：输出语义不变（int+=float → float 提升，12.5），退出码 0，仅产生 `The use statement with non-compound name 'native_types' has no effect` 提示性 Warning → 不触发 QA failure 预案。
2. **`Int += Float` 截断语义差异在 typephp 下真实存在**：tpc 编译产物输出 `12`（10+2.5 → 截断回 int64_t），与 Zend 的 `12.5` 构成真实语义差异，与 NATIVE_TYPES.md Note（"intentional semantics"）完全一致。
3. **T3.2 决策链状态**：前两个条件均已满足——① Zend no-op 成立 ✓；② tpc 编译运行通过 ✓。第三条件（benchmark 收益数据）由 T3.1 提供。**注意**：截断语义差异本身即在"采用 native_types 可改变行为"的风险清单内（Str/Arr 热点代码若存在 `$int += $float` 形态将产生行为变化），T3.2 决策时须同时权衡收益（T3.1）与该语义差异的审计结果。
4. **文件作用域隔离**按 plan 说明留待 T3.2 全量冒烟验证（本单文件 spike 无法证明 `use native_types` 不泄漏到 include 的无声明文件）。

## Acceptance 状态（T0.4 定稿）

- [x] nt.php 创建且 Zend 侧 Docker 实测完成（12.5/4，退出码 0，非 fatal）
- [x] workflow 步骤追加，YAML 自检通过
- [x] 临时 commit `8587cd1`（仅 spike-tmp/nt/ 与 workflow）
- [x] evidence 阶段 1 小节 + learning 追加
- [x] （CI 回传）tpc 侧输出 12/4 + 退出码 0 → 阶段 2 定稿结论（本节）
