<?php

declare(strict_types=1);

namespace Yansongda\Supports\Traits;

trait Serializable
{
    public function __serialize(): array
    {
        if (method_exists($this, 'toArray')) {
            return $this->toArray();
        }

        return [];
    }

    public function __unserialize(array $data): void
    {
        $this->unserializeArray($data);
    }

    public function __toString(): string
    {
        return $this->toJson();
    }

    /**
     * toJson.
     */
    public function toJson(int $option = JSON_UNESCAPED_UNICODE): string
    {
        return json_encode($this->__serialize(), $option);
    }

    /**
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return $this->__serialize();
    }

    public function unserializeArray(array $data): void
    {
        foreach ($data as $key => $item) {
            if (method_exists($this, 'set')) {
                $this->set($key, $item);
            }
        }
    }
}
