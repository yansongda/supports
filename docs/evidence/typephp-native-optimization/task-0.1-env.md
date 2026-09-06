# 2026-09-06 15:23:39 — T0.1 阶段 1（workflow 入库，待 CI 回传）

## 本阶段完成事项

1. 创建 `.github/workflows/spike-typephp.yml`（workflow_dispatch 手动触发，单 job `spike`，runs-on ubuntu-latest）。
2. `.gitignore` 末尾追加三行：`docs/implementation/`、`docs/evidence/`、`docs/learning/`（原内容未动）。
3. 阶段 1 commit：`chore(spike): typephp CI 环境准备 workflow`，仅含上述 2 个文件；evidence 本阶段不 commit（决议见 learning「T0.1 两段式 commit 决议」）。
4. YAML 语法自检通过：`python3 -c "import yaml;yaml.safe_load(open('.github/workflows/spike-typephp.yml'))"` → 输出正常无异常。

## tpc release 调研结论（阶段 1 固定版本）

- **固定版本：`v0.7.0`**（TypePHP v0.7.0，非 prerelease，published 2026-09-04T07:57:16Z，为调研时 GitHub Releases 最新版）。
- **linux-x64 资产名：`tpc_v0.7.0_linux_x64.tar.gz`**。
- **完整下载 URL：`https://github.com/swoole/typephp/releases/download/v0.7.0/tpc_v0.7.0_linux_x64.tar.gz`**。
- 校验文件：`https://github.com/swoole/typephp/releases/download/v0.7.0/SHA256SUMS`（workflow 中 `sha256sum -c` 校验后解压）。
- 资产命名规律：v0.6.5 及更早为 `tpc_v0.6.5_linux_x86_64.tar.gz`（且无 macos/macos 无 arm64 之外的变体），**v0.6.6 起改为 `_x64`/`_arm64` 短后缀**；v0.6.6+ 每版均含 `SHA256SUMS` + linux_x64/linux_arm64/macos_arm64/windows_x64 四资产。
- 调研来源：GitHub API `https://api.github.com/repos/swoole/typephp/releases?per_page=5`（实测返回 v0.7.0 / v0.6.8 / v0.6.6 / v0.6.5 资产清单），与本地文档 `/tmp/typephp-docs/docs/en/`（COMPILER_CLI.md、LIBPHP_INSTALLER.md、QUICKSTART.md）交叉确认。

## 本地文档关键结论（LIBPHP_INSTALLER.md，T0.1 关键产出）

- bootstrap 二进制 `tpc` 必须在进入 `main()` 前由系统动态链接器加载 **`libphp.so` 与 `libphpx.so`**；它自己无法安装缺失库。
- 自动安装器（构建 PHP Embed `libphp.so` / `libphpx.so`）**仅在 Linux + 交互终端启用；CI 等非交互环境不会自动下载安装，也不会执行 sudo**。
- CI 替代方案（文档明确支持）：**提前准备好 `libphp.so`，然后 `export PHP_HOME=/path/to/php`**；libphpx.so 的查找顺序为 `PHPX_HOME` → Composer `InstalledVersions`(swoole/phpx) → TypePHP 源仓库 `vendor/swoole/phpx`。
- `tpc --help` 若因缺 `libphp.so`/`libphpx.so` 直接失败（ldd 显示 not found，退出码非 0），属预期 spike 观察项；workflow 已加 `ldd` 检查 step 与 `continue-on-error`，保证诊断信息进 artifact。
- 注意：QUICKSTART.md 中"Prerequisites/Requirements"描述的是**源码构建**路径（php bin/tpc.php 时代，PHP 8.0+、GCC 9+），与 release 二进制包的运行要求（PHP 8.4+ 运行时、libphp/libphpx）不同源；以 COMPILER_CLI.md + LIBPHP_INSTALLER.md 为准。本地 `/tmp/typephp-docs/docs/en/README.md` 为编译器内部文档目录索引，无 Requirements/Download 节（机械性偏差，已按文档实际内容核对）。

## workflow 设计要点

- 版本与 URL 集中定义在 job `env`：`TPC_VERSION=v0.7.0`、`TPC_ASSET=tpc_v0.7.0_linux_x64.tar.gz`、`TPC_URL=<完整 URL>`、`TPC_DIST=/tmp/typephp-dist`、`SPIKE_DIR=/tmp/typephp-spike`（便于 T0.2/T0.4/T0.5 追加步骤时复用）。
- 步骤顺序（后续 todo 在末尾追加步骤）：①checkout（沿用仓库习惯 `actions/checkout@v7`）②setup-php 8.5 + `phpts: ts`（`shivammathur/setup-php@v2`）③ZTS 首次验证（`PHP_ZTS` 显式断言，continue-on-error）→ NTS 时条件触发 `update: true` 重装 → 最终 ZTS 验证（必须通过，否则 `::error::PHP is NTS...`）④apt 安装 `libgmp-dev libmpfr-dev cmake` 并记录 dpkg 版本 ⑤cmake ≥ 3.24 断言（`sort -V` 比较，不足即 fail 并标 error）⑥下载 + SHA256SUMS 校验 + 解压 + 列出包内文件 ⑦`ldd` 检查 tpc 缺失动态库 ⑧`tpc --help`（退出码记录到文件，continue-on-error，缺 libphp 时不阻塞 artifact 上传）⑨summary ⑩`actions/upload-artifact@v4` 上传 `$SPIKE_DIR/` 全部诊断文件（`if: always()`，`if-no-files-found: error`）。
- 无 secrets/token：公开仓库 release 直接 curl 下载。
- 预期 artifact 名：`spike-typephp-evidence`，内含 `php-zts.txt`、`system-deps.txt`、`tpc-download.txt`、`tpc-ldd.txt`、`tpc-help.txt`、`tpc-help-exit-code.txt`、`spike-summary.txt` 等。
- cmake 版本预期：ubuntu-latest（24.04 noble）apt cmake 为 3.28.x ≥ 3.24，满足；若 ubuntu-latest 镜像降级会显式 fail，由 run log 可见。

## 待回传项清单（用户 push + workflow_dispatch 后，main agent resume worker 做阶段 2）

1. CI run URL（GitHub Actions → spike-typephp → run 页面）。
2. ZTS 验证输出：`php-zts.txt`（或 run log 中 "Verify PHP ZTS (final, must pass)" 步骤输出），需含 `php -v` 版本行与 `PHP_ZTS=1`。
3. 依赖版本：`system-deps.txt`（libgmp-dev/libmpfr-dev Version 行 + cmake --version），确认 cmake ≥ 3.24 实际值。
4. tpc 下载与解压：`tpc-download.txt`（SHA256 校验结果、包内文件清单）。
5. `tpc --help` 运行记录：`tpc-help.txt` + `tpc-help-exit-code.txt`（验收要求退出码 0 的运行记录；若非 0 且 ldd 显示缺 libphp.so/libphpx.so，则按 LIBPHP_INSTALLER.md 结论记录"CI 非交互不可自动安装，需 PHP_HOME 预置"并设计后续手动步骤）。
6. artifact `spike-typephp-evidence` 可直接下载解压后回贴，或逐文件回贴。

## Acceptance 状态

- [x] spike-typephp.yml 创建，YAML 语法自检通过
- [x] .gitignore 追加三行，原内容未动
- [ ] （待 CI）tpc 版本号记录 + `tpc --help` 退出码 0 运行记录 → 阶段 2 凭回传结果定稿 evidence 并单独 commit

# 2026-09-06 15:33:22 — T0.1 阶段 1 补强（libphp/libphpx 预置步骤组）

## 背景

main agent 内容审查发现：release 包不含 libphp/libphpx（v0.7.0 资产仅 4 平台 tpc 二进制 + SHA256SUMS），而 `tpc` 是 DT_NEEDED 级依赖 `libphp.so` + `libphpx.so`（LIBPHP_INSTALLER.md 原文），CI 非交互绝不自动安装 → 原 workflow 的 `tpc --help` 必然失败。本节记录补强方案与调研结论。新 commit：`ea82687 chore(spike): spike workflow 预置 libphp/libphpx 构建步骤`（仅 workflow 文件，不 amend 前一 commit）。

## 官方 CI 借鉴结论（照抄优先级最高，未自行发明）

抓取 `https://raw.githubusercontent.com/swoole/typephp/HEAD/.github/workflows/linux-x64.yml`（502 行，官方构建 linux-x64 tpc 的 workflow，runs-on ubuntu-22.04，matrix php 8.4/8.5 全 ZTS）：

1. **libphp.so 不需要源码构建 PHP**：官方直接 `shivammathur/setup-php@v2`（`phpts: ts` + env `update: true`），其 ZTS 包自带 Embed 库，`"Configure ZTS PHP embed library"` 步骤仅做 `php_home="$(php-config --prefix)"` + `test -f "${php_home}/lib/libphp.so"` + 写 `GITHUB_ENV`（`PHP_HOME`）。→ 本补强照抄：**不下载 php.net 源码、不 ./configure/make**（补强任务第 2 点的"自建构建 PHP 8.5.x"分支不触发，官方路径更优且是 release 二进制同源路径）。机械性偏差记录：PHP_HOME 不静态写死在 job env（依赖 setup-php 安装路径，官方即运行时探测 GITHUB_ENV），PHPX_HOME 静态定义于 job env。
2. **php_hash.h C++ 兼容补丁必须打**（复合 action `.github/actions/patch-php-headers`，临时方案，上游 php/php-src#22935 → #22940）：`php-config --include-dir` 下 `ext/hash/php_hash.h` 若含 `char *base = ecalloc(` 则 `patch --directory=<include-dir> --strip=1 < patches/php-hash-cxx.patch`。patch 文件已核实存在于 typephp **v0.7.0 tag**（HTTP 200，内容即 ecalloc 强转 `(char *)`），固定 URL `https://raw.githubusercontent.com/swoole/typephp/v0.7.0/.github/patches/php-hash-cxx.patch`（判定逻辑与官方逐字一致）。
3. **libphpx.so 构建命令**（官方逐字照抄）：checkout `swoole/phpx` **`ref: master`** 至 `third_party/phpx`，`PHPX_HOME` 指向该目录，然后
   `cmake -S "$PHPX_HOME" -B "$PHPX_HOME/build" -D CMAKE_BUILD_TYPE=Release -D BUILD_TESTS=OFF -D BUILD_EXT=OFF -D GITHUB_ACTION=ON -D php_dir="$PHP_HOME"`，`cmake --build ... --target phpx --parallel 2`，产出断言 `test -f "$PHPX_HOME/lib/libphpx.so"`。注意官方并行度是 **2**（非 nproc，防 runner OOM），照抄。
4. **运行 tpc 必须设 `LD_LIBRARY_PATH=${PHPX_HOME}/lib:${PHP_HOME}/lib`**（官方 "Configure native library path" 步骤，build 与 phpunit 两 job 均如此）→ 本补强经 GITHUB_ENV 导出。
5. **phpx 版本依据**：官方 CI 用 `master`（floating，不固定 tag）——照抄；旁证：typephp v0.7.0 composer.json 约束 `"swoole/phpx": "~2.7.0"`（2.7 开发线即 master）。evidence 记录依据后仍以官方行为为准。
6. 官方 apt 依赖清单：`build-essential cmake libgmp-dev libmpfr-dev pkg-config python3-dev`（比原 spike 多 build-essential/pkg-config/python3-dev）→ 已照抄替换。
7. 官方 Build tpc 用 `php bin/tpc.php project.yml`（源码自举）且启用 phpy 扩展（swoole/phpy 编译 .so + 写入 ini）；**spike 仅跑 release `tpc --help`，不引入 phpy**（无 ZEND 模块加载需求；若 ldd-after 仍报缺库再上报——属 spike 观察项）。

## workflow 步骤设计（新增/变更）

- job env 增补：`PHPX_HOME=${{ github.workspace }}/third_party/phpx`、`TYPEPHP_REF=v0.7.0`、`TYPEPHP_PATCH_URL=<patch 固定 URL>`。
- `Install System Dependencies` 改用官方清单（build-essential cmake libgmp-dev libmpfr-dev pkg-config python3-dev），dpkg 版本记录保留。
- 原 ldd 检查拆两份：`tpc-ldd-before.txt`（下载解压后、预置前，预期报 not found）与 `tpc-ldd-after.txt`（预置后，断言应无 not found，grep 'not found' 有则 `::error::`）。
- 新增预置步骤组（位于 ldd-before 与 `Run tpc --help` 之间）：Download patch → Patch php_hash.h → Configure ZTS PHP Embed Library（PHP_HOME）→ Checkout PHPX(master) → Build libphpx.so（官方 cmake 命令 + `set -o pipefail` 防 tee 吞失败码）→ Configure Native Library Path（LD_LIBRARY_PATH）。
- `Run tpc --help` 保留 continue-on-error + 退出码文件；**新增 `Assert tpc --help Exit Code 0`**：退出码文件缺失或非 0 均 `exit 1` + `::error::`（T0.1 验收硬性门禁）。
- Spike Summary 增补 PHP_HOME/PHPX_HOME/LD_LIBRARY_PATH 与 ldd-after 解析状态。
- YAML 自检通过（`python3 -c "import yaml;yaml.safe_load(...)"`）。

## 预期的额外待回传项（在原 6 项清单之上追加）

7. `php-home.txt`：PHP_HOME 路径 + `libphp.so` 存在（`test -f` 通过）+ setup-php 的 PHP 版本（应为 8.5.x ZTS）。
8. `php-hash-cxx.patch` 应用结果：run log 中 "Patch php_hash.h" 步骤输出（patched 或 already contains upstream fix）。
9. `phpx-cmake-configure.txt` / `phpx-cmake-build.txt` 尾部：cmake configure 与 `--target phpx` 构建成功证据，`phpx-build-result.txt`（`libphpx.so` 路径与 ls 输出）。
10. `tpc-ldd-before.txt`（预期含 not found，spike 基线）与 `tpc-ldd-after.txt`（**应无 not found**；若仍有，整文件回传并 STOP 上报）。
11. `Assert tpc --help Exit Code 0` 步骤绿（或失败时的完整 run log）。

## Acceptance 状态（更新）

- [x] spike-typephp.yml 创建，YAML 语法自检通过
- [x] .gitignore 追加三行，原内容未动
- [x] 阶段 1 补强 commit `ea82687`（仅 workflow 文件）
- [ ] （待 CI）tpc 版本号记录 + `tpc --help` 退出码 0 运行记录 + 预置步骤组全部成功 → 阶段 2 凭回传结果定稿 evidence 并单独 commit

# 2026-09-06 15:40 main agent 亲自验证（T0.1 阶段 1 + 补强）

1. git 层面：`git log` → `6ee4e3e`（阶段 1，workflow + .gitignore 两文件）、`ea82687`（补强，仅 workflow，105+/7-）；`git diff --cached --name-only` 空，无残留暂存；未 push。
2. workflow 内容级审查（亲自通读全文 262 行）：步骤顺序合理（ZTS 验证→apt→cmake 断言→tpc 下载校验→ldd-before→patch→PHP_HOME→phpx 构建→LD_LIBRARY_PATH→ldd-after→tpc --help→**Assert 退出码 0 硬门禁**→summary→artifact if:always()）；`set -o pipefail` 覆盖 tee 管道；patch 幂等判定与官方逐字一致。
3. 关键 URL 独立复核：release v0.7.0 linux-x64 资产（GitHub API 实测存在）、php-hash-cxx.patch@v0.7.0 tag（HTTP 200，内容为 ecalloc 强转补丁）。
4. YAML 自检复跑：`python3 -c "import yaml;yaml.safe_load(...)"` → OK。
5. 结论：**阶段 1 + 补强验证通过**。T0.1 整体验收（tpc --help 退出码 0）待用户 push + workflow_dispatch 后凭 run log 定稿（阶段 2），todo 暂不勾选。
