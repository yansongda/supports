<?php

declare(strict_types=1);

/**
 * typephp 编译冒烟入口（T2.1）。
 *
 * 约束（必须持续满足）：
 * - 零顶层可执行语句：本文件只允许 declare/use/const/函数与类定义，
 *   全部可执行代码位于 main()（tpc bin 模式入口）及其调用的函数内；
 * - 不使用 typephp 特有语法（std 命名空间前缀、Type 静态包装、#[ArrayDef] 属性等），纯 Zend PHP 语义；
 * - 随机值（Str::random/Str::uuidV4）只输出长度/字符集/格式布尔判定，不输出原始值，
 *   以保证 Zend(PHP 8.3) 与 tpc(PHP 8.5) 双运行时输出逐行可比（expected.txt 为 Zend 基线）；
 * - 每个断言点带 SMOKE_<区>_NN: 前缀，便于双运行时 diff 归因。
 */

use Psr\Container\ContainerInterface;
use Yansongda\Supports\Arr;
use Yansongda\Supports\Collection;
use Yansongda\Supports\Config;
use Yansongda\Supports\Pipeline;
use Yansongda\Supports\Str;
use Yansongda\Supports\Traits\Arrayable;

/**
 * PSR-11 容器 stub：仅注册字符串管道类 SmokePipelinePipe，供 Pipeline 字符串管道解析路径使用。
 */
class SmokePsrContainer implements ContainerInterface
{
    public function get(string $id)
    {
        if ('SmokePipelinePipe' === $id) {
            return new SmokePipelinePipe();
        }

        throw new \RuntimeException('Service not found: '.$id);
    }

    public function has(string $id): bool
    {
        return 'SmokePipelinePipe' === $id;
    }
}

/**
 * Pipeline 字符串管道目标类（经容器 stub 解析，默认方法 handle）。
 */
class SmokePipelinePipe
{
    public function handle(mixed $passable, \Closure $next, string $prefix = 'pipe'): mixed
    {
        return $next($prefix.':'.$passable);
    }
}

/**
 * 使用 Arrayable trait 的示例类：name/tags 走属性直读分支，age 走 getter 分发分支
 * （getAge 故意 +1，便于从输出确认 getter 分发路径真实生效）。
 */
class SmokeArrayableUser
{
    use Arrayable;

    protected string $name = 'yansongda';

    protected int $age = 28;

    /** @var array<int, string> */
    protected array $tags = ['php', 'swoole'];

    public function getAge(): int
    {
        return $this->age + 1;
    }
}

function smoke_out(string $label, mixed $value): void
{
    echo $label.': '.var_export($value, true).PHP_EOL;
}

function smoke_json(string $label, mixed $value): void
{
    echo $label.': '.json_encode($value).PHP_EOL;
}

function smoke_arr(): void
{
    $arr = ['user' => ['name' => 'yansongda', 'age' => 28]];

    smoke_out('SMOKE_ARR_01', Arr::get($arr, 'user.name'));
    smoke_out('SMOKE_ARR_02', Arr::get($arr, 'user.email', 'none'));

    $arr2 = [];
    Arr::set($arr2, 'user.name', 'support');
    smoke_out('SMOKE_ARR_03', $arr2);

    smoke_out('SMOKE_ARR_04', [Arr::has($arr2, 'user.name'), Arr::has($arr2, 'user.email')]);

    $arr3 = ['a' => 1, 'b' => ['c' => 2, 'd' => 3]];
    Arr::forget($arr3, 'a');
    Arr::forget($arr3, 'b.c');
    smoke_out('SMOKE_ARR_05', $arr3);

    // 嵌套键删除后残留空数组：json_encode 输出 [] 而非 {}（T0.5 已钉住的 PHP 原生行为）
    smoke_json('SMOKE_ARR_06', Arr::except($arr2, 'user.name'));

    $m1 = ['a' => 1, 'list' => [1, 2], 'nested' => ['x' => 1]];
    $m2 = ['b' => 2, 'list' => [2, 3], 'nested' => ['y' => 2]];
    smoke_out('SMOKE_ARR_07', Arr::merge($m1, $m2));

    smoke_out('SMOKE_ARR_08', Arr::dot(['user' => ['name' => 'a', 'tags' => ['x']]]));

    smoke_out('SMOKE_ARR_09', Arr::collapse([[1, 2], new Collection([3, 4]), 'skip', [5]]));

    smoke_out('SMOKE_ARR_10', [Arr::isAssoc(['a' => 1]), Arr::isAssoc([1, 2])]);

    smoke_out('SMOKE_ARR_11', Arr::camelCaseKey(['user_name' => 'a', 'list' => ['item_key' => 1], 3 => 'num']));

    // T1.4 键位赋值改写版的最终验收点：嵌套 assoc+list 混合结构（T0.5 PAT_FOREACH_REF 差异场景）
    $sr = ['b' => ['d' => 2, 'a' => 1], 'a' => [3, 1, 2], 'c' => ['z' => ['b' => 2, 'a' => 1]]];
    smoke_json('SMOKE_ARR_12', Arr::sortRecursive($sr));
}

function smoke_str(): void
{
    smoke_out('SMOKE_STR_01', Str::snake('userName'));
    smoke_out('SMOKE_STR_02', Str::studly('user_name-id'));
    smoke_out('SMOKE_STR_03', Str::camel('user_name'));
    smoke_out('SMOKE_STR_04', Str::length('你好'));

    // charsArray 函数级 static 路径：'°' 映射 '0'；'@' 转换为 '-at-'
    // （历史坑：charsArray 数字字符串键 '0'-'9' 被 PHP 规范化为 int 键，strict_types=1 下
    // str_replace 收到 int 抛 TypeError，已由 fix(str) 以 (string) $key 修复并重生成基线）
    smoke_out('SMOKE_STR_05', Str::slug('Hello_World 5° C @ cafe'));

    // charsArray 路径：'é'/'à'/'ü' 分别映射 'e'/'a'/'u'
    smoke_out('SMOKE_STR_06', Str::ascii('déjà ü'));

    // languageSpecificCharsArray 函数级 static 路径（de 语种替换先于 charsArray 循环执行）
    smoke_out('SMOKE_STR_07', Str::ascii('schöne änderung', 'de'));

    $random = Str::random(32);
    smoke_out('SMOKE_STR_08', [strlen($random) === 32, 1 === preg_match('/^[A-Za-z0-9]{32}$/', $random)]);

    $uuid = Str::uuidV4();
    smoke_out('SMOKE_STR_09', [
        strlen($uuid) === 36,
        '-' === substr($uuid, 8, 1) && '-' === substr($uuid, 13, 1) && '-' === substr($uuid, 18, 1) && '-' === substr($uuid, 23, 1),
        '4' === substr($uuid, 14, 1),
        in_array(substr($uuid, 19, 1), ['8', '9', 'a', 'b'], true),
    ]);
}

function smoke_collection(): void
{
    $col = new Collection(['name' => 'yansongda', 'age' => 28, 'tags' => ['php', 'swoole']]);

    smoke_out('SMOKE_COL_01', $col->map(function ($item) {
        return is_int($item) ? $item * 2 : $item;
    })->all());

    smoke_out('SMOKE_COL_02', $col->filter(function ($item) {
        return !is_array($item);
    })->all());

    // sortBy('id') 走 valueRetriever 的 data_get 路径（Functions.php 覆盖）
    $sorted = (new Collection([['id' => 3, 'n' => 'c'], ['id' => 1, 'n' => 'a'], ['id' => 2, 'n' => 'b']]))->sortBy('id');
    smoke_json('SMOKE_COL_03', $sorted->toArray());

    $col2 = new Collection([]);
    $col2->set('user.name', 'support');
    smoke_out('SMOKE_COL_04', $col2->get('user.name'));
    smoke_out('SMOKE_COL_05', $col2->get('user.email', 'none'));
    // ArrayAccess 语义：offsetExists 经 Accessable -> Collection::get(Arr::get)，非 getter 解析
    smoke_out('SMOKE_COL_06', [isset($col2['user']), isset($col2['nope'])]);

    smoke_out('SMOKE_COL_07', count($col));
    smoke_json('SMOKE_COL_08', $col);

    $exceptTarget = new Collection(['a' => 1, 'b' => 2, 'c' => 3]);
    // except 两形态：数组形态（T1.1 变参化后兼容扁平单数组）与变参形态
    smoke_json('SMOKE_COL_09', $exceptTarget->except(['a', 'b'])->all());
    smoke_json('SMOKE_COL_10', $exceptTarget->except('a', 'c')->all());

    // toString -> toQueryString 更名后的运行期行为（T1.1）
    smoke_out('SMOKE_COL_11', $exceptTarget->toQueryString());

    smoke_json('SMOKE_COL_12', $col->toArray());

    // Config 继承 + Collection 构造函数点语法键路径（Arr::set 展开）
    $config = new Config(['app.name' => 'supports', 'app.debug' => true]);
    smoke_out('SMOKE_COL_13', [$config instanceof Collection, $config instanceof Config, $config->get('app.name')]);
    $config->set('app.debug', false);
    smoke_out('SMOKE_COL_14', $config->get('app.debug'));

    // Arrayable 示例类：期望 age=29（getter 分发），name/tags 为属性直读
    smoke_json('SMOKE_COL_15', (new SmokeArrayableUser())->toArray());
}

function smoke_pipeline(): void
{
    $container = new SmokePsrContainer();

    smoke_out('SMOKE_PIPE_01', [$container->has('SmokePipelinePipe'), $container->has('Nope')]);

    // closure 管道 + through 变参形态（T1.2 改造后签名）
    $result = (new Pipeline($container))
        ->send('start')
        ->through(function ($passable, $next) {
            return $next($passable.'.c1');
        }, function ($passable, $next) {
            return $next($passable.'.c2');
        })
        ->then(function ($passable) {
            return $passable.'.end';
        });
    smoke_out('SMOKE_PIPE_02', $result);

    // through 数组形态（单数组实参展开为多管道）
    $result2 = (new Pipeline($container))
        ->send('x')
        ->through([
            function ($passable, $next) {
                return $next($passable.'+a');
            },
            function ($passable, $next) {
                return $next($passable.'+b');
            },
        ])
        ->then(function ($passable) {
            return $passable.'!';
        });
    smoke_out('SMOKE_PIPE_03', $result2);

    // 字符串管道经容器 stub 解析（无参数形态）
    $result3 = (new Pipeline($container))
        ->send('s')
        ->through('SmokePipelinePipe')
        ->then(function ($passable) {
            return $passable.'.done';
        });
    smoke_out('SMOKE_PIPE_04', $result3);

    // 字符串管道经容器 stub 解析（带参数形态 pipe:para）
    $result4 = (new Pipeline($container))
        ->send('t')
        ->through('SmokePipelinePipe:PP')
        ->then(function ($passable) {
            return $passable.'.end';
        });
    smoke_out('SMOKE_PIPE_05', $result4);
}

function main(): void
{
    smoke_arr();
    smoke_str();
    smoke_collection();
    smoke_pipeline();
}
