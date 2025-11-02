<?php

namespace App\Enums\Concerns;

use Exception;

trait Colorable
{
    /**
     * @throws Exception
     */
    public function color(): string
    {
        return $this?->getPropertyValue('color');
    }
}
