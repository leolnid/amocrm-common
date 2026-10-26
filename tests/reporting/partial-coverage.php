<?php
declare(strict_types=1);
namespace Illuminate\Support\Facades {
    final class Cache {
        public static function get(string $key,mixed $default=null):mixed {
            $cache=$GLOBALS['daily26_fixture_cache']??[];
            return array_key_exists($key,$cache)?$cache[$key]:$default;
        }
    }
}
namespace {
require __DIR__.'/../../src/Reporting/Daily/TelemetryLossLedger.php';
require __DIR__.'/../../src/Reporting/Daily/TelemetryDailyMetrics.php';
require __DIR__.'/../../src/Reporting/Daily/CompactDailyFormatter.php';
use Leolnid\Common\Reporting\Daily\TelemetryDailyMetrics;
use Leolnid\Common\Reporting\Daily\CompactDailyFormatter;
function check(bool $condition,string $message):void { if(!$condition)throw new RuntimeException($message); }
function config(string $key,mixed $default=null):mixed { $v=$GLOBALS['daily26_fixture_config']??[];return array_key_exists($key,$v)?$v[$key]:$default; }
function storage_path(string $path=''):string { return rtrim((string)$GLOBALS['daily26_fixture_storage'],DIRECTORY_SEPARATOR).($path!==''?DIRECTORY_SEPARATOR.$path:''); }
function makeFixture(string $variant='gap5'):string {
    $base=realpath(sys_get_temp_dir()); if($base===false)throw new RuntimeException('temp root unavailable');
    $root=$base.DIRECTORY_SEPARATOR.'amr-daily-fixture-'.bin2hex(random_bytes(8));
    if(!mkdir($root,0700)||!chmod($root,0700))throw new RuntimeException('fixture create failed');
    $dir=$root.DIRECTORY_SEPARATOR.'logs'.DIRECTORY_SEPARATOR.'business-activity';
    mkdir($dir,0700,true);chmod($dir,0700);
    $gapDay='2026-10-25';$fullDay='2026-10-26';
    $ticks1=str_repeat('1',1440);$ticks2=str_repeat('1',1440);
    if(in_array($variant,['gap5','gap5_late_loss_epoch'],true))$ticks1=substr_replace($ticks1,'00000',1263,5);
    foreach([[$gapDay,$ticks1],[$fullDay,$ticks2]] as [$day,$ticks]){
        $file=$dir.DIRECTORY_SEPARATOR.$day.'.jsonl';file_put_contents($file,'');chmod($file,0600);
        $state=['bytes'=>0,'records'=>0,'losses'=>0,'heartbeat_minutes'=>$ticks,'coverage_from_utc'=>'2026-10-20T00:00:00Z','updated_at_utc'=>'2026-10-27T00:00:00Z','capped'=>false];
        if($variant==='marker_mismatch'&&$day===$gapDay)$state['bytes']=1;
        file_put_contents($file.'.state.json',json_encode($state,JSON_THROW_ON_ERROR));chmod($file.'.state.json',0600);
    }
    if($variant==='missing_file')unlink($dir.DIRECTORY_SEPARATOR.$gapDay.'.jsonl');
    $err=$root.DIRECTORY_SEPARATOR.'app.log';file_put_contents($err,'');chmod($err,0600);
    $GLOBALS['daily26_fixture_storage']=$root;
    $GLOBALS['daily26_fixture_config']=[
      'app_errors.project'=>'fixture-project',
      'app_reports.business_telemetry_enabled'=>true,
      'app_reports.business_coverage_from_utc'=>$variant==='partial_epoch'?'2026-10-26T05:00:00Z':'2026-10-20T00:00:00Z',
      'app_reports.business_cli_commands'=>[],
      'app_reports.business_cli_telemetry_enabled'=>false,
      'app_reports.business_cli_coverage_from_utc'=>null,
      'app_reports.max_scan_bytes'=>8192,
      'app_reports.error_coverage_from_utc'=>'2026-10-20T00:00:00Z',
      'app.timezone'=>'Europe/Moscow',
      'logging.channels.single.path'=>$err,
      'logging.channels.single.driver'=>'single',
      'logging.channels.single.tap'=>false,
      'logging.channels.single.formatter'=>false,
    ];
    $prefix='reporting:telemetry-loss:v2:'.substr(hash('sha256','fixture-project'),0,24);
    $GLOBALS['daily26_fixture_cache']=['business-telemetry-losses'=>0,$prefix.':epoch'=>'2026-10-20T00:00:00Z',$prefix.':legacy-unattributed'=>false,$prefix.':2026-10-26'=>0];
    if($variant==='unknown_loss')$GLOBALS['daily26_fixture_cache'][$prefix.':epoch']='invalid-epoch';
    if(in_array($variant,['gap5_late_loss_epoch','late_loss_epoch_full_heartbeats'],true))$GLOBALS['daily26_fixture_cache'][$prefix.':epoch']='2026-10-25T21:02:55Z';
    if($variant==='partial_epoch')$GLOBALS['daily26_fixture_cache'][$prefix.':epoch']='2026-10-26T05:00:00Z';
    return $root;
}
function removeFixture(string $root):void {
    $base=realpath(sys_get_temp_dir());$real=realpath($root);
    if($base===false||$real===false||!str_starts_with($real,$base.DIRECTORY_SEPARATOR)||!str_starts_with(basename($real),'amr-daily-fixture-'))throw new RuntimeException('refusing fixture cleanup outside owned temp root');
    $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($real,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach($it as $item){if($item->isLink())throw new RuntimeException('unexpected fixture symlink');$item->isDir()?rmdir($item->getPathname()):unlink($item->getPathname());}
    rmdir($real);
}
function collectFixture(string $variant):array {
    $root=makeFixture($variant);
    try {
      $metrics=(new TelemetryDailyMetrics())->collect(new DateTimeImmutable('2026-10-25T21:00:00+00:00'),new DateTimeImmutable('2026-10-26T21:00:00+00:00'));
      $report=['date'=>'2026-10-26','database_available'=>false,'public_health'=>['state'=>'unknown'],'metrics'=>$metrics];
      return ['metrics'=>$metrics,'text'=>CompactDailyFormatter::render('PayKeeper',$report)];
    } finally {removeFixture($root);}
}

$all=TelemetryDailyMetrics::heartbeatMinutes(str_repeat('1',1440),0,1440);
check($all===['expected'=>1440,'observed'=>1440,'missing'=>0],'complete-day heartbeat calculation');
$ticks=substr_replace(str_repeat('1',1440),'00000',1263,5);
$five=TelemetryDailyMetrics::heartbeatMinutes($ticks,1260,180);
check($five===['expected'=>180,'observed'=>175,'missing'=>5],'five-minute gap clipped to Moscow day across UTC boundary');

$cliReport=['date'=>'2026-10-26','database_available'=>false,'public_health'=>['state'=>'unknown'],'metrics'=>[
 'coverage'=>['state'=>'partial','from_utc'=>'2026-10-25T00:39:00+00:00'],
 'counts'=>['accepted'=>null,'rejected'=>null,'processed'=>null,'failed'=>null],
 'observed_counts'=>['accepted'=>10,'rejected'=>0,'processed'=>8,'failed'=>1],
 'observed_http_counts'=>['received'=>10,'4xx'=>0,'5xx'=>0],
 'errors'=>['coverage'=>['state'=>'complete'],'count'=>0,'observed_count'=>0],
 'classification_coverage'=>['state'=>'partial','reason'=>'cli_unobserved'],
 'heartbeat_coverage'=>['unobserved_before_epoch_minutes'=>480,'observation_from_utc'=>'2026-10-26T05:00:00+00:00'],
 'telemetry_loss_coverage'=>['fully_classified'=>false,'epoch_utc'=>'2026-10-26T05:00:00+00:00'],
 'business_cli'=>['coverage'=>['state'=>'partial','from_utc'=>'2026-10-26T05:14:00+00:00'],'expected_units'=>['batch','mail_poll'],'counts'=>null,'observed_counts'=>['batch'=>['started'=>528,'completed'=>527,'failed'=>1],'mail_poll'=>['started'=>189,'completed'=>187,'failed'=>2]],'last_successful_mail_check_utc'=>'2026-10-26T20:55:00+00:00'],
 'segment_issues'=>[],'telemetry_invalid_records'=>0,'telemetry_write_losses'=>0,'pending_kind'=>'queue','pending_now'=>0,
]];
$cliText=CompactDailyFormatter::render('Amobriz',$cliReport);
check(str_contains($cliText,'фоновые команды учитываются с 26.10 08:14; итог за сутки неполный.'),'CLI partial reason uses actual epoch instead of claiming no CLI activity');
check(str_contains($cliText,'Подтверждённый учёт начинается с 26.10 08:00; первые 480 мин. не подтверждены.'),'unclassified loss epoch is explicit with CLI lower bounds');
check(str_contains($cliText,'Фоновые команды: не менее 527 завершено, не менее 1 с ошибкой'),'observed CLI lower bounds remain visible');
check(!str_contains($cliText,'фоновые команды ещё не учтены'),'partial CLI counters are not described as absent');

$stable=collectFixture('gap5');
check($stable['metrics']['telemetry_observation_reliable']===true,'stable scanned files and classified zero loss prove observation');
check($stable['metrics']['heartbeat_coverage']['period_minutes']===1440,'Moscow report period is 1440 minutes');
check($stable['metrics']['heartbeat_coverage']['observed_minutes']===1435,'cross-UTC five-minute gap yields 1435 observed minutes');
check($stable['metrics']['heartbeat_coverage']['missing_minutes']===5,'cross-UTC gap is exactly five minutes');
check(str_contains($stable['text'],'За время наблюдения событий не зарегистрировано. Есть данные за 1435 из 1440 минут. пропуск отметок мониторинга за 5 мин. Полный итог неизвестен.'),'real provider output gives bounded partial zero');
check(!str_contains($stable['text'],'Запросов: 0'),'partial day is not reported as full zero');

$lateEpochFullTicks=collectFixture('late_loss_epoch_full_heartbeats');
check($lateEpochFullTicks['metrics']['heartbeat_coverage']['missing_minutes']===0,'all post-epoch heartbeat ticks do not fill the pre-epoch interval');
check($lateEpochFullTicks['metrics']['coverage']['state']==='partial','late loss-ledger epoch downgrades full-period coverage');
check($lateEpochFullTicks['metrics']['counts']['accepted']===null,'late loss-ledger epoch prevents full-day zero totals');
check($lateEpochFullTicks['metrics']['telemetry_observation_reliable']===true,'post-epoch observation can still be described precisely');
check(str_contains($lateEpochFullTicks['text'],'Есть данные за 1437 из 1440 минут.'),'partial no-event text shows only observed post-epoch interval');

$lateLoss=collectFixture('gap5_late_loss_epoch');
check($lateLoss['metrics']['telemetry_observation_reliable']===true,'valid zero-loss proof is reliable after its own epoch');
check($lateLoss['metrics']['heartbeat_coverage']['expected_minutes']===1437,'late loss epoch clips the post-epoch expected interval');
check($lateLoss['metrics']['heartbeat_coverage']['observed_minutes']===1432,'five absent heartbeat minutes are outside the late-epoch overlap');
check($lateLoss['metrics']['heartbeat_coverage']['unobserved_before_epoch_minutes']===3,'fractional loss epoch is rounded up to three unconfirmed minute buckets');
check($lateLoss['metrics']['heartbeat_coverage']['unconfirmed_minutes']===8,'pre-epoch and heartbeat gaps are unioned without overlap');
check(str_contains($lateLoss['text'],'Есть данные за 1432 из 1440 минут.'),'output uses full-day denominator');
check(str_contains($lateLoss['text'],'до начала подтверждённого учёта нет данных за 3 мин.'),'loss ledger epoch is explicit');
check(str_contains($lateLoss['text'],'пропуск отметок мониторинга за 5 мин.'),'heartbeat loss is separately explicit');

$epoch=collectFixture('partial_epoch');
check($epoch['metrics']['telemetry_observation_reliable']===true,'partial epoch after midnight has reliable observed files');
check($epoch['metrics']['coverage']['state']==='partial','epoch inside report day remains partial');
check($epoch['metrics']['heartbeat_coverage']['expected_minutes']===960,'only post-epoch 960 minutes are heartbeat-expected');
check($epoch['metrics']['heartbeat_coverage']['unobserved_before_epoch_minutes']===480,'pre-epoch 480 minutes are separately unknown');
check(str_contains($epoch['text'],'Есть данные за 960 из 1440 минут.'),'partial epoch text states observed interval');
check(str_contains($epoch['text'],'до начала подтверждённого учёта нет данных за 480 мин.'),'partial epoch names pre-epoch unknown duration');
check(!str_contains($epoch['text'],'Пропуск данных: 5 мин.'),'epoch start is not misrepresented as a five-minute heartbeat gap');

foreach(['marker_mismatch','missing_file','unknown_loss'] as $variant){
 $case=collectFixture($variant);
 check($case['metrics']['telemetry_observation_reliable']===false,$variant.' makes real provider observation unreliable');
 check(!str_contains($case['text'],'событий не зарегистрировано'),$variant.' cannot assert observed zero');
}
echo "partial coverage provider+formatter: PASS (full day; UTC/MSK 5-minute gap; late loss epoch; partial business epoch; marker mismatch; missing file; unknown loss)\n";
}

