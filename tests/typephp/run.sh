#!/usr/bin/env bash
# typephp 编译冒烟双模式运行器（T2.1）
#
# 用法：
#   ./run.sh                        # zend 模式（默认）：php + vendor/autoload.php，对照 expected.txt
#   ./run.sh tpc                    # tpc 模式：release tpc 二进制按 project.yml 编译 bin 后运行，对照 expected.txt
#   TPC_BIN=/path/to/tpc ./run.sh tpc   # 显式指定 tpc 二进制（默认取 PATH 中的 tpc）
#   TPC_OPTS="-O 0" ./run.sh tpc    # 自定义 tpc 编译参数（默认 -O 3 --lto；显式 -O 0 回退编译器默认档）
#
# 基准 expected.txt 由 Zend(PHP 8.3) 产出；tpc(PHP 8.5) diff 失败时先排查 8.3/8.5 输出漂移，
# 再判定是否为 typephp 语义差异。
#
# tpc 调用模式遵循 learning「release tpc 标准调用模式」：cd 解压根 + 全绝对路径参数
# （tpc 内嵌 compiler.php 以 CWD 相对方式 require 自带 vendor/autoload.php）；
# project.yml 内相对路径相对 project.yml 所在目录解析，与 tpc 的 CWD 无关。
#
# tpc CLI 解析规则（CI run 34139563499 实证）：tpc 取第一个非选项 token 为输入文件，
# 因此选项（如 -O 3 --lto）必须置于 project.yml 之后——与 benchmark-typephp.yml 已实证
# 成功的形态（project.yml 在前、-O 3 --lto 在后，O3+LTO 数据正常产出）一致。
#
# 脚本保持 POSIX 兼容（busybox ash 可运行），shebang 为 bash 供 CI 使用。

set -eu

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
REPO_DIR="$(cd "${SCRIPT_DIR}/../.." && pwd)"
EXPECTED="${SCRIPT_DIR}/expected.txt"

MODE="${1:-zend}"
case "${MODE}" in
    zend)
        php "${SCRIPT_DIR}/zend-run.php" | diff - "${EXPECTED}" || {
            echo 'smoke diff FAILED (zend output vs expected.txt)' >&2
            exit 1
        }
        ;;
    tpc)
        TPC_BIN="${TPC_BIN:-tpc}"
        # 编译档位默认 -O 3 --lto：benchmark 首轮以 tpc 编译器默认档（-O <0-3> 默认 0，
        # COMPILER_CLI.md）测得全面慢于 Zend 1.7x~8.4x，性能测试必须显式指定优化档位。
        # 注意 ${TPC_OPTS:-...} 在 unset 与空串时均取默认值；需回退编译器默认档（-O0）
        # 时请显式 TPC_OPTS="-O 0" ./run.sh tpc（置空串不会回退）。
        # ${TPC_OPTS} 故意不加引号：默认值由两个独立参数（-O 3 与 --lto）构成，加引号会被
        # tpc 当作单个参数；shellcheck SC2086 风格在此为有意为之。
        TPC_OPTS="${TPC_OPTS:--O 3 --lto}"
        # 裸命令名先经 PATH 解析为绝对路径
        case "${TPC_BIN}" in
            */*) ;;
            *) TPC_BIN="$(command -v "${TPC_BIN}")" ;;
        esac
        TPC_HOME="$(cd "$(dirname "${TPC_BIN}")" && pwd)"
        TPC_CMD="$(basename "${TPC_BIN}")"
        OUT_BIN="${REPO_DIR}/build/smoke"
        mkdir -p "$(dirname "${OUT_BIN}")"
        (
            cd "${TPC_HOME}" || exit 1
            "./${TPC_CMD}" "${REPO_DIR}/tests/typephp/project.yml" ${TPC_OPTS} -o "${OUT_BIN}"
        )
        # 先捕获二进制输出与退出码（stdout/stderr 分离）：tpc 严格实参计数等运行期错误
        # 会以非 0 退出码退出，若无 pipefail 的管道形态会只报 diff 行差而丢失退出码
        "${OUT_BIN}" > /tmp/smoke-actual.txt 2> /tmp/smoke-err.txt || {
            echo 'tpc binary exited non-zero' >&2
            cat /tmp/smoke-err.txt >&2
            echo '--- smoke stdout tail ---' >&2
            tail -20 /tmp/smoke-actual.txt >&2
            exit 1
        }
        diff /tmp/smoke-actual.txt "${EXPECTED}" || {
            echo 'smoke diff FAILED (tpc output vs zend baseline expected.txt)' >&2
            echo 'hint: 先排查 PHP 8.3(zend 基线) 与 PHP 8.5(tpc) 的输出漂移，再判定是否为 typephp 语义差异' >&2
            exit 1
        }
        ;;
    *)
        echo "usage: $(basename "$0") [zend|tpc]" >&2
        exit 2
        ;;
esac

echo "smoke OK (${MODE})"
