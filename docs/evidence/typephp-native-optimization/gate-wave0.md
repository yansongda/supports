# 2026-09-06 22:24:30 Wave 0 闸门记录

## 性质

本文件为 Wave 0 闸门（plan T0.5 步骤 4 与闸门要求）收尾记录，由执行 worker 创建；闸门判定本身由 main agent 核验完成（核验记录见各 evidence 文件时间戳小节，commit `cc8ee7a`）。

## 闸门输入（Wave 0 五项 todo，全部验收通过并已勾选 [x]）

| todo | 内容 | 结论 | 证据 |
| ---- | ---- | ---- | ---- |
| T0.1 | tpc 环境 spike（CI 验证 tpc release 二进制 + phpx + libphp 运行环境） | 验收 6/6 ✓（tpc v0.7.0 + SHA256 校验 + PHP 8.5.10 ZTS + cmake 3.31.6 ≥ 3.24 + ldd 无 not found + `tpc --help` exit 0） | run `34037668106` success；`task-0.1-env.md` + artifacts 归档 |
| T0.2 | src 全量编译报错清单（`tpc --dry` 13 文件 prepare） | exit 0；A/B 归类：A 类 4 项全部实测落地（except/through func_get_args 仅 WARNING、toString 方法定义零诊断、psr/container strict_types=1 零报错），B 类（未知新项）为零 | run `34038640605` success；`task-0.2-compile-report.md` + artifacts 归档 |
| T0.3 | Zend 基线（改造前，Docker cli-8.3-alpine，PHP 8.3.30 NTS） | 全绿：`composer test` 55 tests / 10101 assertions、`composer analyse` phpstan level 6 No errors、`composer cs-fix` 0 待修复，三命令退出码均 0 | `task-0.3-zend-baseline.md` |
| T0.4 | native_types 语义 spike（`Int += Float` 截断） | 双运行时实证：tpc 输出 `12`/`4`（exit 0），Zend 输出 `12.5`/`4` → `use native_types` 在 Zend 下为 Warning 级 no-op；`Int += Float` 截断语义差异在 typephp 下真实存在（与 NATIVE_TYPES.md Note 一致） | `task-0.4-native-types.md` |
| T0.5 | 边界模式 spike（5 类不兼容模式） | 4/5 PASS（PAT_STATIC_CACHE、PAT_REF_REBIND、PAT_MAGIC_IFACE、PAT_FUNC_STATIC 字节级一致）；FAIL 仅 PAT_FOREACH_REF，行为差异已记录规避建议 | `task-0.5-boundary.md` |

## 基线信息

- 执行分支：`feat/typephp-native-optimization`
- 基线 commit：`e04a668ba1472ce93869378861d13d3ea873d09a`（master，F1 审计以此为基准）
- 闸门收尾时 HEAD：`f260b7d`（本记录创建时点，即 spike-tmp 删除 commit）

## 闸门决议

**通过**。允许进入 Wave 1（串行 T1.1 → T1.2 → T1.3 → T1.4）。

## 遗留记录

- **PAT_FOREACH_REF 行为差异**（T0.5 唯一 FAIL）：`foreach ($arr as &$v)` 引用遍历 + 循环体内递归自调用 + ksort 组合下，tpc 输出为空（json_encode 返回 false 形态，二进制 exit 0 无报错），Zend 输出完整 JSON。
  - 影响面：`src/Arr.php` sortRecursive（:460）一处，**Wave 1 不触碰**；
  - 后续要求：T2.1 冒烟设计须考量该模式（规避写法：键位赋值 `foreach ($array as $k => $v) { $array[$k] = recurse($v); }`，待 T2.1 冒烟实证）；T3.3 须将该差异写入 docs/typephp.md 已知差异清单。
- 其余差异（T0.4 `Int += Float` 截断、T0.2 func_get_args 运行期语义风险）已各自记录于对应 evidence，供 T2.1/T3.2/T3.3 消费。

## spike-tmp 删除确认

按 learning「spike-tmp 临时入库决议」（CI 需检出 spike 文件，闸门通过后整目录删除）执行：

```
$ git rm -r spike-tmp
rm 'spike-tmp/boundary/main.php'
rm 'spike-tmp/boundary/zend-output.txt'
rm 'spike-tmp/main-stub.php'
rm 'spike-tmp/nt/nt.php'
rm 'spike-tmp/project.yml'

$ git commit ...
[feat/typephp-native-optimization f260b7d] chore(spike): Wave 0 闸门通过,删除临时 spike-tmp 目录
 5 files changed, 271 deletions(-)
```

- 删除 commit：`f260b7d`，5 files changed, 271 deletions(-)；
- 工作区 `spike-tmp/` 已不存在；
- 净 diff 不含 spike-tmp：`git log e04a668..HEAD --stat -- spike-tmp` 仅显示上述删除 commit（删除与临时入库互相抵消），后续从基线 `e04a668` 起算的净 diff 无 spike-tmp 内容；
- `docs/evidence/typephp-native-optimization/artifacts/` 下已归档的 spike 数据（run `34037668106` / run `34038640605`）为闸门证据归档，**保留不动**。
