<?php

namespace App\Enums\Concerns;

trait SelectTrait
{
    public static function select(?string $default = null): array
    {
        return array_merge(
            (empty($default) ? [] : [null => $default]),
            collect(static::cases())
                ->mapWithKeys(fn($enum) => [$enum->value => method_exists($enum, 'label') ? $enum->label() : ($enum->description ?? $enum->name)])
                ->toArray(),
        );
    }
}
