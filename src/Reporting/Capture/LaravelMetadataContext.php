<?php
declare(strict_types=1);
namespace Leolnid\Common\Reporting\Capture;
use Leolnid\Common\Reporting\Errors\{ErrorMessage,ErrorObject,ErrorReason};
final class LaravelMetadataContext
{
    public static function collect(\Throwable $error, array $context, array $definition, ?string $consoleCommand, bool $test): array
    {
        // Laravel's scheduler wrapper may reach the handler after event scope was cleared.
        // Recover only an exact artisan command and exit status; never copy shell arguments.
        if (!$test && !in_array($context['source']??null,['queue','http','scheduler'],true)
            && preg_match("~^Scheduled command \\[.*?'artisan' '?([A-Za-z0-9:_-]{1,100})'?\\] failed with exit code \\[([1-9][0-9]{0,2})\\]\\.$~D",$error->getMessage(),$scheduled)===1
            && (int)$scheduled[2]<=255) {
            $context=['source'=>'scheduler','scheduled_command'=>$scheduled[1],'command_exit_code'=>(int)$scheduled[2],'scheduled_wrapper_failure'=>true]+$context;
        }
        $project=(string)config('app_errors.project','application');$server=(string)config('app_errors.server','unknown');
        if (!preg_match('/^[A-Za-z0-9_.-]{1,64}$/D',$project)) $project='application';
        if (!preg_match('/^[A-Za-z0-9_.:-]{1,64}$/D',$server)) $server='unknown';
        $source=$test?'test':($context['source']??null);$route=null;
        if (!$test && !in_array($source,['queue','scheduler'],true)) {
            try { $route=app()->bound('request')?request()->route():null; } catch (\Throwable) { $route=null; }
            if ($route!==null) $source='http';
        }
        if (!in_array($source,['http','queue','scheduler','console','test'],true)) $source='console';
        $class=$error::class;if (!preg_match('/^[A-Za-z_][A-Za-z0-9_\\\\]{0,200}$/D',$class)) $class='Throwable';
        $out=['project'=>$project,'server'=>$server,'php_version'=>PHP_VERSION,'source'=>$source,'exception_class'=>$class];
        $resolvedConsole=$source==='console'?self::command($consoleCommand,$definition):null;
        $runId=$context['scheduled_run_id']??ScheduledRunBridge::inheritedId();
        $correlatableConsole=$source==='console'&&$resolvedConsole!==null&&$resolvedConsole!=='unknown'&&!in_array($resolvedConsole,['schedule:run','schedule:work'],true);
        if(!$test&&($source==='scheduler'||$correlatableConsole)&&is_string($runId)&&preg_match('/^[a-f0-9]{32}$/D',$runId)===1)$out['scheduled_run_id']=$runId;
        if (!$test && ($context['scheduled_wrapper_failure']??false)===true && $source==='scheduler') $out['scheduled_wrapper_failure']=true;
        if ($test) return $out;
        if ($source==='queue') {
            $job=$context['job_class']??null;
            $out['job_class']=is_string($job)&&(preg_match('/^[A-Za-z_][A-Za-z0-9_\\\\]{0,200}$/D',$job)||isset($definition['closure_source_pins'][$job]))?$job:'unknown';
        } elseif ($source==='scheduler') {
            $command=$context['scheduled_command']??null;$out['scheduled_command']=self::command($command,$definition);
            $exit=$context['command_exit_code']??null;if (is_int($exit)&&$exit>=1&&$exit<=255) $out['command_exit_code']=$exit;
        } elseif ($source==='console') {
            $out['console_command']=$resolvedConsole??'unknown';
            $exit=$context['command_exit_code']??null;if (is_int($exit)&&$exit>=1&&$exit<=255) $out['command_exit_code']=$exit;
        } elseif ($source==='http' && is_object($route)) {
            try {
                $uri=method_exists($route,'uri')?$route->uri():null;
                if (is_string($uri)&&preg_match('/^[A-Za-z0-9_\/{}:?.-]{1,200}$/D',$uri)) $out['route_template']=$uri;
                $action=method_exists($route,'getActionName')?$route->getActionName():null;
                if (is_string($action)&&preg_match('/^[A-Za-z_][A-Za-z0-9_\\\\@]{0,200}$/D',$action)) $out['http_action']=$action;
                if ($action==='Closure'&&is_string($uri)&&isset($definition['operations']['route:'.$uri])) $out['http_closure_verified']=true;
            } catch (\Throwable) { /* Keep already-known metadata if an optional route getter fails. */ }
        }
        return $out;
    }
    public static function withIdentity(array $metadata, ?object $context, ?ErrorMessage $message): array
    {
        if ($message!==null) $metadata+=['business_action'=>$message->failure->code,'reason_code'=>$message->reason->code,'service_code'=>$message->reason->service];
        if ($message?->reason->httpStatus!==null) $metadata['http_status']=$message->reason->httpStatus;
        if (is_string($message?->reason->upstreamHost)&&ErrorReason::isUpstreamHost($message->reason->upstreamHost)) $metadata['upstream_host']=$message->reason->upstreamHost;
        if ($context===null) return $metadata;
        $data=get_object_vars($context);
        foreach (['entityId'=>'entity_id','moduleId'=>'module_id','accountId'=>'account_id'] as $field=>$key) {
            $value=$data[$field]??null;if ($value===null||$value===0) continue;
            try { $id=ErrorObject::id($value,$field==='accountId');$metadata[$key]=str_contains($id,'-')?$id:(int)$id; } catch (\InvalidArgumentException) {}
        }
        foreach (['crmHost','accountDomain'] as $field) if (is_string($data[$field]??null)&&ErrorObject::crmHost($data[$field])) $metadata['account_domain']=$data[$field];
        if (is_string($data['kind']??null)&&preg_match('/^[a-z0-9_]{1,100}$/D',$data['kind'])) $metadata['context_kind']=$data['kind'];
        $typed=$data['context_items']??null;$identity=[];
        if (is_array($typed)&&array_is_list($typed)) foreach($typed as $item) if($item instanceof \Leolnid\Common\Reporting\Errors\ErrorContextItem&&$item->id!==null) $identity[]=['key'=>$item->key,'id'=>$item->id];
        if($identity!==[]) $metadata['context_identity']=$identity;
        return $metadata;
    }
    private static function command(mixed $name, array $definition): string
    {
        return is_string($name)&&preg_match('/^[A-Za-z0-9:_-]{1,100}$/D',$name)&&(isset($definition['operations']['command:'.$name])||in_array($name,['app-errors:deliver','app-errors:test','reports:daily'],true))?$name:'unknown';
    }
}
