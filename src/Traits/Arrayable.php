<?php

declare(strict_types=1);

namespace Yansongda\Supports\Traits;

use ReflectionClass;
use Yansongda\Supports\Str;

trait Arrayable
{
    public function toArray(): array
    {
        // 反射结果按类名缓存，避免每次调用都重建 ReflectionClass
        static $cache = [];

        $properties = $cache[static::class] ??= (new ReflectionClass(static::class))->getProperties();

        $result = [];

        foreach ($properties as $item) {
            $k = $item->getName();
            $method = 'get'.Str::studly($k);

            $result[Str::snake($k)] = method_exists($this, $method) ? $this->{$method}() : $this->{$k};
        }

        return $result;
    }
}
