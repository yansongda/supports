---
name: releasing-php-package
description: Use when preparing to publish a new version of a PHP Composer package and need to write or update the CHANGELOG before tagging and releasing
---

# 发布 PHP Composer 包

## 概述

用于发布 PHP Composer 包的结构化流程，避免 CHANGELOG 格式混乱、发布时序错乱（PR 未合并就打 tag）等常见问题，并强制要求：改动走独立分支 + PR，人工合并后方可打 tag。

**硬性约束（违反即流程无效）：**
- Agent 只负责创建 PR，**严禁自行合并 PR**（包括 `gh pr merge`、平台合并 API 等任何形式的自动合并）
- PR 必须由人工审核后合并；在获得人工「PR 已合并」确认前，Agent 不得打 tag、不得创建 Release
- **版本号以维护者指定为准**：Patch/Minor/Major 分类仅作默认建议，维护者指定后不得「纠正」

## 使用场景

- 准备发布新版本（patch/minor/major/beta/rc）
- CHANGELOG 使用了非标准分类或前缀
- 上次发布曾因 CHANGELOG 问题被反馈

**不要使用：**
- 项目已有自动化发布工具（semantic-release、release-please）
- 仅单文件改动且对用户无可见影响

## 版本分类

| 变更类型 | 版本号变化 |
|---|---|
| 仅 Bug 修复 | Patch（1.0.X） |
| 新功能，向后兼容 | Minor（1.X.0） |
| 破坏性变更 | Major（X.0.0） |
| 预发布（beta/rc/alpha） | 预发布版本（1.0.0-beta.1） |

## 核心模式

### 错误示例

```markdown
## v1.2.0 - 2024-01-01

### BREAKING CHANGES
- change: PHP version bumped to 8.2

### Added
- feat: add new provider support (#42)
- feat: add new config system (#43)

### Changed
- fix: correct error code (#45)
- chore: update dependencies (#46)
```

### 正确示例（符合 Keep a Changelog）

```markdown
## [v1.2.0] - 2024-01-01

### Added
- New payment provider support (#42)
- Typed configuration objects replacing arrays (#43)

### Changed
- Minimum PHP version from 8.0 to 8.2 (#41)
- All internal plugins migrated to trait-based architecture (#44)

### Removed
- Legacy helper functions file, migrated to static methods (#44)
- Deprecated `getOldConfig()` functions, use `getProviderConfig()` (#41)
```

## 速查表

| 任务 | 命令 / 文件 | 关键规则 |
|---|---|---|
| 查看当前分支 | `git branch --show-current` | 禁止在 `master`/`main` 上直接修改 CHANGELOG |
| 创建发布分支 | `git checkout -b docs/changelog-vX.Y.Z` | 所有改动必须走 PR |
| 列出自上一个 tag 以来的提交 | `git log --oneline --no-merges $(git describe --tags --abbrev=0)..HEAD` | 排除 merge commit |
| 标准分类 | `Added` → `Changed` → `Deprecated` → `Removed` → `Fixed` → `Security` | 严格顺序，不新增分类 |
| 去除前缀 | 移除 `feat:`、`fix:`、`refactor:`、`chore:` | 仅当存在时才移除；已干净的中文描述保持原样 |
| 合并 PR | 由人工在平台审核后执行 | Agent 严禁自动合并（`gh pr merge` 等一律禁止） |
| 创建 annotated tag | `git tag -a vX.Y.Z -m "release: vX.Y.Z"` | 必须在 PR 由人工合并后执行 |
| 创建 GitHub Release | `gh release create vX.Y.Z --notes-file release-notes.md --prerelease` | beta/rc/alpha 需标记为 pre-release |

## 执行步骤

### 0. 分支隔离（关键）

**在修改任何发布相关文件前，必须先创建独立分支。**

```bash
# 先确认当前分支
git branch --show-current

# 如果在 master/main 上，立即切出发布分支
git checkout -b docs/changelog-vX.Y.Z
```

**禁止**在 `master`/`main` 上直接提交 CHANGELOG 改动。

### 1. 分析提交

```bash
latest=$(git describe --tags --abbrev=0)
git log --oneline --no-merges ${latest}..HEAD
```

重点关注：PR 编号、破坏性变更、新功能、已弃用 / 已移除项。

**仅文档发布判断：** 如果自上一个 tag 以来的代码变更已经在目标分支上，而你只是整理 CHANGELOG，则按「仅文档发布」处理。流程相同，但提交范围只包含 CHANGELOG。

**关键验证步骤：**
分析完提交后，务必验证实际代码状态，避免把「新增后又移除」的功能写进发布说明：

```bash
# 验证文件增删
git diff ${latest}..HEAD --stat

# 验证 src/ 下实际代码变更
git diff ${latest}..HEAD -- src/
```

**验证规则：**
1. 对于 commit 消息中的「新增」项：
   - 确认文件 / 类 / 方法在 HEAD 中仍然存在
   - 如果后续 commit 已移除，则从发布说明中排除
   - 示例：#1157 新增 NetworkException，但 #1161 又移除了 → 不要写进 Added

2. 对于 commit 消息中的「移除」项：
   - 确认文件 / 类 / 方法在 HEAD 中已不存在
   - 如果仍然存在，则排除或改为「已弃用」

3. 对于 commit 消息中的「变更」项：
   - 确认变更在 HEAD 中真实存在
   - 如果已被 revert，则从发布说明中排除

**验证命令：**
```bash
# 确认文件存在于 HEAD
git show HEAD:path/to/file

# 确认文件变更
git diff ${latest}..HEAD -- path/to/file
```

### 2. 规范化 CHANGELOG

**分类顺序（严格）**：Added、Changed、Deprecated、Removed、Fixed、Security。

**规则：**
- 仅当存在 `feat:` / `fix:` / `refactor:` / `chore:` 等前缀时才移除
- 不要重写已经是干净中文描述的条目
- 将 `BREAKING CHANGES` 内容移入 `Changed` 或 `Removed`，破坏性变更须给出「旧写法 → 新写法」
- 合并重复条目（如同一文件被删除两次）
- 保留 PR 引用 `(#1234)` 以便追溯
- 子列表缩进 2 个空格
- 使用者可见的行为 / 签名变化、迁移示例、运行环境要求变化（如 PHP 最低版本）全部写在本版本小节内

**版本标题格式：**
```markdown
## [v1.2.0] - 2024-01-01
```

### 3. PR → Tag → Release

**创建 PR：**
```bash
git add CHANGELOG.md

git commit -m "docs: update CHANGELOG for vX.Y.Z"

git push -u origin docs/changelog-vX.Y.Z

# 通过 GitHub / GitLab / Gitea 等平台创建 PR
```

**⛔ 流程暂停点：创建 PR 后必须停止，等待人工审核合并**

- 告知用户 PR 链接，明确说明等待人工审核合并，然后结束当前步骤
- **严禁**执行 `gh pr merge`（含 --squash/--merge/--rebase）、平台合并 API 等任何自动合并操作
- 后续步骤（push tag、创建 Release）即使已获授权，也不包含 PR 合并权；合并永远由人工执行

**人工合并后（继续前必须确认 PR 已由人工合并）：**
```bash
# 核实 PR 状态（state 应为 MERGED，mergedBy 应为人工账号）
gh pr view <PR编号> --json state,mergedAt,mergedBy
git fetch origin
git log --oneline origin/master | head -5

# 打 tag 并推送
git checkout master && git pull origin master
git tag -a vX.Y.Z -m "release: vX.Y.Z"
git push origin vX.Y.Z
```

**创建 Release（以 GitHub 为例）：**
```bash
# 从 CHANGELOG 提取当前版本段落（正则按仓库实际标题格式调整，本仓库为 `## [vX.Y.Z]`）
sed -n '/^## \[vX.Y.Z\]/,/^## \[v/p' CHANGELOG.md | sed '$d' > release-notes.md

# beta/rc/alpha 版本需添加 --prerelease
gh release create vX.Y.Z --title "vX.Y.Z" --notes-file release-notes.md --prerelease
```

## 常见错误

| 错误 | 原因 | 修正 |
|---|---|---|
| 直接在 master 提交 CHANGELOG | 跳过分支隔离 | 必须先创建 `docs/changelog-vX.Y.Z` 分支 |
| CHANGELOG 保留 `feat:`/`fix:` 前缀 | 从 commit 消息复制粘贴 | 仅在有前缀时剥离；保持已有干净描述 |
| PR 未合并就创建 tag | 急于完成发布 | 先确认 `origin/master` 包含合并提交 |
| 使用 lightweight tag | `git tag` 缺少 `-a` | 始终使用 `git tag -a` 并附带 message |
| Release notes 与 CHANGELOG 不一致 | 单独编写 release notes | 从 CHANGELOG 提取或复制 |
| 预发布版本未标记 pre-release | 忘记 beta/rc/alpha 后缀 | 按版本后缀在发布平台勾选 pre-release |
| 写入「新增后又移除」的功能 | 仅依赖 commit 消息未验证代码 | 用 `git diff` 确认功能在 HEAD 中真实存在 |
| 擅自「纠正」维护者指定的版本号 | 按语义化版本自行判断 | 版本号以维护者指定为准，分类表仅作默认建议 |
| Agent 自行合并 PR | 误将「发布流程授权」当作「合并授权」 | 如需撤销：`git revert <merge-commit>`；此后一律仅创建 PR 并等待人工合并 |
