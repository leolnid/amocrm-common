<?php
declare(strict_types=1);
namespace Leolnid\Common\Reporting\Capture;

use Illuminate\Support\Facades\Cache;

/** Correlates only one foreground Laravel scheduled run with its inherited child environment. */
final class ScheduledRunBridge
{
    private const ENV='LEOLNID_REPORTING_SCHEDULE_RUN_ID';
    private const TTL_SECONDS=600;
    private static ?array $active=null;
    private static array $localDelivered=[];

    public static function begin(object $task): ?string
    {
        if(is_array(self::$active)){
            if(self::$active['task']===$task&&!((bool)($task->runInBackground??false)))return self::$active['id'];
            self::finish();
        }
        if ((bool)($task->runInBackground??false)) return null;
        $native=getenv(self::ENV);
        $previous=['native'=>$native,'env_exists'=>array_key_exists(self::ENV,$_ENV),'env'=>$_ENV[self::ENV]??null,'server_exists'=>array_key_exists(self::ENV,$_SERVER),'server'=>$_SERVER[self::ENV]??null];
        if($native!==false){
            if(!is_string($native)||preg_match('/^[a-f0-9]{32}$/D',$native)!==1)return null;
            $id=$native;
        }else{
            try { $id=bin2hex(random_bytes(16)); } catch (\Throwable) { return null; }
            try { if (putenv(self::ENV.'='.$id)!==true) return null; } catch (\Throwable) { return null; }
            $_ENV[self::ENV]=$id;$_SERVER[self::ENV]=$id;
        }
        self::$active=['task'=>$task,'id'=>$id,'previous'=>$previous];
        return $id;
    }

    public static function activeId(): ?string { return is_array(self::$active)?self::$active['id']:null; }

    public static function activeIdFor(?object $task): ?string
    {
        return is_array(self::$active)&&$task!==null&&self::$active['task']===$task?self::$active['id']:null;
    }

    public static function isActiveParentId(mixed $id): bool
    {
        return is_string($id)&&preg_match('/^[a-f0-9]{32}$/D',$id)===1&&hash_equals((string)(self::activeId()??''),$id);
    }

    public static function finish(?object $task=null): void
    {
        if (!is_array(self::$active)||($task!==null&&self::$active['task']!==$task)) return;
        $previous=self::$active['previous'];self::$active=null;
        try { if ($previous['native']===false) putenv(self::ENV); else putenv(self::ENV.'='.$previous['native']); } catch (\Throwable) { /* Restore PHP's own environment views even if native env support is unavailable. */ }
        if($previous['env_exists'])$_ENV[self::ENV]=$previous['env'];else unset($_ENV[self::ENV]);
        if($previous['server_exists'])$_SERVER[self::ENV]=$previous['server'];else unset($_SERVER[self::ENV]);
    }

    public static function inheritedId(): ?string
    {
        $id=getenv(self::ENV);
        return is_string($id)&&preg_match('/^[a-f0-9]{32}$/D',$id)===1?$id:null;
    }

    public static function delivered(array $metadata): bool
    {
        $id=$metadata['scheduled_run_id']??null;$project=$metadata['project']??null;
        if (!is_string($id)||preg_match('/^[a-f0-9]{32}$/D',$id)!==1||!is_string($project)||preg_match('/^[A-Za-z0-9_.-]{1,64}$/D',$project)!==1) return false;
        self::pruneLocal();if(isset(self::$localDelivered[self::localKey($project,$id)]))return true;
        try { return Cache::get(self::key($project,$id))===true; } catch (\Throwable) { return false; }
    }

    public static function recordDelivered(array $metadata): void
    {
        $id=$metadata['scheduled_run_id']??null;$project=$metadata['project']??null;
        if (!is_string($id)||preg_match('/^[a-f0-9]{32}$/D',$id)!==1||!is_string($project)||preg_match('/^[A-Za-z0-9_.-]{1,64}$/D',$project)!==1) return;
        self::pruneLocal();self::$localDelivered[self::localKey($project,$id)]=time()+self::TTL_SECONDS;
        try { Cache::put(self::key($project,$id),true,self::TTL_SECONDS); } catch (\Throwable) { /* Fail open: parent keeps its own alert. */ }
    }

    private static function localKey(string $project,string $id): string { return $project.':'.$id; }
    private static function pruneLocal(): void { $now=time();foreach(self::$localDelivered as $key=>$expires)if($expires<=$now)unset(self::$localDelivered[$key]); }
    private static function key(string $project,string $id): string { return 'app-errors:scheduler-delivered:'.$project.':'.$id; }
}
