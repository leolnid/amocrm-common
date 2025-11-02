<?php

namespace App\Enums\Concerns;

use Exception;

trait AsSource
{
    /**
     * @throws Exception
     */
    public function getContent(string $prop)
    {
        return match ($prop) {
            'key' => $this->key,
            'value' => $this->value,
            'description' => $this->description,
            'label' => $this->label(),
            default => $this->getPropertyValue($prop),
        };
    }
}
