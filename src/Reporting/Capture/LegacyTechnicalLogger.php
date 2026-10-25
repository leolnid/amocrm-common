<?php
declare(strict_types=1);
namespace Leolnid\Common\Reporting\Capture;
use Monolog\Logger;
final class LegacyTechnicalLogger
{
    public function __invoke(array $config): Logger
    {
        $channel=$config['reporting_channel']??'legacy';
        if (!is_string($channel)||!preg_match('/^[A-Za-z0-9_.-]{1,64}$/D',$channel)) throw new \InvalidArgumentException('Invalid legacy technical channel');
        return new Logger('technical-error-legacy',[new LegacyTechnicalHandler(static function(?\Throwable $error)use($channel):void {app(LaravelErrorReporter::class)->reportLegacy($error,$channel);})]);
    }
}
