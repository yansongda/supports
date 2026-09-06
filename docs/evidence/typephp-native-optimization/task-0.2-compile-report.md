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

# 2026-09-06 22:09 vendor 缺失修复（T0.2 tpc --dry CI 失败，worker）

- **CI 报错原文**（run `34037668106`，`Spike Compile src/ (tpc --dry, collect error list)` 步骤，exit 255）：
  ```
  PHP Fatal error: Uncaught InvalidArgumentException: Directory does not exist: /home/runner/work/supports/supports/vendor/psr/container/src
  ```
- **根因**：`vendor/` 与 `composer.lock` 均被 .gitignore 忽略，CI checkout 后项目根不存在 vendor/；而 `spike-tmp/project.yml` 的 sources 引用 `../vendor/psr/container/src`（相对 spike-tmp/ 即项目根 vendor/），tpc 解析该目录时直接 Fatal error。本地可跑是因为本地有 vendor/，属环境差异而非 project.yml 写错。
- **修复**：workflow 在 `Configure Native Library Path (LD_LIBRARY_PATH)` 之后、`Inspect tpc Dynamic Library Requirements (after provisioning)` 之前插入步骤 `Install Project Composer Dependencies (for spike-tmp sources)`：
  ```yaml
  - name: Install Project Composer Dependencies (for spike-tmp sources)
    run: |
      COMPOSER_ALLOW_SUPERUSER=1 composer install --no-interaction --no-progress --no-audit --no-scripts
      test -f vendor/autoload.php
      test -d vendor/psr/container/src
  ```
  即先 `composer install`（`--no-scripts` 防止 vendor 内脚本干扰，项目 composer.json 第 19 行已声明 `psr/container` 依赖，install 后必然产生 `vendor/psr/container/src`），再断言 `vendor/autoload.php` 文件与 `vendor/psr/container/src` 目录存在，断言通过后才进入后续 tpc 调用。插入位置位于所有 Spike Compile/native_types/boundary 组之前，不影响 libphpx/libphp 预置组。commit `0b612f7`（仅 workflow 文件），YAML safe_load 自检通过。

# 2026-09-06 22:15:20 --no-audit 兼容性修复（T0.2 composer install 步骤 CI 失败，worker）

- **CI 报错原文**（run `34038440997`，`Install Project Composer Dependencies (for spike-tmp sources)` 步骤）：
  ```
  ##[error]The "--no-audit" option does not exist.
  ```
- **根因**：ubuntu-22.04 runner 环境中实际生效的 composer 版本不支持 `composer install --no-audit` 选项，命令直接报错退出，vendor 未安装。
- **修复**：该步骤命令移除 ` --no-audit`，即
  ```yaml
  COMPOSER_ALLOW_SUPERUSER=1 composer install --no-interaction --no-progress --no-scripts
  ```
  其余选项不变，步骤内两行断言（`test -f vendor/autoload.php`、`test -d vendor/psr/container/src`）不动。commit `1951c7f`（仅 workflow 文件），YAML safe_load 自检通过。

# 2026-09-06 22:21:03 阶段 2：编译报错清单定稿（run 34038640605）

## CI 运行事实

- **run**：`34038640605`（spike-typephp.yml，PR #40），URL：https://github.com/yansongda/supports/actions/runs/34038640605 ，**conclusion = success**。
- `tpc --dry` 退出码 **0**（`tpc-dry-exit-code.txt`：`tpc --dry exit code: 0`）。
- **13 个源文件 prepare 完成**（`prepare completed: 13 source files in total`）：vendor/psr/container/src 3 个（ContainerExceptionInterface / ContainerInterface / NotFoundExceptionInterface）+ src/ 9 个（Arr / Collection / Config / Functions / Pipeline / Str / Traits/Accessable / Traits/Arrayable / Traits/Serializable）+ spike-tmp/main-stub.php；逐文件 convert + generate arginfo 全部无 fatal。
- **7 个 C++ 文件生成**（`Dry run completed: 7 C++ source file(s) generated in /home/runner/work/supports/supports/build/spike`）。
- 前置提示行（非致命）：`libphp.so is missing... or set PHP_HOME` 等若干提示——`--dry` 仅生成 C++ 不链接，未阻塞，exit 0，符合预期。
- 全量输出仅两处 WARNING（无 fatal/error）：
  - `Use this expression carefully, which may be inconsistent with the dynamic execution behavior in /home/runner/work/supports/supports/src/Collection.php:103`
  - `Use this expression carefully, which may be inconsistent with the dynamic execution behavior in /home/runner/work/supports/supports/src/Pipeline.php:39`
- 同 run 复核项（回归确认）：native_types 输出 `12`/`4`（nt-typephp.txt，与 T0.4 定稿一致）；boundary diff exit 1 且唯一差异仍为 PAT_FOREACH_REF（与 T0.5 定稿一致）。

## A/B 归类表

| # | 归类 | 位置 | plan 预判项 | 实测结果 |
|---|------|------|-------------|----------|
| 1 | **A 类（plan 预判项）** | `src/Collection.php:103`（except + func_get_args） | func_get_args 预期报错/告警 | **WARNING（`Use this expression carefully...` 形态）非 fatal**，convert/arginfo 正常完成 |
| 2 | **A 类（plan 预判项）** | `src/Pipeline.php:39`（through + func_get_args） | 同上 | **同上 WARNING 非 fatal**，convert/arginfo 正常完成 |
| 3 | **A 类（plan 预判项）** | `src/Collection.php:305`（toString 方法定义） | 关键字方法拦截待实测 | **零诊断**（方法定义编译层无任何报错/告警） |
| 4 | **A 类（plan 预判项）** | vendor/psr/container 三个接口文件（strict_types=1） | 文档预期不报错 | **零报错**（13 文件 prepare 全通过，契约 #6「strict_types=1 为允许值」实测通过） |
| — | **B 类（未知新项）** | — | — | **零**（全量输出中无任何 A 类之外的报错/告警） |

## 两条显式结论（plan 要求）

1. **`Arr::toString` 静态调用未被关键字拦截**：`src/Arr.php:489` toString（静态、带参）在编译层无任何诊断——"关键字方法硬冲突"预案不成立。结合第 3 行实测（`Collection.php:305` toString 方法定义零诊断），关键字解析语义（"Reserved keyword methods ... are resolved before ordinary object methods"）**发生在调用点/运行期而非类定义编译**，带参定义本身不触发编译期冲突。
2. **`toArray()` declared 豁免在定义编译层无诊断**：`Collection.php` 整文件 convert + arginfo 无任何针对 toArray 的诊断（声明豁免/关键字解析均未在定义编译层产生拦截）；**调用期豁免效果留待 T2.1 运行期冒烟确认**（`$collection->toArray()` 实际分发到用户方法还是 builtin）。

## 重要解读（对 Wave 1 的意义）

关键字方法拦截发生在**调用点/运行期**而非类定义编译；func_get_args 在 typephp 运行期的严格参数计数下仍会出问题（契约 #2，两处 WARNING 即编译器对该表达式的显式提示）。因此：

- **Wave 1 的 toString 更名与两处变参化（except/through）仍然必要**——保障**运行期正确性**（避免调用点被 builtin 语义截胡、func_get_args 严格计数错位）；
- 但风险等级从"硬冲突"（编译不过）**下调为"运行期语义风险"**（编译层 exit 0 全绿，问题只会在运行期冒烟暴露）；
- T2.1 冒烟必须重点覆盖：`Arr::toString` 静态调用、`Collection::except(...)` 多参、`Pipeline::through(callable)` 实际分发行为、`Collection::toArray()` 调用点。

## Artifact 归档

- 26 个诊断文件归档入库：`docs/evidence/typephp-native-optimization/artifacts/run-34038640605/`（含 `tpc-dry-compile.txt` 全量输出、`tpc-dry-exit-code.txt`、`tpc-dry-build-dir.txt`、`spike-summary.txt` 及 T0.1/T0.4/T0.5 复核文件）。

# 2026-09-06 22:25 main agent 闸门核验（T0.2）

1. 亲自核对 run 34038640605 的 tpc-dry-compile.txt 全文：exit 0、13 文件 prepare、仅 Collection.php:103 与 Pipeline.php:39 两处 warningUndefinedBehavior 形态 WARNING、toString 零诊断、psr 三接口零报错、7 个 C++ 文件生成。
2. A/B 归类表与两条结论复核无误；B 类为零；预案（Arr::toString 被拦截）不触发。
3. 结论：**T0.2 验收通过，勾选 [x]**。Wave 1 改造必要性维持（运行期调用语义），风险等级下调已记录。
