<?php
namespace Leolnid\Common\Reporting\Daily;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;
class TelemetryDailyMetrics
{
 private function coverage(?string $from,DateTimeImmutable $start,DateTimeImmutable $end,bool $available): array
 {
  if(!$available||!is_string($from)||!preg_match('/^\d{4}-\d{2}-\d{2}T/',$from))return ['state'=>'unknown','from_utc'=>null];
  try {$date=new DateTimeImmutable($from);}catch(Throwable){return ['state'=>'unknown','from_utc'=>null];}
  return ['state'=>$date>=$end?'unavailable':($date>$start?'partial':'complete'),'from_utc'=>$date->setTimezone(new DateTimeZone('UTC'))->format('c')];
 }
 private function scan(string $path,callable $consume): bool
 {
  if(!is_file($path))return false;
  $f=fopen($path,'rb');if(!$f)return false;$read=0;$max=(int)config('app_reports.max_scan_bytes',8388608);
  try {while(($line=fgets($f,8192))!==false){$read+=strlen($line);if($read>$max||!str_ends_with($line,"\n"))return false;$consume($line);}return true;}
  finally {fclose($f);}
 }
 public function collect(DateTimeImmutable $start,DateTimeImmutable $end): array
 {
  $dir=storage_path('logs/business-activity');
  $lossProof=TelemetryLossLedger::collect($start,$end);
  $coverage=$this->coverage(config('app_reports.business_coverage_from_utc'),$start,$end,(bool)config('app_reports.business_telemetry_enabled')&&is_dir($dir));
  $cliCatalog=config('app_reports.business_cli_commands',[]);$cliCounts=['batch'=>['started'=>0,'completed'=>0,'failed'=>0],'mail_poll'=>['started'=>0,'completed'=>0,'failed'=>0]];$lastMail=null;
  $cliCoverage=$cliCatalog?$this->coverage(config('app_reports.business_cli_coverage_from_utc'),$start,$end,(bool)config('app_reports.business_cli_telemetry_enabled')&&is_dir($dir)):['state'=>'not_applicable','from_utc'=>null];
  $counts=array_fill_keys(['accepted','rejected','processed','failed'],0);$seen=[];$last=null;$complete=true;$invalid=0;$issues=[];
  foreach(array_unique([$start->format('Y-m-d'),$end->modify('-1 second')->format('Y-m-d')]) as $day){
   if($coverage['from_utc']!==null&&new DateTimeImmutable($day.'T00:00:00+00:00 +1 day')<=new DateTimeImmutable($coverage['from_utc']))continue;
   $file=$dir.'/'.$day.'.jsonl';$marker=$file.'.state.json';$state=is_file($marker)?json_decode((string)file_get_contents($marker,false,null,0,65536),true):null;$records=0;
   $scanned=$this->scan($file,function($line)use(&$counts,&$seen,&$last,&$invalid,&$records,&$cliCounts,&$lastMail,$cliCatalog,$start,$end){
    $row=json_decode($line,true);if(!is_array($row)||!isset($row['event'],$row['occurred_at_utc'],$row['correlation_id'])){$invalid++;return;}
    if((!array_key_exists($row['event'],$counts)&&!($row['event']==='received'&&is_string($row['context']??null)&&str_starts_with($row['context'],'http:received:')))||!preg_match('/^[a-f0-9]{32}$/D',$row['correlation_id'])){$invalid++;return;}
    try {$at=new DateTimeImmutable($row['occurred_at_utc']);}catch(Throwable){$invalid++;return;}
    $records++;
    if($at<$start||$at>=$end)return;
    $context=$row['context']??null;
    if(is_string($context)&&str_starts_with($context,'cli:')){
     if(!preg_match('/^cli:(started|finished):(batch|mail_poll):([A-Za-z0-9:_-]{1,96})$/D',$context,$m)||($cliCatalog[$m[3]]??null)!==$m[2]||($m[1]==='started'&&$row['event']!=='accepted')||($m[1]==='finished'&&!in_array($row['event'],['processed','failed'],true))){$invalid++;return;}
     $key='cli:'.$m[1].':'.$row['correlation_id'];if(isset($seen[$key]))return;if(count($seen)>=100000){$invalid++;return;}$seen[$key]=true;
     $cliCounts[$m[2]][$m[1]==='started'?'started':($row['event']==='processed'?'completed':'failed')]++;
     if($m[2]==='mail_poll'&&$m[1]==='finished'&&$row['event']==='processed'&&($lastMail===null||$at>new DateTimeImmutable($lastMail)))$lastMail=$at->format('c');
     return;
    }
    $key=$row['event'].':'.$row['correlation_id'];if(isset($seen[$key]))return;
    if(count($seen)>=100000){$invalid++;return;}$seen[$key]=true;$counts[$row['event']]=($counts[$row['event']]??0)+1;
    if($last===null||$at>new DateTimeImmutable($last))$last=$at->format('c');
   });
   clearstatcache(true,$file);$markerAfter=is_file($marker)?json_decode((string)file_get_contents($marker,false,null,0,65536),true):null;
   $dayStart=new DateTimeImmutable($day.'T00:00:00+00:00');$segmentStart=max($start,$dayStart);if($coverage['from_utc']!==null)$segmentStart=max($segmentStart,new DateTimeImmutable($coverage['from_utc']));$segmentEnd=min($end,$dayStart->modify('+1 day'));
   $first=max(0,(int)floor(($segmentStart->getTimestamp()-$dayStart->getTimestamp())/60));$lastMinute=min(1440,(int)ceil(($segmentEnd->getTimestamp()-$dayStart->getTimestamp())/60));$ticks=$state['heartbeat_minutes']??'';$tickProof=is_string($ticks)&&preg_match('/^[01]{1440}$/D',$ticks)&&substr_count(substr($ticks,$first,$lastMinute-$first),'1')===$lastMinute-$first;
   $proved=$tickProof&&$scanned&&is_array($state)&&$state===$markerAfter&&is_file($file)&&($state['bytes']??-1)===filesize($file)&&($state['records']??-1)===$records&&(($lossProof['fully_classified']??false)||($state['losses']??1)===0)&&!($state['capped']??false);
   if(!$proved)$issues[]=$day.':missing_invalid_capped_or_marker_mismatch';
   $complete=$complete&&$proved;
  }
  $losses=$lossProof['losses'];
  $businessReliable=$complete&&!$invalid&&$losses===0&&$end->getTimestamp()>=time()-604800;
  if(!$businessReliable&&$coverage['state']==='complete')$coverage['state']='partial';
  if(!$businessReliable&&$cliCoverage['state']==='complete')$cliCoverage['state']='partial';
  $observed=$counts;
  if(!$businessReliable||in_array($coverage['state'],['unknown','unavailable'],true))$counts=array_fill_keys(array_keys($counts),null);
  $errorPath=(string)config('logging.channels.single.path');$sourceConfirmed=config('logging.channels.single.driver')==='single'&&!config('logging.channels.single.tap')&&!config('logging.channels.single.formatter');
  $errorCoverage=$this->coverage(config('app_reports.error_coverage_from_utc'),$start,$end,is_file($errorPath)&&$sourceConfirmed);
  $errorSeen=[];$tests=0;$errorInvalid=0;$firstLog=null;
  $errorComplete=$this->scan($errorPath,function($line)use(&$errorSeen,&$tests,&$errorInvalid,&$firstLog,$start,$end){
   if($firstLog===null&&preg_match('/^\[([0-9-]{10} [0-9:]{8})\]/',$line,$stamp)){try{$firstLog=new DateTimeImmutable($stamp[1],new DateTimeZone((string)config('app.timezone','UTC')));}catch(Throwable){}}
   if(!preg_match('/"event_kind"\s*:\s*"application_error"/',$line))return;
   if(!preg_match('/(\{.*\})\s*$/',$line,$match)){$errorInvalid++;return;}
   $row=json_decode($match[1],true);if(!is_array($row)||!isset($row['occurred_at_utc'],$row['correlation_id'])){$errorInvalid++;return;}
   try {$at=new DateTimeImmutable($row['occurred_at_utc']);}catch(Throwable){$errorInvalid++;return;}
   if($at<$start||$at>=$end)return;
   if($row['is_test']??false){$tests++;return;}
   if(count($errorSeen)<100000)$errorSeen[$row['correlation_id']]=true;else $errorInvalid++;
  });
  $coveredStart=$errorCoverage['from_utc']===null?$start:max($start,new DateTimeImmutable($errorCoverage['from_utc']));
  $errorReliable=$errorComplete&&!$errorInvalid&&$firstLog!==null&&$firstLog<=$coveredStart;
  if(!$errorReliable&&$errorCoverage['state']==='complete')$errorCoverage['state']='partial';
  return ['business_cli'=>['coverage'=>$cliCoverage,'expected_units'=>array_values(array_unique($cliCatalog)),'observed_counts'=>$cliCounts,'counts'=>$cliCoverage['state']==='complete'?$cliCounts:null,'last_successful_mail_check_utc'=>$lastMail,'units'=>['batch'=>'technical named command runs','mail_poll'=>'mailbox checks, not incoming requests or number of emails']],'source'=>'sanitized lifecycle telemetry','coverage'=>$coverage,'counts'=>$counts,'observed_counts'=>$observed,'pending_now'=>null,'last_genuine_activity_utc'=>$last,'segment_issues'=>$issues,'heartbeat_resolution_seconds'=>60,'telemetry_invalid_records'=>$invalid,'telemetry_write_losses'=>$losses,'telemetry_loss_coverage'=>$lossProof,'errors'=>['source'=>'logging.channels.single','source_driver_confirmed'=>$sourceConfirmed,'coverage'=>$errorCoverage,'count'=>!$errorReliable||in_array($errorCoverage['state'],['unknown','unavailable'],true)?null:count($errorSeen),'observed_count'=>count($errorSeen),'invalid_records'=>$errorInvalid,'known_tests_excluded'=>$tests]];
 }
}
