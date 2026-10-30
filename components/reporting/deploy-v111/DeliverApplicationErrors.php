<?php
namespace App\Console\Commands;

use App\Services\ErrorReporting\ErrorOutbox;
use Leolnid\Common\Reporting\Capture\LaravelDeliverApplicationErrors;

final class DeliverApplicationErrors extends LaravelDeliverApplicationErrors
{
    public function handle(ErrorOutbox $outbox): int
    {
        return $this->deliverOutbox($outbox);
    }
}
