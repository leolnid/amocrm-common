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
        $healthy=($report['public_health']['state']??'unknown')==='baseline_match'&&($report['database_available']??false);
        $health=$healthy?'Сервис доступен':'Сервис требует проверки';
        $header='📊 <b>'.$escape($project).' · '.$escape($date).'</b>';

        if(($m['received_unit']??null)==='stored_module_runs'){
            $module=is_array($m['module_run_counts']??null)?$m['module_run_counts']:[];
            $full=($m['coverage']['state']??'unknown')==='complete';
            $number=static fn($v)=>is_int($v)&&$v>=0?(!$full?'не менее ':'').$v:'нет данных';
            $pending=is_int($m['pending_now']??null)&&$m['pending_now']>=0?' · ожидают '.$m['pending_now']:'';
            $processing=is_int($m['processing_now']??null)&&$m['processing_now']>=0?' · в обработке '.$m['processing_now']:'';
            $lines=[$header,'Запусков модулей: '.$number($module['received']??null),
                'Модули: '.$number($module['completed']??null).' завершено, '.$number($module['failed']??null).' с ошибкой · пропущено '.$number($module['skipped']??null).$pending.$processing,$health];
            if(!$full)$lines[]='Данные неполные.';
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
            $zeroProven=$from!==null&&($m['segment_issues']??null)===[]&&($m['telemetry_invalid_records']??null)===0&&($m['telemetry_write_losses']??null)===0;
            $valid=static fn($v)=>is_int($v)&&$v>=0&&($v>0||$zeroProven);
            $lower=static fn($v)=>$valid($v)?'не менее '.$v:'нет данных';
            $requests=$hasHttp?$h['received']:($hasReceivedCounter?$c['received']:($legacyExit?$sum($c['accepted']??null,$c['rejected']??null):null));
            $responses=$hasHttp?$sum($h['4xx']??null,$h['5xx']??null):($hasReceivedCounter||$legacyExit?($c['rejected']??null):null);
            $processed=$c['processed']??null;$failed=$c['failed']??null;
            $errorComplete=($m['errors']['coverage']['state']??'unknown')==='complete';
            $errorValue=$errorComplete?($m['errors']['count']??null):($m['errors']['observed_count']??null);
            $errorText=$errorComplete?$n($errorValue):(is_int($errorValue)&&$errorValue>0?'не менее '.$errorValue:'нет данных');
            $note=($m['classification_coverage']['reason']??null)==='cli_unobserved'?'Данные неполные: фоновые команды ещё не учтены.':'Данные неполные.';
            if(!$valid($requests)&&!$valid($responses)&&!$valid($processed)&&!$valid($failed)&&!(is_int($errorValue)&&$errorValue>0))return self::withCli(implode("\n",[$header,'Статистика пока недоступна. '.$note,$health]),$m);
            $queue=($m['pending_kind']??'')==='queue'&&is_int($m['pending_now']??null)&&$m['pending_now']>=0?' · в очереди '.$m['pending_now']:'';
            return self::withCli(implode("\n",[$header,($legacyExit&&!$hasHttp&&!$hasReceivedCounter?'Обработано запросов: ':'Запросов: ').$lower($requests).' · ошибок ответа: '.$lower($responses),
                'Задачи: '.$lower($processed).' завершено, '.$lower($failed).' с ошибкой'.$queue,$health.' · ошибок в журнале: '.$errorText,$note]),$m);
        }
        $c=$onboarding?($m['observed_counts']??[]):($m['counts']??[]);
        $h=$onboarding?($m['observed_http_counts']??[]):($m['http_counts']??[]);
        $h=is_array($h)?$h:[];$c=is_array($c)?$c:[];
        $hasHttp=array_key_exists('received',$h);$hasReceivedCounter=array_key_exists('received',$c);
        $legacyExit=($m['unit_definitions']['accepted/rejected']??null)==='business HTTP requests';
        $requests=$hasHttp?$h['received']:($hasReceivedCounter?$c['received']:($legacyExit?$sum($c['accepted']??null,$c['rejected']??null):null));
        $responseErrors=$hasHttp?$sum($h['4xx']??null,$h['5xx']??null):($hasReceivedCounter||$legacyExit?($c['rejected']??null):null);
        $number=static fn($value)=>($onboarding&&is_int($value)&&$value>=0?'не менее ':'').$n($value);
        $requestLabel=$legacyExit&&!$hasHttp&&!$hasReceivedCounter?'Обработано запросов: ':'Запросов: ';
        $queue=($m['pending_kind']??'')==='queue'&&is_int($m['pending_now']??null)?' · в очереди '.$n($m['pending_now']):'';
        $errors=$n(($m['errors']['coverage']['state']??'unknown')==='complete'?($m['errors']['count']??null):null);
        $lines=[$header,$requestLabel.$number($requests).' · ошибок ответа: '.$number($responseErrors),
            'Задачи: '.$number($c['processed']??null).' завершено, '.$number($c['failed']??null).' с ошибкой'.$queue,
            $health.' · ошибок в журнале: '.$errors];
        if($onboarding)$lines[]='За часть суток: с '.$from->setTimezone(new DateTimeZone('Europe/Moscow'))->format('d.m H:i').'.';
        return self::withCli(implode("\n",$lines),$m);
    }

    private static function withCli(string $text,array $metrics): string
    {
        $cli=$metrics['business_cli']??null;
        if(!is_array($cli)||($cli['coverage']['state']??'not_applicable')==='not_applicable')return $text;
        $complete=($cli['coverage']['state']??'unknown')==='complete';
        $counts=$complete?($cli['counts']??null):($cli['observed_counts']??null);
        $parts=[];
        foreach(['batch'=>'Фоновые команды','mail_poll'=>'Проверки почты']as$unit=>$label){
            if(!in_array($unit,$cli['expected_units']??[],true))continue;
            $row=is_array($counts)?($counts[$unit]??null):null;
            $valid=static fn($v)=>is_int($v)&&$v>=0;
            $completed=$row['completed']??null;$failed=$row['failed']??null;
            if(!$valid($completed)||!$valid($failed)||(!$complete&&$completed===0&&$failed===0))continue;
            $number=static fn($v)=>($complete?'':'не менее ').$v;
            $parts[]=$label.': '.$number($completed).' завершено, '.$number($failed).' с ошибкой';
        }
        if(!$parts)return $text;
        if(is_string($cli['last_successful_mail_check_utc']??null)){
            try{$date=new DateTimeImmutable($cli['last_successful_mail_check_utc']);$parts[]='Последняя проверка почты: '.$date->setTimezone(new DateTimeZone('Europe/Moscow'))->format('d.m H:i');}catch(Throwable){}
        }
        return $text."\n".implode(' · ',$parts);
    }
}
