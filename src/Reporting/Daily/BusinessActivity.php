<?php
namespace Leolnid\Common\Reporting\Daily;
use Throwable;
use Leolnid\Common\Reporting\StoragePolicy;
final class BusinessActivity
{
 public static function correlation(string $opaqueId): string {return substr(hash_hmac('sha256',$opaqueId,(string)config('app.key')),0,32);}
 private static function enabled(): bool
 {
  if(!config('app_reports.business_telemetry_enabled'))return false;
  $from=strtotime((string)config('app_reports.business_coverage_from_utc'));
  return $from!==false&&$from<=time();
 }
 public static function initializeDay(): void {if(self::enabled())self::append(null);}
 public static function heartbeat(): bool {return self::enabled()?self::append(null,true):false;}
 public static function record(string $event,string $correlation,string $context): bool
 {
  if(!self::enabled())return false;
  if(!in_array($event,['accepted','rejected','processed','failed'],true))return false;
  if(!preg_match('/^[a-f0-9]{32}$/D',$correlation)||!preg_match('/^[A-Za-z0-9_\\\\\/{}:.-]{1,160}$/D',$context))return false;
  return self::append(['event'=>$event,'correlation_id'=>$correlation,'context'=>$context,'occurred_at_utc'=>gmdate('c')]);
 }
 /** Explicit opt-in for a collector which has a native HTTP-begin event. Legacy record() stays unchanged. */
 public static function recordReceived(string $correlation,string $context):bool
 {
  if(!self::enabled()||!preg_match('/^[a-f0-9]{32}$/D',$correlation)||!preg_match('/^http:received:[A-Za-z0-9_:.-]{1,120}$/D',$context))return false;
  return self::append(['event'=>'received','correlation_id'=>$correlation,'context'=>$context,'occurred_at_utc'=>gmdate('c')]);
 }
 private static function append(?array $row,bool $heartbeat=false): bool
 {
  try {
   if(!TelemetryLossLedger::initialize())throw new \RuntimeException();
   $dir=storage_path('logs/business-activity');
   $policy=new StoragePolicy((int)config('app_reports.storage.file_mode',0640),(int)config('app_reports.storage.directory_mode',0750));$policy->ensureDirectory($dir);
   $file=$dir.'/'.gmdate('Y-m-d').'.jsonl';
   $f=$policy->open($file);if(!flock($f,LOCK_EX))throw new \RuntimeException();
   try {
    $size=fstat($f)['size'];$marker=$file.'.state.json';$existing=is_file($marker);
    $state=$existing?json_decode((string)file_get_contents($marker,false,null,0,65536),true):null;
    if(!is_array($state)||!isset($state['bytes'],$state['records'],$state['losses'])){$state=['bytes'=>0,'records'=>0,'losses'=>($existing||$size>0)?1:0];if($state['losses'])TelemetryLossLedger::record();}
    if($state['bytes']!==$size){$state['losses']++;TelemetryLossLedger::record();}
    if(($state['capped']??false))return false;
    if($row===null&&!$heartbeat&&$existing&&$state['bytes']===$size)return true;
    if($heartbeat){$ticks=$state['heartbeat_minutes']??str_repeat('0',1440);if(!is_string($ticks)||!preg_match('/^[01]{1440}$/D',$ticks)){$ticks=str_repeat('0',1440);$state['losses']++;TelemetryLossLedger::record();}$ticks[((int)gmdate('G'))*60+(int)gmdate('i')]='1';$state['heartbeat_minutes']=$ticks;}
    $line=$row===null?'':json_encode($row,JSON_THROW_ON_ERROR)."\n";
    $cap=max(4096,min(33554432,(int)config('app_reports.max_scan_bytes',8388608)));
    if($size+strlen($line)>$cap){$state['capped']=true;$state['losses']++;TelemetryLossLedger::record();$line='';}
    if($line!==''){fseek($f,0,SEEK_END);if(fwrite($f,$line)!==strlen($line))throw new \RuntimeException();fflush($f);fsync($f);$state['records']++;}
    $state['bytes']=fstat($f)['size'];$state['coverage_from_utc']=config('app_reports.business_coverage_from_utc');$state['updated_at_utc']=gmdate('c');
    $tmp=$marker.'.tmp-'.bin2hex(random_bytes(8));
    try {$policy->writeNew($tmp,json_encode($state,JSON_THROW_ON_ERROR));if(!rename($tmp,$marker))throw new \RuntimeException();}
    finally {if(is_file($tmp))@unlink($tmp);}
    if(!$existing){$cutoff=gmdate('Y-m-d',time()-86400*max(2,min(31,(int)config('app_reports.retention_days',14))));foreach(glob($dir.'/*.jsonl') as $old){if(preg_match('/^(\d{4}-\d{2}-\d{2})\.jsonl$/D',basename($old),$match)&&$match[1]<$cutoff){@unlink($old);if(is_file($old.'.state.json'))@unlink($old.'.state.json');}}}
    return !($state['capped']??false);
   } finally {flock($f,LOCK_UN);fclose($f);}
  } catch(Throwable) {error_log('Business telemetry write failed');TelemetryLossLedger::record();return false;}
 }
}
