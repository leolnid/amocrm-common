<?php

namespace App\Enums\Concerns;

use App\Enums\Concerns\Attributes\Label;
use Exception;

trait Labelable
{
    use GetAttributeValue;

    /**
     * @throws Exception
     */
    public function label(): string
    {
        return self::getAttributes($this, Label::class)?->first()?->label
            ?? $this?->getPropertyValue('label')
            ?? $this?->description
            ?? $this?->key
            ?? '';
    }
}
