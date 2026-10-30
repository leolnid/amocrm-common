<?php
declare(strict_types=1);
namespace Leolnid\Common\Reporting\Capture;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\{Cache,Log};

abstract class LaravelDeliverApplicationErrors extends Command
{
    protected $signature='app-errors:deliver';
    protected $description='Retry sanitized application error notifications without business jobs';

    protected function deliverOutbox(LaravelErrorOutbox $outbox): int
    {
        $result=$outbox->drain();
        $this->line(json_encode($result,JSON_THROW_ON_ERROR));
        $review=array_intersect_key($result,array_fill_keys(['expired_requires_review','corrupt_requires_review','interrupted_requires_review','receipt_journal_requires_review'],true));
        $review=array_filter($review,static fn($count):bool=>is_int($count)&&$count>0);
        if ($review!==[]) {
            $key='app-errors:outbox-review:'.hash('sha256',json_encode($review,JSON_THROW_ON_ERROR));
            if (Cache::add($key,true,86400)) {
                Log::channel('single')->warning('Доставка уведомлений: сохранённые записи требуют ручной проверки; автоматические повторы для них остановлены',[
                    'event'=>'reporting_outbox_requires_review','command'=>'app-errors:deliver','review_counts'=>$review,
                    'action'=>'Проверить исторические записи error-notifications; не сбрасывать попытки и не повторять бизнес-задания',
                ]);
            }
        }
        // Old exhausted envelopes are review state, not a fresh command failure every five minutes.
        return self::SUCCESS;
    }
}
