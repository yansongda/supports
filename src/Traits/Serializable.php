<?php

declare(strict_types=1);

namespace Yansongda\Supports\Traits;

trait Serializable
{
    public function __serialize(): array
    {
        /* @phpstan-ignore-next-line */
        if (method_exists($this, 'toArray')) {
            return $this->toArray();
        }

        return [];
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public function __unserialize(array $data): void
    {
        $this->unserializeArray($data);
    }

    public function __toString(): string
    {
        return $this->toJson();
    }

    public function serialize(): ?string
    {
        return serialize($this);
    }

    public function unserialize(string $data): void
    {
        unserialize($data);
    }

    public function toJson(int $option = JSON_UNESCAPED_UNICODE): string
    {
        return json_encode($this->__serialize(), $option);
    }

    /**
     * @return array<array-key, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->__serialize();
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public function unserializeArray(array $data): self
    {
        foreach ($data as $key => $item) {
            /* @phpstan-ignore-next-line */
            if (method_exists($this, 'set')) {
                $this->set(strval($key), $item);
            }
        }

        return $this;
    }
}
