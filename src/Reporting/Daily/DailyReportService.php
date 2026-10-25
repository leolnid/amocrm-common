<?php
declare(strict_types=1);
namespace Leolnid\Common\Reporting\Daily;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use Leolnid\Common\Reporting\Delivery\NotificationTransport;
use Leolnid\Common\Reporting\StoragePolicy;
use Throwable;

/** Generic daily delivery; application ports own metrics, probes and routing. */
class DailyReportService
{
    public function __construct(
        private readonly Closure $metrics,
        private readonly NotificationTransport $transport,
        private readonly Closure $databaseAvailable,
        private readonly Closure $health,
        private readonly Closure $route,
        private readonly Closure $queueDelivery,
        private readonly string $ledgerDirectory,
        private readonly string $outboxDirectory,
        private readonly string $project,
        private readonly string $displayName,
        private readonly string $server,
        private readonly StoragePolicy $storage = new StoragePolicy(),
    ) {}

    private function writeLedger(string $path, array $state): void
    {
        $temp=$path.'.tmp-'.bin2hex(random_bytes(8));
        try {
            $this->storage->writeNew($temp,json_encode($state,JSON_THROW_ON_ERROR));
            if (!rename($temp,$path)) throw new \RuntimeException('Daily ledger rename failed');
        } finally { if (is_file($temp)) unlink($temp); }
    }

    private function outboxState(): array
    {
        $dir=$this->outboxDirectory;
        $out=['pending'=>0,'expired_requires_review'=>0,'corrupt_requires_review'=>count(glob($dir.'/*.invalid')),'interrupted_requires_review'=>count(glob($dir.'/*.tmp-*'))];
        foreach (glob($dir.'/*.json') as $file) {
            $item=json_decode((string)file_get_contents($file),true);
            if (!is_array($item)||!isset($item['created_at'],$item['attempts'])) {$out['corrupt_requires_review']++;continue;}
            if ($item['created_at']<time()-86400||$item['attempts']>=20) $out['expired_requires_review']++;
            else $out['pending']++;
        }
        return $out;
    }

    public function period(?string $date=null): array
    {
        $tz=new DateTimeZone('Europe/Moscow');
        $day=$date??(new DateTimeImmutable('now',$tz))->modify('-1 day')->format('Y-m-d');
        $start=DateTimeImmutable::createFromFormat('!Y-m-d',$day,$tz);
        if (!$start||$start->format('Y-m-d')!==$day) throw new \InvalidArgumentException('Invalid report date');
        return [$start->setTimezone(new DateTimeZone('UTC')),$start->modify('+1 day')->setTimezone(new DateTimeZone('UTC')),$day];
    }

    public function run(?string $date=null,bool $dryRun=false): array
    {
        [$start,$end,$day]=$this->period($date);
        try {$metrics=($this->metrics)($start,$end);} catch (Throwable) {$metrics=['coverage'=>['state'=>'unknown'],'counts'=>array_fill_keys(['accepted','rejected','processed','failed'],null),'pending_now'=>null,'errors'=>['count'=>null]];}
        $metrics['notification_outbox']=$this->outboxState();
        try {$database=(bool)($this->databaseAvailable)();} catch (Throwable) {$database=false;}
        $health=($this->health)();
        $project=preg_match('/^[A-Za-z0-9_.-]{1,64}$/D',$this->project)?$this->project:'application';
        $server=preg_match('/^[A-Za-z0-9_.:-]{1,64}$/D',$this->server)?$this->server:'unknown';
        $text=CompactDailyFormatter::render($this->displayName!==''?$this->displayName:$project,['date'=>$day,'metrics'=>$metrics,'database_available'=>$database,'public_health'=>$health]);
        $result=['date'=>$day,'period_start_utc'=>$start->format('c'),'period_end_utc'=>$end->format('c'),'metrics'=>$metrics,'database_available'=>$database,'public_health'=>$health,'server'=>$server,'php_version'=>PHP_VERSION,'text'=>$text,'dry_run'=>$dryRun];
        if ($dryRun) {$result['status']='dry_run';return $result;}
        $route=($this->route)();
        if (!is_array($route)||($route['enabled']??false)!==true||($route['chat_id']??null)!=='-1001994065435'||!is_int($route['message_thread_id']??null)||$route['message_thread_id']<=0) {$result['status']='disabled_or_unrouted';return $result;}
        $this->storage->ensureDirectory($this->ledgerDirectory);
        $path=$this->ledgerDirectory.'/'.$day.'.json';
        $lock=$this->storage->open($path.'.lock');
        if (!flock($lock,LOCK_EX|LOCK_NB)) {fclose($lock);$result['status']='busy';return $result;}
        try {
            if (is_file($path)) {
                $old=json_decode((string)file_get_contents($path),true);
                $result['status']=is_array($old)&&in_array($old['state']??'', ['accepted','queued'],true)?'already_claimed':'requires_review';
                $result['previous_state']=$old['state']??'invalid';return $result;
            }
            $this->writeLedger($path,['state'=>'claimed','date'=>$day,'claimed_utc'=>gmdate('c')]);
            $payload=['chat_id'=>$route['chat_id'],'message_thread_id'=>$route['message_thread_id'],'text'=>$text,'parse_mode'=>'HTML','disable_web_page_preview'=>true];
            try {$receipt=$this->transport->send($payload);$state=['state'=>'accepted','date'=>$day,'receipt'=>$receipt];}
            catch (Throwable $error) {
                try {($this->queueDelivery)($payload,$error,$path);$state=['state'=>'queued','date'=>$day];}
                catch (Throwable) {$state=['state'=>'requires_review','date'=>$day];error_log('Daily report delivery requires review');}
            }
            $this->writeLedger($path,$state);$result['status']=$state['state'];$result['receipt']=$state['receipt']??null;return $result;
        } finally {flock($lock,LOCK_UN);fclose($lock);}
    }
}
