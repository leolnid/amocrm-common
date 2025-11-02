<?php

namespace App\Enums\Concerns;

use Spatie\LaravelData\Casts\Cast;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;

trait CastToData
{
    public static function dataCastUsing(...$arguments): Cast
    {
        $class = static::class;

        return new class($class) implements Cast
        {
            public function __construct(private readonly string $class) {}

            public function cast(DataProperty $property, mixed $value, array $properties, CreationContext $context): mixed
            {
                if ($this->class::hasValue($value)) {
                    return $this->class::fromValue($value);
                }

                return $this->class::Unknown();
            }
        };
    }
}
