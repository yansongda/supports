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

# 2026-09-06 17:03 触发机制偏差修正

## 三种触发路径实测失败结论（main agent 实测）

spike-typephp.yml 原触发器仅有 `workflow_dispatch:`，且该 workflow 文件**仅存在于非默认分支** `feat/typephp-native-optimization`。三条人工触发路径全部实测失败：

1. **REST API dispatch**：`POST /repos/.../actions/workflows/spike-typephp.yml/dispatches` 返回 **404**（GitHub 按 workflow 文件路径解析时以默认分支为准，非默认分支上的 workflow 文件查不到）。
2. **GitHub CLI**：`gh workflow run spike-typephp.yml` 报错原文：**"workflow spike-typephp.yml not found on the default branch"**。
3. **Actions UI**：workflow 页面为软 404，无 Run 按钮。

结论：workflow_dispatch / UI dispatch 均要求 workflow 文件存在于**默认分支**，非默认分支上的 workflow 无法人工触发。

## 本修正说明

`.github/workflows/spike-typephp.yml` 顶部 `on:` 块追加 `pull_request:`（默认 types，不加 branches 过滤），`workflow_dispatch:` 保留不动，其余内容零改动。解法模式：main agent 开 **draft PR**，PR 创建事件自动运行 **PR 分支上的 workflow 文件**（pull_request 事件使用触发分支上的 workflow 定义，不受默认分支限制）。YAML 自检通过；commit `42ea96e`（仅该文件，+1 行）。

## 对 T3.1 的前瞻（重要，派发时必须带上）

`.github/workflows/benchmark-typephp.yml`（T3.1 基准测试）同为 `workflow_dispatch:` 触发，**届时会遇到完全相同的限制**。T3.1 必须二选一：

- 同样给 benchmark-typephp.yml 加 `pull_request:` 触发器 + draft PR 自动运行（与本次 spike 修正同模式）；
- 或届时仓库已合并进默认分支（workflow 文件在默认分支后 workflow_dispatch 恢复可用）再触发。

**T3.1 派发 prompt 必须带此结论**，避免重复踩坑。

# 2026-09-06 21:41:48 — ZTS 装配失败修复（phpts/update 改为 setup-php env 参数 + 固定 ubuntu-22.04）

## 失败 run 证据（main agent 实测诊断）

- 失败 run：PR #40 spike-typephp，run id **34036623836**，URL：https://github.com/yansongda/supports/actions/runs/34036623836 ，失败步骤 **"Verify PHP ZTS (final, must pass)"**（setup-php 装出 PHP 8.5.10 **NTS**，Thread Safety => disabled）。
- runner 原始 warning（决定性证据）：
  ```
  ##[warning]Unexpected input(s) 'phpts', valid inputs are ['php-version', 'php-version-file', 'extensions', 'ini-file', 'ini-values', 'coverage', 'tools', 'github-token']
  ```
  即 `phpts` **不是** `shivammathur/setup-php@v2` 的 `with:` 输入（当前 v2 action.yml 无该输入），GitHub Actions 对未知 input 仅告警并静默忽略 → 实际装的是默认 NTS 包。

## 官方 typephp CI 原文写法（已抓取原文核实）

官方 linux-x64 CI 的 Setup PHP 步骤（`phpts`/`update` 均为 step **env:**，非 with:）：

```yaml
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          coverage: none
          ini-values: precision=17, memory_limit=4G, error_reporting=E_ERROR|E_WARNING, display_errors=1, display_startup_errors=1, log_errors=0
          tools: composer:v2
        env:
          fail-fast: true
          phpts: ts
          update: true
```

官方另有两个关键差异：① `runs-on: ubuntu-22.04`（非 ubuntu-latest/24.04）；② ZTS 验证用 `php -r 'printf("PHP_ZTS=%d\n", PHP_ZTS)'`（ZTS 时输出 `PHP_ZTS=1`）。

## 本次修正要点（commit 仅含 workflow 文件）

1. job `spike` `runs-on: ubuntu-latest` → `runs-on: ubuntu-22.04`（官方实证；ubuntu-latest/24.04 未验证）。
2. 删除原三步 dance（`Setup PHP 8.5 (ZTS)` with: phpts 版 / `Verify PHP ZTS (first attempt)` / `Retry Setup PHP 8.5 (ZTS) with update:true` / `Verify PHP ZTS (final, must pass)`），替换为两步：
   - `Setup PHP 8.5 (ZTS)`：with: `php-version: '8.5'` + `coverage: none` + `tools: composer:v2`（后两项照官方；ini-values 本项目无此需求未照抄）；**`phpts: ts` 与 `update: true` 移入 step `env:`**。
   - `Verify PHP ZTS`：`php -r 'printf("PHP_ZTS=%d\n", PHP_ZTS);'` 输出到 `$SPIKE_DIR/php-zts.txt`（保留 php -v 与 php -i thread 诊断行），`php -r 'exit(PHP_ZTS ? 0 : 1);'` 作硬门禁，通过后 `echo 'ZTS confirmed'`。
3. 其余步骤（tpc 下载、patch、PHP_HOME、phpx 构建、--dry、native_types、boundary、Summary、Upload）零改动。
4. YAML 自检通过：`python3 -c "import yaml;yaml.safe_load(open('.github/workflows/spike-typephp.yml'))"` → OK。

## 历史小节纠错声明

此前小节（"T0.1 阶段 1 补强"，15:33:22）将官方写法转述为 "`shivammathur/setup-php@v2`（`phpts: ts` + env `update: true`）"，且 learning 中 15:25 小节记为 "`phpts: ts`" 是 with 输入、"`update: true`" 仅作 NTS 重试——**该转述有误**（实为 setup-php 步骤失败主因）。正确结论：**`phpts` 与 `update` 都是 setup-php 步骤的 `env:` 参数，不是 `with:` 输入**；以本节为准。

# 2026-09-06 21:48 phpx ABI 不匹配修复（worker，run 34036983975 实证）

## 症状（PR #40 spike run 34036983975）

所有动态库已成功解析（ldd 显示 libphp.so / libphpx.so 均 found），但 `tpc --help` 退出码 127，报错原文：

```
symbol lookup error: .../tpc: undefined symbol: _ZN3php21FunctionCallCacheSlot4callERKNS_7VariantEjP12_zval_structP11_zend_array
```

`php::` 命名空间符号 = phpx 的 C++ 符号 → **从 swoole/phpx master 构建的 libphpx.so 与 tpc v0.7.0 release 二进制 ABI 不匹配**（`php::FunctionCallCacheSlot::call(php::Variant const&, unsigned, _zval_struct*, _zend_array*)` 在 tpc 二进制中未定义，说明运行时加载的 libphpx.so 导出的该符号签名/存在性与 tpc 编译期预期不一致）。

## 已核实事实

1. **ldd-after 全 resolved**：`$SPIKE_DIR/tpc-ldd-after.txt` 无 "not found"，libphp.so 与 libphpx.so 均被找到——失败不是"找不到库"，而是"库内容不匹配"。
2. **tpc v0.7.0 release tarball 自带与该二进制同源构建的 `vendor/swoole/phpx` 完整源码**（含 CMakeLists.txt），包内路径 `tpc_v0.7.0_linux_x64/vendor/swoole/phpx`，解压后位于 `$TPC_DIST`=/tmp/typephp-dist 下。LIBPHP_INSTALLER.md 本就规定 libphpx 查找顺序含 "TypePHP 源仓库 vendor/swoole/phpx"。
3. typephp v0.7.0 composer.json 约束 `swoole/phpx: ~2.7.0`；phpx 仓库最新 2.7.x tag 为 v2.7.0（备选方案，仅记录不采用）。

## 修复方案与理由

workflow `.github/workflows/spike-typephp.yml`（commit 99ba584）：

1. job env `PHPX_HOME: ${{ github.workspace }}/third_party/phpx` → `PHPX_HOME: /tmp/typephp-dist/tpc_v0.7.0_linux_x64/vendor/swoole/phpx`（与 TPC_DIST=/tmp/typephp-dist 同源，写死完整路径）。
2. 删除步骤 `Checkout PHPX (swoole/phpx, official typephp CI uses master)`（不再 clone master）。
3. `Build libphpx.so` 步骤原 cmake 命令零改动，仅 run 开头加 `echo "phpx source: $PHPX_HOME"`；PHPX_HOME 改指包内路径后自动生效。

**为何优于 pin v2.7.0 tag**：release tarball 内 vendor/swoole/phpx 是官方构建 tpc v0.7.0 二进制时实际使用的源码快照，与其 ABI 天然逐符号一致；v2.7.0 tag 只保证 "~2.7.0" 语义约束，不能保证与 tpc v0.7.0 构建时的 commit 完全相同（2.7.x 分支在 tag 之后仍可能演进），ABI 仍存在漂移风险。

## 遗留观察项

- 本修复待下次 CI run 实测验证。若 `tpc --help` 仍报个别 undefined symbol，再逐个归因（如包内源码与二进制仍非同 commit 的极端情况），届时考虑反推 tpc 二进制符号表比对。
- 其余步骤（Setup PHP、Verify ZTS、apt、cmake 断言、tpc 下载、patch、PHP_HOME、LD_LIBRARY_PATH、ldd、--dry/native_types/boundary 各 spike 组、Summary、Upload）本次零改动。

# 2026-09-06 21:55:31 tpc 运行目录修复（worker，run 34037299648 实测诊断）

## 症状（PR #40 spike run 34037299648）

前一修复（libphpx 从 release 包内 vendor/swoole/phpx 源码构建）已生效：全部动态库解析成功、**tpc 已能启动**（内嵌 compiler.php 开始执行）。新失败（exit 255），Fatal error 原文：

```
PHP Fatal error: Uncaught Error: Failed opening required '/home/runner/work/supports/supports/vendor/autoload.php'
in /home/runner/work/typephp/typephp/src/compiler.php:9
```

## 诊断推理

1. **CWD 相对 require**：报错路径 `/home/runner/work/supports/supports/vendor/autoload.php` 正是 tpc 运行时 CWD（workspace 根）下的相对路径 `vendor/autoload.php` 拼接结果；而内嵌 compiler.php 自身位于 `/home/runner/work/typephp/typephp/src/compiler.php`（二进制内嵌源码，与 supports 仓库无关）——证明 release `tpc` 二进制内嵌的 compiler.php 以 **CWD 相对方式** require `vendor/autoload.php`。
2. **包内自带 vendor**：release tarball 自带完整 `vendor/`（composer 生产依赖），其正确用法 = **cd 到解压根目录再调用 tpc**（等价 composer 安装形态 vendor/bin/tpc.php 的运行环境）；从 workspace 根运行故缺 autoloader。
3. **二进制位于解压根**：ldd 证实二进制就在解压根 `/tmp/typephp-dist/tpc_v0.7.0_linux_x64/tpc`，即解压根 = tpc 包根目录。

## 修复模式（commit ci(spike): tpc 统一改为解压根目录运行）

workflow `.github/workflows/spike-typephp.yml`，所有 tpc 调用统一改为「子 shell cd 到 tpc 包根目录 + 绝对路径」模式：

1. 每个含 tpc 调用的步骤定义 `TPC_BIN="$(find ...)"` + `TPC_HOME="$(dirname "$TPC_BIN")"`，执行改为 `(cd "$TPC_HOME" && "$TPC_BIN" ...)`。
2. **所有传给 tpc 的入参与产物路径一律绝对路径**（`$GITHUB_WORKSPACE/spike-tmp/...`、`$GITHUB_WORKSPACE/build/...`）：project.yml 内相对路径以 project.yml 所在目录为基准（已实证），但 CWD 已变为 tpc 包根，绝对路径消除 CWD 变化带来的歧义。
3. **编译产物运行**（nt run / boundary run）：产物为独立 AOT 二进制，不依赖 tpc 包根目录，保持 workspace 运行，仅路径改绝对；boundary diff 的 zend-output.txt 同步改绝对路径。
4. 其余步骤（Setup PHP、Verify ZTS、apt、cmake 断言、tpc 下载、ldd、patch、PHP_HOME、Build libphpx、LD_LIBRARY_PATH、Assert、Summary、Upload）零改动。

# 2026-09-06 22:04:39 — 阶段 2：CI 验证结论（定稿）

## CI run 信息

- **run id**：`34037668106`（PR #40，workflow `spike-typephp.yml`）
- **URL**：https://github.com/yansongda/supports/actions/runs/34037668106
- **conclusion**：**success**（main agent 已亲自从 artifact 逐文件核对）
- **artifact 归档**：`docs/evidence/typephp-native-optimization/artifacts/run-34037668106/`（26 个诊断文件整体随 evidence 入库，纯文本）。

## 验收对照表（全部满足）

| 验收项 | 证据（artifact 文件） | 实测 | 结论 |
|---|---|---|---|
| tpc 版本 v0.7.0 | `tpc-help.txt` | `TypePHP Compiler (AOT) v0.7.0` + USAGE 正常输出 | ✓ |
| release URL 实测可下载 | `tpc-download.txt` | 固定 URL `.../v0.7.0/tpc_v0.7.0_linux_x64.tar.gz` 下载 + SHA256SUMS 校验解压成功 | ✓ |
| ZTS PHP | `php-zts.txt` | PHP 8.5.10 (ZTS)，`Thread Safety => enabled`，`PHP_ZTS=1` | ✓ |
| cmake ≥ 3.24 | `system-deps.txt` | `cmake version 3.31.6`（≥ 3.24） | ✓ |
| libphp.so + libphpx.so 预置成功 | `tpc-ldd-after.txt` | `libphpx.so => .../tpc_v0.7.0_linux_x64/vendor/swoole/phpx/lib/libphpx.so`（release 包内源码构建）、`libphp.so => /usr/lib/libphp.so`（setup-php ZTS 包自带），全解析**无 not found** | ✓ |
| `tpc --help` 退出码 0 | `tpc-help-exit-code.txt` | `tpc --help exit code: 0`（run 内 Assert 硬门禁步骤通过） | ✓ |

## CI 迭代史摘要（3 次修复，详见前文各小节）

1. **pull_request 触发机制**（commit `42ea96e`）：workflow_dispatch/UI/REST 对非默认分支上的 workflow 文件均不可触发（404 / "not found on the default branch"）→ 加 `pull_request:` 触发器 + draft PR 自动运行。
2. **setup-php ZTS 写法 + ubuntu-22.04**（commit `90c345c`）：`phpts`/`update` 是步骤 `env:` 参数而非 `with:` 输入（误写被静默忽略装出 NTS）→ 移入 env 并固定 `runs-on: ubuntu-22.04`（官方 CI 实证）。
3. **包内 phpx 源码 + 包根目录运行**（commit `99ba584` + `929745a`）：swoole/phpx master 构建的 libphpx.so 与 tpc v0.7.0 release 二进制 ABI 不匹配（undefined symbol）→ 改用 release tarball 自带 `vendor/swoole/phpx` 源码构建；tpc 内嵌 compiler.php 以 CWD 相对 require vendor/autoload.php → 统一 cd 解压根目录调用并以绝对路径传参。

## Acceptance 状态（T0.1 定稿）

- [x] spike-typephp.yml 创建，YAML 语法自检通过
- [x] .gitignore 追加三行，原内容未动
- [x] 阶段 1 补强 commit `ea82687`（仅 workflow 文件）
- [x] （CI 回传）tpc 版本号记录 + `tpc --help` 退出码 0 运行记录 + 预置步骤组全部成功 → run 34037668106 success，验收全部满足
- [x] 阶段 2 定稿：本节 + artifact 归档入库

## 范围说明

- **T0.2 不在本次定稿范围**：`tpc --dry` 退出码 255（CI 检出无 supports vendor/，报 `Directory does not exist: .../vendor/psr/container/src`），其修复（composer install）另行处理。
- 附带产物：`nt-*` / `boundary-*` 诊断文件属 T0.4 / T0.5，各自 evidence 定稿，不在本文件展开。

# 2026-09-06 22:25 main agent 闸门核验（T0.1）

1. 亲自核对 run 34037668106 artifact 全部 26 文件：PHP_ZTS=1、cmake 3.31.6、tpc-ldd-after 全解析、**tpc --help 退出码 0**（TypePHP Compiler (AOT) v0.7.0）。验收对照表 6/6 ✓。
2. CI 迭代 3 次修复（触发机制/环境变量写法/包内 phpx/包根目录运行）均由本人诊断定位、worker 修复、本人验证，全程记录于本文件历史小节。
3. 结论：**T0.1 验收通过，勾选 [x]**。
