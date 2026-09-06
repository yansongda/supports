# 2026-09-06 15:40:57 — T0.2 阶段 1（project.yml + workflow 编译步骤，待 CI 回传）

## 本阶段完成事项

1. 创建 `spike-tmp/project.yml` 与 `spike-tmp/main-stub.php`。
2. `.github/workflows/spike-typephp.yml` 追加步骤组（`Spike Compile src/` 前缀）。
3. 临时 commit：`a31ed19 chore(spike): src 全量编译报错快照(spike-tmp 临时入库,闸门后删除)`，仅含上述 3 文件。**注明：spike-tmp/ 临时入库系 CI 交互协议所需**（CI 检出仓库才能编译），Wave 0 闸门通过后按 learning 决议整目录 `git rm -r` 删除。
4. evidence 本阶段不 commit（CI 结果回传后阶段 2 归档原始输出/归类表/两条结论再单独 commit）。

## COMPILER_CLI.md 实测要点（与 plan 写法的机械性偏差）

- CLI 用法：`tpc <file|dir|project.yml> [options]`；`--dry`＝"Only generate C++, without compiling and linking"；`--build-dir <dir>`＝"Directory for generated C++ and intermediate artifacts"（官方示例 `--dry --build-dir /tmp/typephp-build`）。plan 的参数名全部与文档一致，无偏差。
- **project.yml schema 字段名偏差（机械性，已修正）**：plan 写 `mode: bin`；**官方 v0.7.0 仓库根 project.yml 实测为 `build-mode: bin`**（`https://raw.githubusercontent.com/swoole/typephp/v0.7.0/project.yml`）。两种写法在文档中并存（WASI_BUILD.md 示例用 `mode:`），但官方 release 构建自用的仓库根 project.yml 与我们的 v0.7.0 CLI 完全同代，**采用 `build-mode: bin`**。COMPILER_CLI.md 本身不含完整 schema（"see the user documentation and the project configuration parser in the code"），故以官方 project.yml 实物为 schema 依据：实测字段 `name` / `build-mode` / `version` / `cxx-std` / `cxx-flags` / `resource` / `sources`（目录与单文件混排均可，如 `./src` 与 `vendor/.../NodeVisitor.php`）/ `ignore`。
- **sources 相对路径基准 = project.yml 所在目录**：官方 project.yml 同文件内 resource 字段注释明确"相对于 project.yml 所在目录"；WASI_BUILD.md 明确 "`build-dir`, `output` ... are all resolved relative to the project file"。故 plan 的 `../vendor/psr/container/src`、`../src`（相对 spike-tmp/）写法**正确保留**；`main-stub.php` 同目录直接写文件名。
- `strict_types` 预期（A 类 psr 接口项的文档侧对照）：INCOMPATIBLE_PHP_FEATURES.md declare 节 "`declare(strict_types=...)` accepts only `strict_types=1`" → psr/container 接口文件均为 `declare(strict_types=1)`，**文档预期不报错**，待 CI 实证。

## spike-tmp/project.yml 最终形态

```yaml
name: spike
build-mode: bin
sources:
  - ../vendor/psr/container/src
  - ../src
  - main-stub.php
```

main-stub.php：`<?php declare(strict_types=1); function main(): void {}`（合规：Binary mode must define a global main()，无参、返回 void——INCOMPATIBLE_PHP_FEATURES.md Program structure 节）。

## workflow 追加步骤设计（插入 Assert tpc --help 之后、Spike Summary 之前）

1. `Spike Compile src/ (tpc --dry, collect error list)`（**continue-on-error: true**——收集报错清单是目的，tpc 非零退出属预期信息不得阻断 job）：
   ```bash
   TPC_BIN="$(find "$TPC_DIST" -type f -name tpc | head -n1)"
   set -o pipefail
   set +e
   "$TPC_BIN" spike-tmp/project.yml --dry --build-dir build/spike 2>&1 \
     | tee "$SPIKE_DIR/tpc-dry-compile.txt"
   TPC_DRY_EXIT=$?
   set -e
   echo "tpc --dry exit code: $TPC_DRY_EXIT" > "$SPIKE_DIR/tpc-dry-exit-code.txt"
   ```
   stdout+stderr 合并 `tee` 进 artifact；显式 `set -o pipefail`（GH Actions bash 默认虽带 pipefail，显式声明防歧义）保证退出码来自 tpc 而非 tee。
2. `Spike Compile src/ (list dry build artifacts)`（`if: always()`）：`--build-dir` 相对基准存在歧义（相对 CWD 还是 project.yml，文档未明说），故同时扫描 `build/spike` 与 `spike-tmp/build/spike` 两个候选路径列产物清单，写入 `tpc-dry-build-dir.txt`。
3. 运行前提：`PHP_HOME`/`PHPX_HOME`/`LD_LIBRARY_PATH` 已由 T0.1 预置步骤经 GITHUB_ENV 导出，直接可用；输出文件均在 `$SPIKE_DIR/`，自动随现有 `Upload Spike Evidence` artifact 上传，未新增 upload 步骤。
4. 预期新增 artifact 文件：`tpc-dry-compile.txt`（tpc 全量输出）、`tpc-dry-exit-code.txt`、`tpc-dry-build-dir.txt`。

## 待回传项清单（阶段 2 依据）

1. `tpc-dry-compile.txt` 全文（原始输出，归类基准）。
2. `tpc-dry-exit-code.txt`（tpc 退出码；**若为崩溃/core dump 类失败，按 QA failure 场景保存完整 stderr 并 STOP**）。
3. `tpc-dry-build-dir.txt`（--dry 产物清单，确认 --build-dir 实际基准）。
4. CI 后填写：A/B 归类表（A=已知 3 项 toString / func_get_args×2 + psr 接口 strict_types 项；B=未知新项）；两条显式结论（`Arr::toString` 静态调用是否被关键字拦截；`Collection::toArray()` declared 豁免是否生效）。
5. 文档侧预判（待实证）：strict_types=1 为文档允许值预期不报错；func_get_args 严格参数计数语义预期在 except()/through() 处报错或告警；`toString`/`toArray` 关键字解析规则（"an application method of the same name **that takes arguments** is not called"）→ 无参 `toArray()` 与静态带参 `Arr::toString()` 均为契约未实测点，以 CI 输出为准。

## Acceptance 状态

- [x] spike-tmp 两文件 + workflow 追加步骤完成，YAML 自检通过（workflow 与 project.yml 均通过 python yaml.safe_load）
- [x] 临时 commit `a31ed19`（仅 3 个文件）
- [x] evidence 阶段 1 小节 + learning 追加
- [ ] （待 CI）A/B 归类表 + 两条显式结论 → 阶段 2

# 2026-09-06 15:47 main agent 亲自验证（T0.2 阶段 1）

1. `spike-tmp/project.yml` 内容审查：`build-mode: bin` 字段名已由 main agent 独立抓取官方 v0.7.0 仓库根 project.yml（raw.githubusercontent.com）交叉确认，worker 的机械性偏差修正正确；sources 相对基准（project.yml 所在目录）依据官方 resource 注释与 WASI_BUILD.md，`../` 写法成立。
2. `spike-tmp/main-stub.php`：仅 declare + main() 定义，零顶层可执行语句 ✓。
3. workflow 追加步骤审查：位置正确（Assert tpc --help 之后、Spike Summary 之前）；continue-on-error + pipefail + 退出码捕获齐全；双候选 build-dir 列目录兜底；输出全入 $SPIKE_DIR 随既有 artifact 上传。
4. YAML 双文件自检复跑 OK；commit `a31ed19` 仅含 3 文件（spike-tmp 临时入库已在 evidence 与 commit message 注明决议）；`git status --porcelain` 仅预期未跟踪文件。
5. 结论：**阶段 1 验证通过**。T0.2 整体验收（A/B 归类 + 两条结论）待 CI run log 回传后阶段 2 定稿。
