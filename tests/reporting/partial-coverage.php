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
    $err=$root.DIRECTORY_SEPARATOR.'app.log';file_put_contents($err,"[2026-10-20 00:00:00] production.INFO: baseline\n");chmod($err,0600);
    if($variant==='error_categories'){
        $lines=[];
        for($i=0;$i<25;$i++){
            $id=str_pad(dechex($i<18?$i:$i-18),16,'0',STR_PAD_LEFT);
            $class=$i<11?'Illuminate\\Http\\Client\\RequestException':'AmoCRM\\Exceptions\\InvalidArgumentException';
            $row=['event_kind'=>'application_error','is_test'=>false,'occurred_at_utc'=>'2026-10-26T09:00:00Z','correlation_id'=>$id,'source'=>'queue','exception_class'=>$class];
            $lines[]='[2026-10-26 12:00:00] production.ERROR: Application error '.json_encode($row,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
        }
        file_put_contents($err,"[2026-10-20 00:00:00] production.INFO: baseline\n".implode("\n",$lines)."\n");
    }
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
check(str_contains($cliText,'Фоновые задачи учитываются с 26.10 08:14; итог за сутки неизвестен.'),'CLI partial reason uses actual epoch instead of claiming no activity');
check(str_contains($cliText,'Первые 480 мин. дня не учтены; данные начинаются с 08:00.'),'unclassified loss epoch is explicit with CLI counts');
check(str_contains($cliText,'Фоновые задачи (часть суток): 527 завершено, 1 с ошибкой'),'partial CLI counts are compact and period-qualified');
check(!str_contains($cliText,'фоновые задачи ещё не учтены'),'partial CLI counters are not described as absent');
$cliReport['metrics']['business_cli']['observed_counts']['batch']['failed']=0;
$cliZeroText=CompactDailyFormatter::render('Amobriz',$cliReport);
check(str_contains($cliZeroText,'Фоновые задачи (часть суток): 527 завершено, 0 с ошибкой'),'partial CLI zero is explicitly observed, not a daily total');
check(!str_contains($cliZeroText,'не менее 0'),'partial CLI zero is not rendered as a lower bound');

$windowMetrics=[
 'coverage'=>['state'=>'partial','from_utc'=>'2026-10-26T21:25:00+00:00'],
 'counts'=>['accepted'=>null,'rejected'=>null,'processed'=>null,'failed'=>null],
 'observed_counts'=>['accepted'=>64,'rejected'=>0,'processed'=>64,'failed'=>0],
 'observed_http_counts'=>['received'=>64,'4xx'=>0,'5xx'=>0],
 'classification_coverage'=>['state'=>'complete'],
 'segment_issues'=>[],'telemetry_invalid_records'=>0,'telemetry_write_losses'=>0,'telemetry_observation_reliable'=>true,
 'heartbeat_coverage'=>['period_minutes'=>1440,'expected_minutes'=>1415,'observed_minutes'=>1415,'missing_minutes'=>0,'unobserved_before_epoch_minutes'=>25,'observation_from_utc'=>'2026-10-26T21:25:00+00:00'],
 'errors'=>['coverage'=>['state'=>'complete'],'count'=>0,'observed_count'=>0,'count_unit'=>'unique_correlation_ids'],
 'pending_kind'=>'queue','pending_now'=>0,
];
$windowText=CompactDailyFormatter::render('Neftekamsk',['date'=>'2026-10-27','metrics'=>$windowMetrics,'database_available'=>false,'public_health'=>['state'=>'baseline_match']]);
check(str_contains($windowText,'Период: 00:25–24:00 MSK; первые 25 мин. не учтены; итог за сутки неизвестен.'),'25-minute start is a concise local-time interval with unknown daily total');
check(str_contains($windowText,'HTTP-запросы: 64 · ответы 4xx/5xx: 0'),'HTTP counts are not called completed business work');
check(str_contains($windowText,'Фоновые задания: завершено 64, ошибок выполнения 0'),'job counts are reported separately from HTTP');
check(str_contains($windowText,'Сайт отвечает штатно'),'website baseline does not claim whole business health');
check(!str_contains($windowText,'наблюдалось 64')&&!str_contains($windowText,'не менее 0'),'period-level partial wording avoids repeated observed labels and zero lower bounds');
$fullMetrics=$windowMetrics;$fullMetrics['coverage']=['state'=>'complete'];$fullMetrics['counts']=['accepted'=>64,'rejected'=>0,'processed'=>64,'failed'=>0];$fullMetrics['http_counts']=['received'=>64,'4xx'=>0,'5xx'=>0];
$fullText=CompactDailyFormatter::render('Neftekamsk',['date'=>'2026-10-27','metrics'=>$fullMetrics,'database_available'=>false,'public_health'=>['state'=>'baseline_match']]);
check(str_contains($fullText,'HTTP-запросы: 64 · ответы 4xx/5xx: 0'),'full period counts are exact without observed qualifiers');
check(!str_contains($fullText,'итог за сутки неизвестен'),'full coverage is not marked partial');

$nefteMetrics=[
 'coverage'=>['state'=>'partial','from_utc'=>'2026-10-25T01:47:00+00:00'],
 'counts'=>['accepted'=>null,'rejected'=>null,'processed'=>null,'failed'=>null],
 'observed_counts'=>['accepted'=>64,'rejected'=>0,'processed'=>64,'failed'=>0],
 'unit_definitions'=>['accepted/rejected'=>'business HTTP requests','processed/failed'=>'final technical whitelisted job UUIDs; not business operations completed'],
 'classification_coverage'=>['state'=>'complete','gap_days'=>[]],
 'segment_issues'=>[],'telemetry_observation_reliable'=>true,'telemetry_invalid_records'=>0,'telemetry_write_losses'=>0,
 'telemetry_loss_coverage'=>['fully_classified'=>false,'epoch_utc'=>'2026-10-25T21:24:22+00:00'],
 'heartbeat_coverage'=>['period_minutes'=>1440,'expected_minutes'=>1415,'observed_minutes'=>1415,'missing_minutes'=>0,'unobserved_before_epoch_minutes'=>25,'observation_from_utc'=>'2026-10-25T21:24:22+00:00'],
 'errors'=>['coverage'=>['state'=>'complete'],'count'=>18,'observed_count'=>18,'count_unit'=>'unique_correlation_ids'],
 'pending_kind'=>'queue','pending_now'=>null,
];
$nefteText=CompactDailyFormatter::render('База цветов — Нефтекамск',['date'=>'2026-10-26','metrics'=>$nefteMetrics,'public_health'=>['state'=>'unknown']]);
check(str_contains($nefteText,'Получено запросов: 64 · ответы 4xx/5xx: 0'),'accepted/rejected counters are labelled as received requests');
check(!str_contains($nefteText,'Обработано запросов:'),'request reception is not described as processing');
check(str_contains($nefteText,'Фоновые задания: завершено 64, ошибок выполнения 0'),'technical job lifecycle counts have an explicit label');
check(str_contains($nefteText,'В журнале: 18 событий ошибок'),'error log events have a distinct unit and line');
check(str_contains($nefteText,'Завершённое задание может содержать ошибки отдельных действий.'),'logged errors are not implied to be failed jobs');
check(str_contains($nefteText,'Сбор: 1415/1440 мин.; итог за сутки неизвестен.'),'partial coverage is explicit for the frozen Neftekamksk sample');
check(!str_contains($nefteText,'Очередь сейчас:')&&!str_contains($nefteText,'Очередь сейчас: 0'),'unavailable queue state does not become a zero');
check(str_contains($nefteText,'Доступность сайта не проверялась'),'unknown site state is not presented as a health claim');
$nefteMetrics['observed_counts']['failed']=1;
$nefteFailureText=CompactDailyFormatter::render('База цветов — Нефтекамск',['date'=>'2026-10-26','metrics'=>$nefteMetrics]);
check(!str_contains($nefteFailureText,'Завершённое задание может содержать'),'separation note is omitted when a job failure is already counted');
$nefteMetrics['observed_counts']['failed']=null;
$nefteUnknownFailureText=CompactDailyFormatter::render('База цветов — Нефтекамск',['date'=>'2026-10-26','metrics'=>$nefteMetrics]);
check(!str_contains($nefteUnknownFailureText,'Завершённое задание может содержать'),'separation note requires a known zero job-failure count');

$stable=collectFixture('gap5');
check($stable['metrics']['telemetry_observation_reliable']===true,'stable scanned files and classified zero loss prove observation');
check($stable['metrics']['heartbeat_coverage']['period_minutes']===1440,'Moscow report period is 1440 minutes');
check($stable['metrics']['heartbeat_coverage']['observed_minutes']===1435,'cross-UTC five-minute gap yields 1435 observed minutes');
check($stable['metrics']['heartbeat_coverage']['missing_minutes']===5,'cross-UTC gap is exactly five minutes');
check(str_contains($stable['text'],'Событий за учтённое время не зарегистрировано. Сбор: 1435/1440 мин. пропуск учёта за 5 мин. Полный итог неизвестен.'),'real provider output gives bounded partial zero');
check(!str_contains($stable['text'],'Запросов: 0'),'partial day is not reported as full zero');

$lateEpochFullTicks=collectFixture('late_loss_epoch_full_heartbeats');
check($lateEpochFullTicks['metrics']['heartbeat_coverage']['missing_minutes']===0,'all post-epoch heartbeat ticks do not fill the pre-epoch interval');
check($lateEpochFullTicks['metrics']['coverage']['state']==='partial','late loss-ledger epoch downgrades full-period coverage');
check($lateEpochFullTicks['metrics']['counts']['accepted']===null,'late loss-ledger epoch prevents full-day zero totals');
check($lateEpochFullTicks['metrics']['telemetry_observation_reliable']===true,'post-epoch observation can still be described precisely');
check(str_contains($lateEpochFullTicks['text'],'Сбор: 1437/1440 мин.'),'partial no-event text shows only observed post-epoch interval');

$lateLoss=collectFixture('gap5_late_loss_epoch');
check($lateLoss['metrics']['telemetry_observation_reliable']===true,'valid zero-loss proof is reliable after its own epoch');
check($lateLoss['metrics']['heartbeat_coverage']['expected_minutes']===1437,'late loss epoch clips the post-epoch expected interval');
check($lateLoss['metrics']['heartbeat_coverage']['observed_minutes']===1432,'five absent heartbeat minutes are outside the late-epoch overlap');
check($lateLoss['metrics']['heartbeat_coverage']['unobserved_before_epoch_minutes']===3,'fractional loss epoch is rounded up to three unconfirmed minute buckets');
check($lateLoss['metrics']['heartbeat_coverage']['unconfirmed_minutes']===8,'pre-epoch and heartbeat gaps are unioned without overlap');
check(str_contains($lateLoss['text'],'Сбор: 1432/1440 мин.'),'output uses full-day denominator');
check(str_contains($lateLoss['text'],'Период: 00:02–24:00 MSK; первые 3 мин. дня не учтены.'),'loss ledger epoch is explicit without internal telemetry terminology');
check(str_contains($lateLoss['text'],'пропуск учёта за 5 мин.'),'heartbeat loss is separately explicit');

$epoch=collectFixture('partial_epoch');
check($epoch['metrics']['telemetry_observation_reliable']===true,'partial epoch after midnight has reliable observed files');
check($epoch['metrics']['coverage']['state']==='partial','epoch inside report day remains partial');
check($epoch['metrics']['heartbeat_coverage']['expected_minutes']===960,'only post-epoch 960 minutes are heartbeat-expected');
check($epoch['metrics']['heartbeat_coverage']['unobserved_before_epoch_minutes']===480,'pre-epoch 480 minutes are separately unknown');
check(str_contains($epoch['text'],'Период: 08:00–24:00 MSK; первые 480 мин. дня не учтены.'),'partial epoch text states observed interval');
check(str_contains($epoch['text'],'Полный итог неизвестен.'),'partial epoch names the unknown full-day total');
check(!str_contains($epoch['text'],'Пропуск данных: 5 мин.'),'epoch start is not misrepresented as a five-minute heartbeat gap');

foreach(['marker_mismatch','missing_file','unknown_loss'] as $variant){
 $case=collectFixture($variant);
 check($case['metrics']['telemetry_observation_reliable']===false,$variant.' makes real provider observation unreliable');
 check(!str_contains($case['text'],'событий не зарегистрировано'),$variant.' cannot assert observed zero');
}
$errors=collectFixture('error_categories');
check($errors['metrics']['errors']['count']===18,'error count uses unique safe correlation IDs');
check($errors['metrics']['errors']['observed_raw_records']===25&&$errors['metrics']['errors']['observed_duplicate_records']===7,'repeat logger calls are counted separately from correlations');
check($errors['metrics']['errors']['observed_category_counts']===['amocrm'=>7,'http_client'=>11],'error classes collapse to fixed safe categories');
check($errors['metrics']['errors']['observed_source_counts']===['queue'=>18],'error sources aggregate without exposing job classes');
check(str_contains($errors['text'],'В журнале: 18 событий ошибок (25 записей, из них 7 повторных; это не число операций)'),'formatter explains log count unit and repeated records');
check(str_contains($errors['text'],'Категории журнала: HTTP-запросы 11, amoCRM 7'),'formatter shows useful safe categories');
check(!preg_match('/[a-f0-9]{16}/',$errors['text']),'formatter does not expose correlation identifiers');
check(!str_contains($stable['text'],'не менее 0'),'partial zero is not formatted as a lower bound of zero');
check(str_contains($stable['text'],'Доступность сайта не проверялась'),'health unknown is not presented as service failure or success');
$moduleText=CompactDailyFormatter::render('Тест',['date'=>'2026-10-27','metrics'=>[
 'received_unit'=>'stored_module_runs','coverage'=>['state'=>'partial','from_utc'=>'2026-10-26T21:25:00Z'],
 'module_run_counts'=>['received'=>64,'completed'=>64,'failed'=>0,'skipped'=>0],'pending_now'=>0,'processing_now'=>0,
],'public_health'=>['state'=>'unknown']]);
check(str_contains($moduleText,'Период: 00:25–24:00 MSK; итоги за сутки неизвестны.'),'partial module runs state exact observed window once');
check(str_contains($moduleText,'Запусков модулей: 64'),'module runs retain their own unit label');
check(str_contains($moduleText,'Модули: 64 завершено, 0 с ошибкой · пропущено 0'),'module counts are plain integers under period line');
check(!str_contains($moduleText,'наблюдалось'),'partial module counts do not repeat observed qualifier');
echo "partial coverage provider+formatter: PASS (full day; UTC/MSK 5-minute gap; late loss epoch; partial business epoch; marker mismatch; missing file; unknown loss; categorized logs)\n";
}

