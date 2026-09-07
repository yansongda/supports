<?php

declare(strict_types=1);

/*
 * benchmark 固定 case 集（T3.1：Zend vs typephp 双运行时对比）。
 *
 * typephp bin 模式约束（INCOMPATIBLE_PHP_FEATURES.md Program structure）：
 * - 全局作用域零可执行语句：本文件只允许 declare/use/常量/函数与类定义；
 * - 每 case 一个独立实现函数，函数级 static sink 累加防死代码消除
 *   （函数级 static 模式已由 T0.5 spike 的 PAT_STATIC_CACHE 实证 typephp 兼容）；
 * - case 输入全部为固定数据（无随机、无网络/IO），数据在循环外构造，循环内不重复构造；
 * - 随机型 case（Str::random/Str::uuidV4）仅对返回值取 strlen 累加，原始值不进入任何输出。
 */

use Psr\Container\ContainerInterface;
use Yansongda\Supports\Arr;
use Yansongda\Supports\Collection;
use Yansongda\Supports\Pipeline;
use Yansongda\Supports\Str;

/**
 * PSR-11 容器 stub：仅注册字符串管道类 BenchPipelinePipe，供 Pipeline 字符串管道解析路径使用
 * （同 T2.1 冒烟 stub 模式精简；get 不声明返回类型以兼容 vendor 内接口签名）。
 */
class BenchPsrContainer implements ContainerInterface
{
    public function get(string $id)
    {
        if ('BenchPipelinePipe' === $id) {
            return new BenchPipelinePipe();
        }

        throw new \RuntimeException('Service not found: '.$id);
    }

    public function has(string $id): bool
    {
        return 'BenchPipelinePipe' === $id;
    }
}

/**
 * Pipeline 字符串管道目标类（经容器 stub 解析，默认方法 handle）。
 */
class BenchPipelinePipe
{
    public function handle(mixed $passable, \Closure $next, string $prefix = 'pipe'): mixed
    {
        return $next($prefix.':'.$passable);
    }
}

/**
 * case 名单（有序，--case=all 依此展开，JSON results 顺序与之一致）。
 *
 * @return array<int, string>
 */
function bench_case_names(): array
{
    return [
        'str_snake',
        'str_slug',
        'str_random',
        'str_uuid_v4',
        'arr_collapse',
        'arr_merge',
        'arr_dot',
        'arr_camel_case_key',
        'collection_map_filter_sort',
        'pipeline_full_chain',
    ];
}

/**
 * 每个 case 的固定迭代数。
 */
function bench_case_iterations(string $case): int
{
    switch ($case) {
        case 'str_snake':
            return 100000;
        case 'str_slug':
            return 50000;
        case 'str_random':
            return 10000;
        case 'str_uuid_v4':
            return 10000;
        case 'arr_collapse':
            return 20000;
        case 'arr_merge':
            return 20000;
        case 'arr_dot':
            return 10000;
        case 'arr_camel_case_key':
            return 10000;
        case 'collection_map_filter_sort':
            return 5000;
        case 'pipeline_full_chain':
            return 2000;
        default:
            return 0;
    }
}

/**
 * 执行单个 case 计时，返回耗时毫秒（hrtime 单调时钟）。
 *
 * @throws \InvalidArgumentException
 */
function bench(string $case, int $n): float
{
    $start = hrtime(true);

    switch ($case) {
        case 'str_snake':
            bench_case_str_snake($n);

            break;
        case 'str_slug':
            bench_case_str_slug($n);

            break;
        case 'str_random':
            bench_case_str_random($n);

            break;
        case 'str_uuid_v4':
            bench_case_str_uuid_v4($n);

            break;
        case 'arr_collapse':
            bench_case_arr_collapse($n);

            break;
        case 'arr_merge':
            bench_case_arr_merge($n);

            break;
        case 'arr_dot':
            bench_case_arr_dot($n);

            break;
        case 'arr_camel_case_key':
            bench_case_arr_camel_case_key($n);

            break;
        case 'collection_map_filter_sort':
            bench_case_collection_map_filter_sort($n);

            break;
        case 'pipeline_full_chain':
            bench_case_pipeline_full_chain($n);

            break;
        default:
            throw new \InvalidArgumentException('Unknown benchmark case: '.$case);
    }

    return (hrtime(true) - $start) / 1000000;
}

function bench_case_str_snake(int $n): void
{
    static $sink = 0;
    $inputs = ['userName', 'HelloWorld_Test', 'yansongda supports pkg', 'HTTPStatus2XX_Code', 'already_snake_case'];
    $count = count($inputs);
    for ($i = 0; $i < $n; ++$i) {
        $sink += strlen(Str::snake($inputs[$i % $count]));
    }
}

function bench_case_str_slug(int $n): void
{
    static $sink = 0;
    // T1.5 修复后 charsArray 路径有效：含 °/@ 数字键映射与 languageSpecific/de 变体输入
    $inputs = ['Hello_World 5° C @ cafe', 'déjà ü at café', 'schöne änderung', 'PHP Framework Support', 'user_profile-settings'];
    $count = count($inputs);
    for ($i = 0; $i < $n; ++$i) {
        $sink += strlen(Str::slug($inputs[$i % $count]));
    }
}

function bench_case_str_random(int $n): void
{
    static $sink = 0;
    for ($i = 0; $i < $n; ++$i) {
        $sink += strlen(Str::random(16));
    }
}

function bench_case_str_uuid_v4(int $n): void
{
    static $sink = 0;
    for ($i = 0; $i < $n; ++$i) {
        $sink += strlen(Str::uuidV4());
    }
}

function bench_case_arr_collapse(int $n): void
{
    static $sink = 0;
    // 含 Collection 元素覆盖 Traversable 分支（实例预构造，循环内不重复构造）
    $chunks = [[1, 2, 3], ['a' => 1, 'b' => 2], [4, 5], new Collection([6, 7]), [8]];
    for ($i = 0; $i < $n; ++$i) {
        $sink += count(Arr::collapse($chunks));
    }
}

function bench_case_arr_merge(int $n): void
{
    static $sink = 0;
    $base = ['a' => 1, 'list' => [1, 2], 'nested' => ['x' => 1]];
    $overlay = ['b' => 2, 'list' => [3], 'nested' => ['y' => 2]];
    for ($i = 0; $i < $n; ++$i) {
        $sink += count(Arr::merge($base, $overlay));
    }
}

function bench_case_arr_dot(int $n): void
{
    static $sink = 0;
    $nested = ['user' => ['name' => 'yansongda', 'tags' => ['php', 'swoole']], 'db' => ['host' => 'localhost', 'port' => 3306]];
    for ($i = 0; $i < $n; ++$i) {
        $sink += count(Arr::dot($nested));
    }
}

function bench_case_arr_camel_case_key(int $n): void
{
    static $sink = 0;
    $data = ['user_name' => 'a', 'user_age' => 28, 'list_data' => ['item_key' => 1, 'sub_key' => ['deep_key' => 2]], 3 => 'num'];
    for ($i = 0; $i < $n; ++$i) {
        $result = Arr::camelCaseKey($data);
        $sink += is_array($result) ? count($result) : 0;
    }
}

function bench_case_collection_map_filter_sort(int $n): void
{
    static $sink = 0;
    $items = [
        ['id' => 5, 'v' => 2],
        ['id' => 1, 'v' => 9],
        ['id' => 3, 'v' => 4],
        ['id' => 8, 'v' => 1],
        ['id' => 2, 'v' => 6],
        ['id' => 7, 'v' => 3],
        ['id' => 4, 'v' => 8],
        ['id' => 6, 'v' => 5],
    ];
    for ($i = 0; $i < $n; ++$i) {
        // sortBy('id') 走 valueRetriever 的 data_get 路径；闭包保持 T2.1 冒烟的无类型标注形态
        $result = (new Collection($items))
            ->map(function ($item) {
                return ['id' => $item['id'], 'v' => $item['v'] * 2];
            })
            ->filter(function ($item) {
                return $item['v'] > 4;
            })
            ->sortBy('id');
        $sink += count($result);
    }
}

function bench_case_pipeline_full_chain(int $n): void
{
    static $sink = 0;
    $container = new BenchPsrContainer();
    for ($i = 0; $i < $n; ++$i) {
        // 全链 = 构造 + send + through（closure x2 + 字符串容器管道）+ then
        $result = (new Pipeline($container))
            ->send('payload')
            ->through(
                function ($passable, $next) {
                    return $next($passable.'-c1');
                },
                function ($passable, $next) {
                    return $next($passable.'-c2');
                },
                'BenchPipelinePipe'
            )
            ->then(function ($passable) {
                return $passable.'-end';
            });
        $sink += strlen((string) $result);
    }
}
