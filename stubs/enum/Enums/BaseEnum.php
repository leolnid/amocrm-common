<?php

namespace App\Enums;

use App\Enums\Traits\AsSource;
use App\Enums\Traits\CastToData;
use App\Enums\Traits\Colorable;
use App\Enums\Traits\GetAttributeValue;
use App\Enums\Traits\Labelable;
use App\Enums\Traits\SelectTrait;
use ArrayAccess;
use BenSampo\Enum\Enum;
use JetBrains\PhpStorm\Deprecated;
use Spatie\LaravelData\Casts\Castable;

#[Deprecated('Рекомендуется использовать нативные Enum')]
abstract class BaseEnum extends Enum implements ArrayAccess, Castable
{
    use AsSource, CastToData, Colorable, GetAttributeValue, Labelable, SelectTrait, Traits\ArrayAccess;
}
