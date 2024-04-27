<?php

namespace Leolnid\Common\Exceptions;

use Exception;
use Throwable;

class SalesbotRunException extends Exception
{
    public readonly int $salesbotId;
    public readonly int $leadId;

    public function __construct(int $salesbotId, int $leadId, ?Throwable $previous = null)
    {
        parent::__construct("Ошибка при запуске salesbot", previous: $previous);
        $this->salesbotId = $salesbotId;
        $this->leadId = $leadId;
    }
}
