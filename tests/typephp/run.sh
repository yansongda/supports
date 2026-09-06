#!/usr/bin/env bash
# typephp 编译冒烟双模式运行器（T2.1）
#
# 用法：
#   ./run.sh                        # zend 模式（默认）：php + vendor/autoload.php，对照 expected.txt
#   ./run.sh tpc                    # tpc 模式：release tpc 二进制按 project.yml 编译 bin 后运行，对照 expected.txt
#   TPC_BIN=/path/to/tpc ./run.sh tpc   # 显式指定 tpc 二进制（默认取 PATH 中的 tpc）
#
# 基准 expected.txt 由 Zend(PHP 8.3) 产出；tpc(PHP 8.5) diff 失败时先排查 8.3/8.5 输出漂移，
# 再判定是否为 typephp 语义差异。
#
# tpc 调用模式遵循 learning「release tpc 标准调用模式」：cd 解压根 + 全绝对路径参数
# （tpc 内嵌 compiler.php 以 CWD 相对方式 require 自带 vendor/autoload.php）；
# project.yml 内相对路径相对 project.yml 所在目录解析，与 tpc 的 CWD 无关。
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
            "./${TPC_CMD}" "${REPO_DIR}/tests/typephp/project.yml" -o "${OUT_BIN}"
        )
        "${OUT_BIN}" | diff - "${EXPECTED}" || {
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
