<?php

namespace App\Enums\Concerns;

use App\Enums\Concerns\Attributes\Property;
use Exception;
use Illuminate\Support\Collection;
use ReflectionClass;

trait GetAttributeValue
{
    /**
     * @throws Exception
     */
    public function getPropertyValue(string $name, $default = null)
    {
        return static::getAttributes($this)
            ->first(fn (Property $el) => $el->name === $name)
            ?->value ?: $default;
    }

    /**
     * @template T
     *
     * @param  class-string<T>  $class
     * @return Collection<int, T>
     *
     * @throws Exception
     */
    protected static function getAttributes(self $value, string $class = Property::class): Collection
    {
        $reflection = new ReflectionClass($value);
        $constReflection = $reflection->getReflectionConstant($value->name);
        $attributes = $constReflection->getAttributes($class);

        return collect($attributes)
            ->map(fn ($el) => $el->newInstance());
    }

    /**
     * @throws Exception
     */
    public function getPropertyValues(string $name): Collection
    {
        return static::getAttributes($this)
            ->filter(fn (Property $el) => $el->name === $name)
            ->map(fn (Property $el) => $el->value);
    }
}
