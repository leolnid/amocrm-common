<?php
declare(strict_types=1);
namespace Leolnid\Common\Reporting\Capture;
use Monolog\{Level,LogRecord};
use Monolog\Handler\AbstractProcessingHandler;
final class LegacyTechnicalHandler extends AbstractProcessingHandler
{
    public function __construct(private \Closure $report) {parent::__construct(Level::Warning,true);}
    protected function write(LogRecord $record): void
    {
        $error=null;
        foreach (['exception','throwable','error'] as $key) if (($record->context[$key]??null) instanceof \Throwable) {$error=$record->context[$key];break;}
        // Never render, parse or forward the legacy free-text message/context payload.
        try {($this->report)($error);} catch (\Throwable) {error_log('Legacy technical observer unavailable');}
    }
}
