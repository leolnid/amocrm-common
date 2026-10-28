<?php
namespace Leolnid\Common\Reporting\Daily;

use DateTimeImmutable;
use DateTimeZone;
use Throwable;

final class CompactDailyFormatter
{
    public static function render(string $project, array $report): string
    {
        $escape=static fn($s)=>htmlspecialchars($s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        $n=static fn($v)=>is_int($v)&&$v>=0?(string)$v:'нет данных';
        $sum=static fn($a,$b)=>is_int($a)&&$a>=0&&is_int($b)&&$b>=0&&$a<=PHP_INT_MAX-$b?$a+$b:null;
        $m=$report['metrics']??[];
        $date=(string)$report['date'];
        $health=match($report['public_health']['state']??'unknown'){
            'baseline_match'=>'Сайт отвечает штатно',
            'baseline_changed'=>'Ответ сайта отличается от ожидаемого',
            'transport_failed'=>'Проверка сайта не завершилась',
            default=>'Доступность сайта не проверялась',
        };
        $header='📊 <b>'.$escape($project).' · '.$escape($date).'</b>';

        if(($m['received_unit']??null)==='stored_module_runs'){
            $module=is_array($m['module_run_counts']??null)?$m['module_run_counts']:[];
            $full=($m['coverage']['state']??'unknown')==='complete';
            $number=static fn($v)=>is_int($v)&&$v>=0?(string)$v:'нет данных';
            $pending=is_int($m['pending_now']??null)&&$m['pending_now']>=0?' · ожидают '.$m['pending_now']:'';
            $processing=is_int($m['processing_now']??null)&&$m['processing_now']>=0?' · в обработке '.$m['processing_now']:'';
            $lines=[$header];
            if(!$full){
                $period=null;
                try{
                    $from=is_string($m['coverage']['from_utc']??null)?new DateTimeImmutable($m['coverage']['from_utc']):null;
                    $day=DateTimeImmutable::createFromFormat('!Y-m-d',$date,new DateTimeZone('Europe/Moscow'));
                    if($from!==null&&$day!==false&&$from>$day&&$from<$day->modify('+1 day'))$period='Период: '.$from->setTimezone(new DateTimeZone('Europe/Moscow'))->format('H:i').'–24:00 MSK; итоги за сутки неизвестны.';
                }catch(Throwable){}
                $lines[]=$period??'За неполный период; итоги за сутки неизвестны.';
            }
            $lines[]='Запусков модулей: '.$number($module['received']??null);
            $lines[]='Модули: '.$number($module['completed']??null).' завершено, '.$number($module['failed']??null).' с ошибкой · пропущено '.$number($module['skipped']??null).$pending.$processing;
            $lines[]=$health;
            return implode("\n",$lines);
        }
        $complete=($m['coverage']['state']??'unknown')==='complete';
        $from=null;$start=null;$end=null;
        try {
            if(is_string($m['coverage']['from_utc']??null))$from=new DateTimeImmutable($m['coverage']['from_utc']);
            $start=DateTimeImmutable::createFromFormat('!Y-m-d',$date,new DateTimeZone('Europe/Moscow'));
            if(!$start||$start->format('Y-m-d')!==$date)throw new \InvalidArgumentException();
            $end=$start->modify('+1 day');
        } catch(Throwable) {$from=null;$start=null;$end=null;}
        if($from!==null&&$end!==null&&$from>=$end){
            $local=$from->setTimezone(new DateTimeZone('Europe/Moscow'));
            $midnight=$local->setTime(0,0);
            $first=$midnight->modify($local==$midnight?'+1 day':'+2 days')->format('d.m');
            return implode("\n",[$header,'Сбор статистики начат '.$local->format('d.m').' в '.$local->format('H:i').'. Первый полный отчёт — '.$first.' при непрерывном сборе.',$health]);
        }
        $onboarding=!$complete&&$from!==null&&$start!==null&&$end!==null&&$from>$start&&$from<$end
            &&($m['classification_coverage']['state']??'unknown')==='complete'
            &&($m['segment_issues']??null)===[]
            &&($m['telemetry_invalid_records']??null)===0
            &&($m['telemetry_write_losses']??null)===0;
        if(!$complete&&!$onboarding){
            $c=is_array($m['observed_counts']??null)?$m['observed_counts']:[];
            $h=is_array($m['observed_http_counts']??null)?$m['observed_http_counts']:[];
            $hasHttp=array_key_exists('received',$h);$hasReceivedCounter=array_key_exists('received',$c);
            $legacyExit=($m['unit_definitions']['accepted/rejected']??null)==='business HTTP requests';
            $zeroProven=($m['telemetry_observation_reliable']??false)===true&&$from!==null&&($m['segment_issues']??null)===[]&&($m['telemetry_invalid_records']??null)===0&&($m['telemetry_write_losses']??null)===0;
            $valid=static fn($v)=>is_int($v)&&$v>=0&&($v>0||$zeroProven);
            $lower=static fn($v)=>$valid($v)?(string)$v:'нет данных';
            $requests=$hasHttp?$h['received']:($hasReceivedCounter?$c['received']:($legacyExit?$sum($c['accepted']??null,$c['rejected']??null):null));
            $responses=$hasHttp?$sum($h['4xx']??null,$h['5xx']??null):($hasReceivedCounter||$legacyExit?($c['rejected']??null):null);
            $processed=$c['processed']??null;$failed=$c['failed']??null;
            $errorComplete=($m['errors']['coverage']['state']??'unknown')==='complete';
            $errorValue=$errorComplete?($m['errors']['count']??null):($m['errors']['observed_count']??null);
            $note='Есть пропуски учёта; итог за сутки неизвестен.';
            if(($m['classification_coverage']['reason']??null)==='cli_unobserved'){
                $cliFrom=$m['business_cli']['coverage']['from_utc']??null;
                try{$cliStart=is_string($cliFrom)?(new DateTimeImmutable($cliFrom))->setTimezone(new DateTimeZone('Europe/Moscow'))->format('d.m H:i'):null;}catch(Throwable){$cliStart=null;}
                $note=$cliStart!==null?'Фоновые задачи учитываются с '.$cliStart.'; итог за сутки неизвестен.':'Фоновые задачи учтены не за весь день; итог за сутки неизвестен.';
            }
            $heartbeatCoverage=$m['heartbeat_coverage']??[];
            $before=$heartbeatCoverage['unobserved_before_epoch_minutes']??null;
            $observationFrom=$heartbeatCoverage['observation_from_utc']??null;
            if(is_int($before)&&$before>0&&is_string($observationFrom)){
                try{$observedStart=(new DateTimeImmutable($observationFrom))->setTimezone(new DateTimeZone('Europe/Moscow'))->format('H:i');$note.=' Первые '.$before.' мин. дня не учтены; данные начинаются с '.$observedStart.'.';}catch(Throwable){}
            }
            $lossCoverage=$m['telemetry_loss_coverage']??null;
            if(is_array($lossCoverage)&&($lossCoverage['fully_classified']??false)!==true&&($lossCoverage['epoch_utc']??null)===null)$note.=' Полнота учёта потерь не подтверждена.';
            $observed=$m['observed_counts']??[];
            $allObservedZero=is_array($observed)&&array_intersect(['accepted','rejected','processed','failed'],array_keys($observed))===['accepted','rejected','processed','failed'];
            if($allObservedZero){foreach(['accepted','rejected','processed','failed'] as $key){if(($observed[$key]??null)!==0){$allObservedZero=false;break;}}}
            $heartbeat=$m['heartbeat_coverage']??[];
            $missingMinutes=$heartbeat['missing_minutes']??null;
            $periodMinutes=$heartbeat['period_minutes']??null;
            $observedMinutes=$heartbeat['observed_minutes']??null;
            $beforeEpochMinutes=$heartbeat['unobserved_before_epoch_minutes']??null;
            if($allObservedZero&&($m['telemetry_observation_reliable']??false)===true&&is_int($missingMinutes)&&is_int($periodMinutes)&&is_int($observedMinutes)&&is_int($beforeEpochMinutes)&&($missingMinutes>0||$beforeEpochMinutes>0)){
                $detail='Сбор: '.$observedMinutes.'/'.$periodMinutes.' мин.';
                $uncertainty=[];
                if($missingMinutes>0)$uncertainty[]='пропуск учёта за '.$missingMinutes.' мин.';
                    $window=null;
                    if($beforeEpochMinutes>0&&is_string($heartbeat['observation_from_utc']??null)){
                        try{$observedStart=(new DateTimeImmutable($heartbeat['observation_from_utc']))->setTimezone(new DateTimeZone('Europe/Moscow'));if($start!==null&&$end!==null&&$observedStart>$start&&$observedStart<$end)$window='Период: '.$observedStart->format('H:i').'–24:00 MSK; первые '.$beforeEpochMinutes.' мин. дня не учтены.';}catch(Throwable){}
                    }
                $queue=($m['pending_kind']??'')==='queue'&&is_int($m['pending_now']??null)&&$m['pending_now']>=0?'В очереди сейчас: '.$m['pending_now'].'.':null;
                    $windowText=$window??'';
                    $lines=[$header,'Событий за учтённое время не зарегистрировано. '.($windowText!==''?$windowText.' ':'').$detail.' '.implode(' ',$uncertainty).' Полный итог неизвестен.',self::errorLine($m['errors']??[])];
                if($queue!==null)$lines[]=$queue;
                $lines[]=$health;
                return self::withCli(implode("\n",$lines),$m);
            }
            if($from!==null&&$start!==null&&$from>$start&&$from<$end){
                $localFrom=$from->setTimezone(new DateTimeZone('Europe/Moscow'));
                $note='Период: '.$localFrom->format('H:i').'–24:00 MSK; итог за сутки неизвестен.';
            }
            if(!$complete){
                $heartbeat=$m['heartbeat_coverage']??[];$observedMinutes=$heartbeat['observed_minutes']??null;$periodMinutes=$heartbeat['period_minutes']??null;$missingMinutes=$heartbeat['missing_minutes']??null;
                if($from!==null&&$start!==null&&$from>$start&&$from<$end){
                    $localFrom=$from->setTimezone(new DateTimeZone('Europe/Moscow'));
                    $note='Период: '.$localFrom->format('H:i').'–24:00 MSK; итог за сутки неизвестен.';
                }elseif(is_int($observedMinutes)&&is_int($periodMinutes)&&$periodMinutes>0&&$observedMinutes<$periodMinutes){
                    $note='Сбор: '.$observedMinutes.'/'.$periodMinutes.' мин.'.(is_int($missingMinutes)&&$missingMinutes>0?'; пропуски учёта — '.$missingMinutes.' мин.':'').'; итог за сутки неизвестен.';
                }
                $gaps=$m['classification_coverage']['gap_days']??[];
                if(is_array($gaps)&&$gaps){$gapLabels=[];foreach($gaps as $day)$gapLabels[]=is_string($day)&&preg_match('/^\d{4}-\d{2}-\d{2}$/D',$day)?substr($day,8,2).'.'.substr($day,5,2):'отдельные даты';$note='Есть пропуски учёта активности за '.implode(', ',$gapLabels).'; итог за сутки неизвестен.';}
                if(($m['segment_issues']??[])!==[])$note='Есть пропуски учёта телеметрии; итог за сутки неизвестен.';
                $lossCoverage=$m['telemetry_loss_coverage']??[];
                if(($lossCoverage['fully_classified']??false)!==true&&($lossCoverage['epoch_utc']??null)===null)$note='Полнота учёта событий не подтверждена; итог за сутки неизвестен.';
            }
            if(!$valid($requests)&&!$valid($responses)&&!$valid($processed)&&!$valid($failed)&&!(is_int($errorValue)&&$errorValue>0))return self::withCli(implode("\n",[$header,'Статистика пока недоступна. '.$note,$health,self::errorLine($m['errors']??[])]),$m);
            $errorLine=self::errorLine($m['errors']??[]);
            $requestLine=($legacyExit&&!$hasHttp&&!$hasReceivedCounter?'Получено запросов: ':'HTTP-запросы: ').$lower($requests).' · ответы 4xx/5xx: '.$lower($responses);
            $lines=[$header,$note,$requestLine,'Фоновые задания: завершено '.$lower($processed).', ошибок выполнения '.$lower($failed),$errorLine];
            $jobErrorNote=self::jobErrorSeparationNote($m,$failed);
            if($jobErrorNote!==null)$lines[]=$jobErrorNote;
            if(($m['pending_kind']??'')==='queue'&&is_int($m['pending_now']??null)&&$m['pending_now']>=0)$lines[]='Очередь сейчас: '.$m['pending_now'];
            $lines[]=$health;
            return self::withCli(implode("\n",$lines),$m);
        }
        $c=$onboarding?($m['observed_counts']??[]):($m['counts']??[]);
        $h=$onboarding?($m['observed_http_counts']??[]):($m['http_counts']??[]);
        $h=is_array($h)?$h:[];$c=is_array($c)?$c:[];
        $hasHttp=array_key_exists('received',$h);$hasReceivedCounter=array_key_exists('received',$c);
        $legacyExit=($m['unit_definitions']['accepted/rejected']??null)==='business HTTP requests';
        $requests=$hasHttp?$h['received']:($hasReceivedCounter?$c['received']:($legacyExit?$sum($c['accepted']??null,$c['rejected']??null):null));
        $responseErrors=$hasHttp?$sum($h['4xx']??null,$h['5xx']??null):($hasReceivedCounter||$legacyExit?($c['rejected']??null):null);
        $number=static fn($value)=>$n($value);
        $requestLabel=$legacyExit&&!$hasHttp&&!$hasReceivedCounter?'Получено запросов: ':'HTTP-запросы: ';
        $failed=$c['failed']??null;
        $lines=[$header,$requestLabel.$number($requests).' · ответы 4xx/5xx: '.$number($responseErrors),
            'Фоновые задания: завершено '.$number($c['processed']??null).', ошибок выполнения '.$number($failed),
            $health,self::errorLine($m['errors']??[])];
        $jobErrorNote=self::jobErrorSeparationNote($m,$failed);
        if($jobErrorNote!==null)$lines[]=$jobErrorNote;
        if(($m['pending_kind']??'')==='queue'&&is_int($m['pending_now']??null)&&$m['pending_now']>=0)$lines[]='Очередь сейчас: '.$m['pending_now'];
        if($onboarding){$startText='Период: '.$from->setTimezone(new DateTimeZone('Europe/Moscow'))->format('H:i').'–24:00 MSK';$before=$m['heartbeat_coverage']['unobserved_before_epoch_minutes']??null;if(is_int($before)&&$before>0)$startText.='; первые '.$before.' мин. не учтены';$lines[]=$startText.'; итог за сутки неизвестен.';}
        return self::withCli(implode("\n",$lines),$m);
    }

    private static function withCli(string $text,array $metrics): string
    {
        $cli=$metrics['business_cli']??null;
        if(!is_array($cli)||($cli['coverage']['state']??'not_applicable')==='not_applicable')return $text;
        $complete=($cli['coverage']['state']??'unknown')==='complete';
        $counts=$complete?($cli['counts']??null):($cli['observed_counts']??null);
        $parts=[];
        foreach(['batch'=>'Фоновые задачи','mail_poll'=>'Проверки почты']as$unit=>$label){
            if(!in_array($unit,$cli['expected_units']??[],true))continue;
            $row=is_array($counts)?($counts[$unit]??null):null;
            $valid=static fn($v)=>is_int($v)&&$v>=0;
            $completed=$row['completed']??null;$failed=$row['failed']??null;
            if(!$valid($completed)||!$valid($failed)||(!$complete&&$completed===0&&$failed===0))continue;
            $number=static fn($v)=>(string)$v;
            $periodLabel=$complete?$label:$label.' (часть суток)';
            $parts[]=$periodLabel.': '.$number($completed).' завершено, '.$number($failed).' с ошибкой';
        }
        if(!$parts)return $text;
        if(is_string($cli['last_successful_mail_check_utc']??null)){
            try{$date=new DateTimeImmutable($cli['last_successful_mail_check_utc']);$parts[]='Последняя проверка почты: '.$date->setTimezone(new DateTimeZone('Europe/Moscow'))->format('d.m H:i');}catch(Throwable){}
        }
        return $text."\n".implode(' · ',$parts);
    }

    private static function errorLine(array $errors): string
    {
        $complete=($errors['coverage']['state']??'unknown')==='complete';
        $count=$complete?($errors['count']??null):($errors['observed_count']??null);
        if(!$complete&&$count===0)$count=null;
        $text=is_int($count)?'В журнале: '.$count.' событий ошибок':'В журнале ошибок: данных недостаточно';
        $raw=$errors['observed_raw_records']??null;$ids=$errors['observed_count']??null;
        if(is_int($raw)&&is_int($ids)&&$raw>=$ids&&$raw>$ids)$text.=' ('.$raw.' записей, из них '.($raw-$ids).' повторных; это не число операций)';
        $groups=$errors['observed_category_counts']??[];
        $labels=['http_client'=>'HTTP-запросы','amocrm'=>'amoCRM','database'=>'База данных','application'=>'Приложение','unknown'=>'тип не определён'];
        $parts=[];foreach($labels as $key=>$caption)if(is_int($groups[$key]??null)&&$groups[$key]>0)$parts[]=$caption.' '.$groups[$key];
        if($parts)$text.="\nКатегории журнала: ".implode(', ',$parts);
        if(!$complete&&!str_contains($text,'недостаточно'))$text.="\nПолный итог по журналу неизвестен.";
        return $text;
    }

    private static function jobErrorSeparationNote(array $metrics,mixed $failed): ?string
    {
        $errorCoverage=($metrics['errors']['coverage']['state']??'unknown')==='complete';
        $errorCount=$errorCoverage?($metrics['errors']['count']??null):($metrics['errors']['observed_count']??null);
        return is_int($failed)&&$failed===0&&is_int($errorCount)&&$errorCount>0
            ?'Завершённое задание может содержать ошибки отдельных действий.'
            :null;
    }
}
