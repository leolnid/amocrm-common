<?php
namespace Leolnid\Common\Reporting\Daily;
use DateTimeImmutable;
use Throwable;
final class HttpPhaseMetrics
{
    public static function collect(DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        $counts = array_fill_keys(['received','responses','accepted','rejected','4xx','5xx','other'], 0);
        $seen = []; $valid = true;
        foreach (array_unique([$start->format('Y-m-d'),$end->modify('-1 second')->format('Y-m-d')]) as $day) {
            $path = storage_path('logs/business-activity/'.$day.'.jsonl');
            if (!is_file($path)) { $valid = false; continue; }
            $f = fopen($path, 'rb');
            if (!$f) { $valid = false; continue; }
            $bytes = 0;
            try {
                while (($line = fgets($f,8192)) !== false) {
                    $bytes += strlen($line);
                    if ($bytes > (int)config('app_reports.max_scan_bytes',8388608) || !str_ends_with($line,"\n")) { $valid=false; break; }
                    $row = json_decode($line,true);
                    if (!is_array($row)) { $valid=false; continue; }
                    $context=$row['context']??null;
                    if (!is_string($context)) { $valid=false; continue; }
                    if (!str_starts_with($context,'http:')) continue;
                    if (!is_string($row['occurred_at_utc']??null)) { $valid=false; continue; }
                    try { $at=new DateTimeImmutable($row['occurred_at_utc']); } catch (Throwable) { $valid=false; continue; }
                    if ($at<$start || $at>=$end) continue;
                    $id=$row['correlation_id']??'';
                    if (!is_string($id) || !preg_match('/^[a-f0-9]{32}$/D',$id)) { $valid=false; continue; }
                    if (preg_match('/^http:received:[A-Za-z0-9_:.-]+$/D',$context)) $kind='received';
                    elseif (preg_match('/^http:status:([1-5][0-9]{2}):[A-Za-z0-9_:.-]+$/D',$context,$match)) $kind='response';
                    else { $valid=false; continue; }
                    $key=$kind.':'.$id;
                    if (isset($seen[$key])) continue;
                    if (count($seen)>=100000) { $valid=false; continue; }
                    $seen[$key]=true;
                    if ($kind==='received') { $counts['received']++; continue; }
                    $status=(int)$match[1]; $counts['responses']++;
                    if ($status>=200&&$status<300) $counts['accepted']++;
                    else { $counts['rejected']++; $counts[$status>=400&&$status<500?'4xx':($status>=500?'5xx':'other')]++; }
                }
            } finally { fclose($f); }
        }
        return ['counts'=>$counts,'valid'=>$valid];
    }

}
