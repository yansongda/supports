<?php

declare(strict_types=1);


/**
 * 边界模式自包含复刻 spike（零顶层可执行语句；不 require/引用 Yansongda 类）。
 * 每个模式输出 `PAT_*: <摘要>` 标记行，便于双运行时逐行 diff 与单模式归因。
 */

// 仿 src/Traits/Arrayable.php 的反射目标（带属性样例类）
class ReflectTarget
{
    public int $id = 0;

    protected string $name = 'demo';

    public function getName(): string
    {
        return $this->name;
    }
}

class ReflectTargetChild extends ReflectTarget {}

// 仿 src/Collection.php 的魔术接口集合类
class BoundaryCollection implements \ArrayAccess, \Countable, \IteratorAggregate, \JsonSerializable
{
    protected array $items = [];

    public function __construct(mixed $items = [])
    {
        foreach ($items as $key => $value) {
            $this->items[$key] = $value;
        }
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->items[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->items[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (null === $offset) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->items[$offset]);
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->items);
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function jsonSerialize(): mixed
    {
        return $this->items;
    }

    public function toArray(): array
    {
        return $this->items;
    }
}

function boundary_forget(array &$array, array $keys): void
{
    // 仿 src/Arr.php forget（:191 附近）：引用重绑定（$array = &$original; $array = &$array[$part];）+ continue 2
    $original = &$array;
    foreach ($keys as $key) {
        if (array_key_exists($key, $array)) {
            unset($array[$key]);

            continue;
        }
        $parts = explode('.', $key);
        // clean up before each pass
        $array = &$original;
        while (count($parts) > 1) {
            $part = array_shift($parts);
            if (isset($array[$part]) && is_array($array[$part])) {
                $array = &$array[$part];
            } else {
                continue 2;
            }
        }
        unset($array[array_shift($parts)]);
    }
}

function boundary_sort_recursive(array $array): array
{
    // 仿 src/Arr.php sortRecursive：foreach 引用 + ksort/sort
    foreach ($array as &$value) {
        if (is_array($value)) {
            $value = boundary_sort_recursive($value);
        }
    }
    $isAssoc = count(array_filter(array_keys($array), 'is_string')) > 0;
    if ($isAssoc) {
        ksort($array);
    } else {
        sort($array);
    }

    return $array;
}

function boundary_chars_array(): array
{
    // 仿 src/Str.php charsArray：函数级 static 未初始化声明 + isset 判定 + 大字面量数组
    static $charsArray;

    if (isset($charsArray)) {
        return $charsArray;
    }

    return $charsArray = [
        '0' => ['°', '₀', '۰', '０'],
        '1' => ['¹', '₁', '۱', '１'],
        '2' => ['²', '₂', '۲', '２'],
        '3' => ['³', '₃', '۳', '３'],
    ];
}

function pattern_static_cache(): void
{
    // 仿 src/Traits/Arrayable.php toArray：函数级 static 缓存 + ??= 短路 + ReflectionClass::getProperties + 动态属性读写
    static $cache = [];

    $t = new ReflectTargetChild();
    $t->{'id'} = 9;

    $properties = $cache[$t::class] ??= (new \ReflectionClass($t::class))->getProperties();

    $result = [];
    foreach ($properties as $item) {
        $k = $item->getName();
        $method = 'get'.ucfirst($k);

        $result[$k] = method_exists($t, $method) ? $t->{$method}() : $t->{$k};
    }

    // 第二次取缓存：??= 右侧不执行，两数组应为同一实例（=== 成立）
    $properties2 = $cache[$t::class] ??= (new \ReflectionClass($t::class))->getProperties();

    echo 'PAT_STATIC_CACHE: '.json_encode($result)
        .' cached='.var_export($properties === $properties2, true)
        .PHP_EOL;
}

function pattern_reference_rebind(): void
{
    // 覆盖：引用参数、$a = &$b、$a = &$b[$k] 嵌套重绑定、continue 2
    $array = [
        'top' => 1,
        'b' => ['c' => 2, 'd' => 3],
        'e' => ['f' => ['g' => 4]],
        'missing' => ['y' => 5],
    ];
    boundary_forget($array, ['top', 'b.c', 'e.f.g', 'missing.y.z']);

    echo 'PAT_REF_REBIND: '.json_encode($array).PHP_EOL;
}

function pattern_foreach_reference(): void
{
    // 覆盖：foreach ($arr as &$v) 引用遍历 + 递归 + ksort/sort
    $input = [
        'z' => ['b' => 2, 'a' => 1],
        'a' => ['d' => 4, 'c' => 3],
        'list' => [3, 1, 2],
    ];

    echo 'PAT_FOREACH_REF: '.json_encode(boundary_sort_recursive($input)).PHP_EOL;
}

function pattern_magic_interfaces(): void
{
    // 覆盖：ArrayAccess/Countable/IteratorAggregate/JsonSerializable + declared toArray() 豁免观察
    $c = new BoundaryCollection(['b' => 2, 'a' => 1]);
    $c['c'] = 3;
    $c[] = 4;
    $exists = isset($c['a']);
    $gone = isset($c['zz']);
    unset($c['b']);
    $count = count($c);

    $iterated = [];
    foreach ($c as $key => $value) {
        $iterated[] = $key.'='.$value;
    }

    echo 'PAT_MAGIC_IFACE: exists='.var_export($exists, true)
        .' gone='.var_export($gone, true)
        .' count='.$count
        .' iter='.implode(',', $iterated)
        .' json='.json_encode($c)
        .' toArray='.json_encode($c->toArray())
        .PHP_EOL;
}

function pattern_func_static(): void
{
    // 覆盖：函数级 static + isset 判定 + 大字面量（含 unicode）数组；二次调用走缓存短路
    $first = boundary_chars_array();
    $second = boundary_chars_array();
    $same = ($first === $second);

    echo 'PAT_FUNC_STATIC: count='.count($first)
        .' sample='.json_encode($first['0'])
        .' cached='.var_export($same, true)
        .PHP_EOL;
}

function main(): void
{
    pattern_static_cache();
    pattern_reference_rebind();
    pattern_foreach_reference();
    pattern_magic_interfaces();
    pattern_func_static();
}
