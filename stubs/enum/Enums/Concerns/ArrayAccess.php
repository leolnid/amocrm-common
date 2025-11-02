<?php

namespace App\Enums\Concerns;

use Exception;

trait ArrayAccess
{
    /**
     * @throws Exception
     */
    public function offsetExists(mixed $offset): bool
    {
        return ! empty($this->offsetGet($offset));
    }

    /**
     * @throws Exception
     */
    public function offsetGet(mixed $offset): mixed
    {
        return match ($offset) {
            'key' => $this->key,
            'value' => $this->value,
            'description' => $this->description,
            'label' => $this->label(),
            default => $this->getPropertyValue($offset),
        };
    }

    public function offsetSet(mixed $offset, mixed $value): void {}

    public function offsetUnset(mixed $offset): void {}
}
