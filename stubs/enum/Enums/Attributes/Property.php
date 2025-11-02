<?php

namespace App\Enums\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
class Property
{
    public function __construct(public string $name, public mixed $value = null) {}
}
