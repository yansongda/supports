<?php

declare(strict_types=1);

/*
 * tpc bin 模式入口（T3.1）。
 *
 * typephp bin 模式约束（INCOMPATIBLE_PHP_FEATURES.md Program structure）：
 * - 全局作用域零可执行语句，本文件仅声明 main()；
 * - main() 必须为全局函数，签名 (int $argc, array $argv)，返回 void；
 * - 转发 run.php 的 bench_cli_main()（tpc 侧符号由 project.yml sources 聚合解析，
 *   与 T2.1 冒烟 main.php -> src/ 跨文件调用同模式）。
 *
 * Zend CLI 侧不会自动调用 main()，故不直接执行本文件；Zend 侧由 php -r driver
 * 显式加载 cases.php + run.php 后调用 bench_cli_main()（见 run.php 头注）。
 */

function main(int $argc, array $argv): void
{
    bench_cli_main($argc, $argv);
}
