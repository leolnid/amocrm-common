<?php
declare(strict_types=1);
namespace Leolnid\Common\Reporting\Daily;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\Cache;
use Throwable;
use Leolnid\Common\Reporting\StoragePolicy;
/** Counters belong to the report's Moscow calendar day; legacy losses have no invented date. */
final class TelemetryLossLedger
{
 private const TTL=3888000;
 private static function prefix():string
 {
  $project=config('app_errors.project');
  if(!is_string($project)||!preg_match('/^[A-Za-z0-9_.-]{1,64}$/D',$project))throw new \RuntimeException('Missing telemetry project namespace');
  return 'reporting:telemetry-loss:v2:'.substr(hash('sha256',$project),0,24);
 }
 private static function validEpoch(mixed $raw):?DateTimeImmutable
 {
  if(!is_string($raw)||!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\+00:00|Z)$/D',$raw))return null;
  try{return new DateTimeImmutable($raw);}catch(Throwable){return null;}
 }
 public static function initialize(?DateTimeImmutable $at=null):bool
 {
  try{
   $at=($at??new DateTimeImmutable('now',new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('UTC'));$prefix=self::prefix();$legacy=Cache::get('business-telemetry-losses',0);
   if(!is_int($legacy)||$legacy<0)throw new \RuntimeException('Invalid legacy loss counter');
   Cache::add($prefix.':epoch',$at->format('c'),self::TTL);$epoch=Cache::get($prefix.':epoch');if(self::validEpoch($epoch)===null)throw new \RuntimeException('Invalid loss epoch');
   Cache::add($prefix.':legacy-unattributed',$legacy>0,self::TTL);$unattributed=Cache::get($prefix.':legacy-unattributed');if(!is_bool($unattributed))throw new \RuntimeException('Invalid legacy loss marker');
   Cache::put($prefix.':epoch',$epoch,self::TTL);Cache::put($prefix.':legacy-unattributed',$unattributed||$legacy>0,self::TTL);
   return true;
  }catch(Throwable){return false;}
 }
 public static function record(?DateTimeImmutable $at=null):bool
 {
  try{
   $at=$at??new DateTimeImmutable('now',new DateTimeZone('UTC'));if(!self::initialize($at)){self::markUnavailable($at);return false;}
   $key=self::prefix().':'.$at->setTimezone(new DateTimeZone('Europe/Moscow'))->format('Y-m-d');Cache::add($key,0,self::TTL);$value=Cache::increment($key);
   if(!is_int($value)||$value<1){self::markUnavailable($at);return false;}return true;
  }catch(Throwable){self::markUnavailable($at??new DateTimeImmutable('now',new DateTimeZone('UTC')));return false;}
 }
 private static function markUnavailable(DateTimeImmutable $at):void
 {
  try{
   $dir=storage_path('logs/business-activity');$policy=new StoragePolicy((int)config('app_reports.storage.file_mode',0640),(int)config('app_reports.storage.directory_mode',0750));$policy->ensureDirectory($dir);
   $file=$policy->open($dir.'/'.$at->setTimezone(new DateTimeZone('Europe/Moscow'))->format('Y-m-d').'.loss-ledger-gap');
   try{if(!flock($file,LOCK_EX)||!ftruncate($file,0)||fwrite($file,"1\n")!==2||!fflush($file)||!fsync($file))return;}finally{flock($file,LOCK_UN);fclose($file);}
  }catch(Throwable){error_log('Telemetry loss day marker unavailable');}
 }
 public static function collect(DateTimeImmutable $start,DateTimeImmutable $end):array
 {
  try{
   if($end<=$start||$end->getTimestamp()-$start->getTimestamp()>2678400)throw new \RuntimeException('Invalid loss period');
   $prefix=self::prefix();$raw=Cache::get($prefix.':epoch');$epoch=self::validEpoch($raw);$legacy=Cache::get('business-telemetry-losses',0);$unattributed=Cache::get($prefix.':legacy-unattributed',false);
   if(!is_int($legacy)||$legacy<0||!is_bool($unattributed)||($raw!==null&&$epoch===null))throw new \RuntimeException('Invalid loss metadata');
   $exact=$epoch!==null&&$start>=$epoch;
   if(!$exact&&($unattributed||$legacy>0))return ['losses'=>null,'fully_classified'=>false,'epoch_utc'=>$epoch?->format('c'),'legacy_unattributed'=>true];
   $total=0;$day=$start->setTimezone(new DateTimeZone('Europe/Moscow'))->setTime(0,0);
   while($day<$end){$dayName=$day->format('Y-m-d');if(is_file(storage_path('logs/business-activity/'.$dayName.'.loss-ledger-gap')))throw new \RuntimeException('Loss ledger unavailable for report day');$value=Cache::get($prefix.':'.$dayName,0);if(!is_int($value)||$value<0||$total>PHP_INT_MAX-$value)throw new \RuntimeException('Invalid loss day counter');$total+=$value;$day=$day->modify('+1 day');}
   return ['losses'=>$total,'fully_classified'=>$exact,'epoch_utc'=>$epoch?->format('c'),'legacy_unattributed'=>$unattributed||$legacy>0];
  }catch(Throwable){return ['losses'=>null,'fully_classified'=>false,'epoch_utc'=>null,'legacy_unattributed'=>true];}
 }
}
