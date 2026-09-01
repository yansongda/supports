<?php

declare(strict_types=1);

namespace Yansongda\Supports\Traits;

use Yansongda\Supports\Str;

trait Accessable
{
    public function __get(string $key): mixed
    {
        return $this->get($key);
    }

    public function __isset(string $key): bool
    {
        return !is_null($this->get($key));
    }

    public function __unset(string $key): void
    {
        $this->offsetUnset($key);
    }

    public function __set(string $key, mixed $value): void
    {
        $this->set($key, $value);
    }

    /**
     * 取值：优先调用本类的 `get{Studly($key)}` 方法（getter 解析设计，勿改：
     * 下游如 yansongda/pay 的 AbstractConfig 依赖该特性将配置键映射到 getter），
     * 未命中时返回 $default。
     *
     * 注意：键名可能与既有方法产生映射碰撞（如 get('iterator') 会调用 getIterator()）。
     */
    public function get(?string $key = null, mixed $default = null): mixed
    {
        if (is_null($key)) {
            return method_exists($this, 'toArray') ? $this->toArray() : $default;
        }

        $method = 'get'.Str::studly($key);

        if (method_exists($this, $method)) {
            return $this->{$method}();
        }

        return $default;
    }

    /**
     * 赋值：若本类存在 `set{Studly($key)}` 方法则调用之（setter 解析设计，同 get，勿改）。
     */
    public function set(string $key, mixed $value): self
    {
        $method = 'set'.Str::studly($key);

        if (method_exists($this, $method)) {
            $this->{$method}($value);
        }

        return $this;
    }

    public function offsetExists(mixed $offset): bool
    {
        return !is_null($this->get($offset));
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->get($offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->set($offset, $value);
    }

    /**
     * 宿主类存在 forget() 方法时执行删除（如 Collection），否则为空实现（无通用存储可删）。
     */
    public function offsetUnset(mixed $offset): void
    {
        if (method_exists($this, 'forget')) {
            $this->forget($offset);
        }
    }
}
