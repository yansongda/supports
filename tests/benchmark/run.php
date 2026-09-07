<?php

declare(strict_types=1);

/*
 * benchmark 双运行时运行器（T3.1：Zend vs typephp，CI 同机 best-of-N）。
 *
 * typephp bin 模式约束：全局作用域零可执行语句（INCOMPATIBLE_PHP_FEATURES.md
 * Program structure，Intentional Rule，编译器显式拒绝），因此本文件只允许
 * declare/use/函数定义，全部逻辑位于函数内；tpc 侧符号由 project.yml 的
 * sources 聚合解析（与 T2.1 冒烟 main.php -> src/ 跨文件调用同模式）。
 *
 * 入口分工：
 * - tpc bin：bench-main.php 的全局 main(int $argc, array $argv): void -> bench_cli_main()；
 * - Zend CLI：PHP CLI 不会自动调用 main()，且本文件全局作用域不可执行任何语句，
 *   故由 driver 显式加载 cases.php + run.php 后调用 bench_cli_main()：
 *   php -r 'require "tests/benchmark/cases.php"; require "tests/benchmark/run.php"; bench_cli_main((int) $argc, $argv);' \
 *     --runtime=zend --case=all --rounds=5 --output=build/bench-zend.json
 *
 * 用法：
 *   单侧：--runtime=zend|typephp --case=<name|all> --rounds=<int> --output=<path>
 *   合并：--merge=<zend.json>,<typephp.json> --output=<path>
 *
 * JSON 结构（单侧）：
 *   {"env":{"runtime","php_version","sapi","os","rounds","case"},"results":[{"case","n","ms"}]}
 * JSON 结构（合并，--merge 产出）：
 *   {"env":{"zend":{...},"typephp":{...}},"results":[{"case","n","zend_ms","typephp_ms","ratio"}]}
 *   ratio = typephp_ms / zend_ms，保留 3 位小数（>1 表示 typephp 更慢）。
 */

/**
 * 解析 CLI 参数。
 *
 * @param array<int, string> $argv
 *
 * @return array{runtime: string, case: string, rounds: int, output: null|string, merge: null|array{0: string, 1: string}}
 *
 * @throws \InvalidArgumentException
 */
function bench_parse_args(int $argc, array $argv): array
{
    $runtime = '';
    $case = 'all';
    $rounds = 5;
    $output = null;
    $merge = null;

    for ($i = 1; $i < $argc; ++$i) {
        $arg = (string) $argv[$i];
        if (!str_starts_with($arg, '--')) {
            throw new \InvalidArgumentException('Unexpected argument: '.$arg);
        }

        $name = substr($arg, 2);
        $value = '';
        $eq = strpos($name, '=');
        if (false !== $eq) {
            $value = substr($name, $eq + 1);
            $name = substr($name, 0, $eq);
        }

        switch ($name) {
            case 'runtime':
                $runtime = $value;

                break;
            case 'case':
                $case = $value;

                break;
            case 'rounds':
                $rounds = (int) $value;

                break;
            case 'output':
                $output = $value;

                break;
            case 'merge':
                $parts = explode(',', $value, 2);
                if (2 !== count($parts) || '' === $parts[0] || '' === $parts[1]) {
                    throw new \InvalidArgumentException('--merge expects <zend.json>,<typephp.json>');
                }
                $merge = [$parts[0], $parts[1]];

                break;
            default:
                throw new \InvalidArgumentException('Unknown option: --'.$name);
        }
    }

    if (null === $merge) {
        if (!in_array($runtime, ['zend', 'typephp'], true)) {
            throw new \InvalidArgumentException('--runtime must be zend or typephp');
        }
        if ($rounds < 1) {
            throw new \InvalidArgumentException('--rounds must be >= 1');
        }
        if ('all' !== $case && !in_array($case, bench_case_names(), true)) {
            throw new \InvalidArgumentException('Unknown case: '.$case.' (available: '.implode(', ', bench_case_names()).')');
        }
    }

    return ['runtime' => $runtime, 'case' => $case, 'rounds' => $rounds, 'output' => $output, 'merge' => $merge];
}

/**
 * 运行环境描述（无时间戳/随机值，保证 JSON 键序与内容可复现）。
 *
 * @return array<string, mixed>
 */
function bench_env(string $runtime, int $rounds, string $case): array
{
    return [
        'runtime' => $runtime,
        'php_version' => \PHP_VERSION,
        'sapi' => \PHP_SAPI,
        'os' => \PHP_OS,
        'rounds' => $rounds,
        'case' => $case,
    ];
}

/**
 * 单侧运行：--case 展开 case 名单，每 case 跑 rounds 轮取最小（best-of-N）。
 *
 * @return array{env: array<string, mixed>, results: list<array{case: string, n: int, ms: float}>}
 */
function bench_run_side(string $runtime, string $case, int $rounds): array
{
    $names = 'all' === $case ? bench_case_names() : [$case];
    $results = [];
    foreach ($names as $name) {
        $iterations = bench_case_iterations($name);
        if ($iterations <= 0) {
            throw new \InvalidArgumentException('Unknown benchmark case: '.$name);
        }

        $best = null;
        for ($r = 0; $r < $rounds; ++$r) {
            $ms = bench($name, $iterations);
            if (null === $best || $ms < $best) {
                $best = $ms;
            }
        }

        $results[] = ['case' => $name, 'n' => $iterations, 'ms' => round((float) $best, 3)];
    }

    return ['env' => bench_env($runtime, $rounds, $case), 'results' => $results];
}

/**
 * 读取并校验单侧 JSON。
 *
 * @return array{env: array<string, mixed>, results: list<array{case: string, n: int, ms: float}>}
 *
 * @throws \RuntimeException
 */
function bench_read_json(string $file): array
{
    if (!is_file($file)) {
        throw new \RuntimeException('JSON file not found: '.$file);
    }

    $decoded = json_decode((string) file_get_contents($file), true);
    if (!is_array($decoded) || !isset($decoded['env'], $decoded['results']) || !is_array($decoded['results'])) {
        throw new \RuntimeException('Invalid benchmark JSON: '.$file);
    }

    return $decoded;
}

/**
 * 合并两份单侧 JSON为同构报告（results[].zend_ms/typephp_ms/ratio，以 zend 侧 case 顺序为基准）。
 *
 * @param null|string $output
 *
 * @throws \RuntimeException
 */
function bench_merge(string $zendFile, string $typephpFile, ?string $output): void
{
    $zend = bench_read_json($zendFile);
    $typephp = bench_read_json($typephpFile);

    $byCase = [];
    foreach ($typephp['results'] as $row) {
        $byCase[$row['case']] = $row;
    }

    $results = [];
    foreach ($zend['results'] as $row) {
        $case = $row['case'];
        if (!isset($byCase[$case])) {
            throw new \RuntimeException('typephp JSON missing case: '.$case);
        }

        $zendMs = (float) $row['ms'];
        $typephpMs = (float) $byCase[$case]['ms'];
        $results[] = [
            'case' => $case,
            'n' => $row['n'],
            'zend_ms' => $zendMs,
            'typephp_ms' => $typephpMs,
            'ratio' => $zendMs > 0 ? round($typephpMs / $zendMs, 3) : 0.0,
        ];
    }

    $payload = ['env' => ['zend' => $zend['env'], 'typephp' => $typephp['env']], 'results' => $results];

    printf("%-30s %10s %12s %12s %8s\n", 'case', 'n', 'zend_ms', 'typephp_ms', 'ratio');
    foreach ($results as $row) {
        printf("%-30s %10d %12.3f %12.3f %8.3f\n", $row['case'], $row['n'], $row['zend_ms'], $row['typephp_ms'], $row['ratio']);
    }
    echo PHP_EOL;

    bench_write_json($output, $payload);
}

/**
 * 单侧 stdout 摘要表。
 *
 * @param array<string, mixed>                        $env
 * @param list<array{case: string, n: int, ms: float}> $results
 */
function bench_print_side_table(string $runtime, array $env, array $results): void
{
    printf(
        "benchmark runtime=%s php=%s sapi=%s rounds=%s case=%s\n",
        $runtime,
        isset($env['php_version']) ? (string) $env['php_version'] : 'unknown',
        isset($env['sapi']) ? (string) $env['sapi'] : 'unknown',
        isset($env['rounds']) ? (string) $env['rounds'] : 'unknown',
        isset($env['case']) ? (string) $env['case'] : 'unknown'
    );
    printf("%-30s %10s %12s\n", 'case', 'n', 'best_ms');
    foreach ($results as $row) {
        printf("%-30s %10d %12.3f\n", $row['case'], $row['n'], $row['ms']);
    }
    echo PHP_EOL;
}

/**
 * 写 JSON 文件（自动建父目录）并同步输出到 stdout。
 *
 * @param array<string, mixed> $payload
 *
 * @throws \RuntimeException
 */
function bench_write_json(?string $output, array $payload): void
{
    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (false === $json) {
        throw new \RuntimeException('json_encode failed');
    }

    if (null !== $output && '' !== $output) {
        $dir = dirname($output);
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new \RuntimeException('Unable to create output directory: '.$dir);
        }
        file_put_contents($output, $json."\n");
        printf('JSON written to %s%s', $output, PHP_EOL);
    }

    echo $json, PHP_EOL;
}

/**
 * CLI 入口（tpc bin 由 bench-main.php 的 main() 转发至此；Zend 侧由 php -r driver 调用）。
 *
 * @param array<int, string> $argv
 *
 * @throws \InvalidArgumentException|\RuntimeException
 */
function bench_cli_main(int $argc, array $argv): void
{
    $args = bench_parse_args($argc, $argv);

    if (null !== $args['merge']) {
        bench_merge($args['merge'][0], $args['merge'][1], $args['output']);

        return;
    }

    $payload = bench_run_side($args['runtime'], $args['case'], $args['rounds']);
    bench_print_side_table($args['runtime'], $payload['env'], $payload['results']);
    bench_write_json($args['output'], $payload);
}
