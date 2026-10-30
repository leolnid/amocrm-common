<?php
declare(strict_types=1);
namespace Leolnid\Common\Reporting\Capture;
use Illuminate\Support\Facades\{Cache,Log};
use Leolnid\Common\Reporting\Delivery\NotificationTransport;
use Leolnid\Common\Reporting\Errors\{ContextAdapter,Deduplicator,ErrorReporter,HealthCheckIdentity,OperationResolver,SourcePins};
abstract class LaravelErrorReporter
{
    protected const DEFINITION_CLASS='';
    protected const OUTBOX_CLASS=LaravelErrorOutbox::class;
    protected const APPROVED_CHAT_ID='';
    private array $definition;
    private array $context=[];
    private array $consoleStack=[];
    private ?string $failedConsole=null;
    private ?int $failedExit=null;
    private ?object $activeBusiness=null;
    private \WeakMap $business;
    private ErrorReporter $shared;
    public ?array $lastReceipt=null;
    public function __construct(NotificationTransport $transport)
    {
        $class=static::DEFINITION_CLASS;
        if (!is_string($class)||$class===''||!defined($class.'::VALUE')) throw new \LogicException('Explicit project error definition required');
        $this->definition=constant($class.'::VALUE');$this->definition['runtime_root']=base_path();$this->business=new \WeakMap;
        $repeatState=static function(string $key,int $ttl,string $action,int $acknowledged):array {
            $lock=Cache::lock('app-errors:repeat-lock:'.hash('sha256',$key),5);
            return $lock->block(2,static function()use($key,$ttl,$action,$acknowledged):array {
                $counter=$key.':repeat-count';
                if($action==='claim') {
                    $count=max(0,min(999999,(int)Cache::get($counter,0)));
                    if(Cache::add($key,true,$ttl)) return ['first'=>true,'repeat_count'=>$count];
                    Cache::put($counter,min(999999,$count+1),$ttl*2);
                    return ['first'=>false,'repeat_count'=>0];
                }
                if($action==='ack') {
                    $count=max(0,min(999999,(int)Cache::get($counter,0)));
                    $remaining=max(0,$count-max(0,$acknowledged));
                    if($remaining===0) Cache::forget($counter); else Cache::put($counter,$remaining,$ttl*2);
                }
                return ['first'=>false,'repeat_count'=>0];
            });
        };
        $this->shared=new ErrorReporter(new Deduplicator(static fn(string $key,int $ttl):bool=>Cache::add($key,true,$ttl),(int)config('app_errors.dedup_seconds',60),$repeatState),static fn(string $level,string $message,array $metadata)=>Log::channel('single')->log($level,$message,$metadata),fn():bool=>$this->ready(),fn(array $payload):array=>$transport->send($payload),function(array $payload,\Throwable $failure)use($transport):void {$class=static::OUTBOX_CLASS;(new $class($transport))->put($payload,$failure);},(string)config('app_errors.telegram.chat_id'),(int)config('app_errors.telegram.message_thread_id'));
    }
    public function setContext(array $context): void {$this->context=$context;$this->activeBusiness=null;}
    public function clearContext(): void {$this->context=[];$this->activeBusiness=null;}
    public function setBusinessContext(object $context): void {$this->activeBusiness=$context;}
    public function ready(): bool
    {
        return !is_file(storage_path('app/error-notifications/.delivery-paused'))&&(bool)config('app_errors.enabled')&&is_string(config('app_errors.telegram.bot_token'))&&config('app_errors.telegram.bot_token')!==''&&static::APPROVED_CHAT_ID!==''&&(string)config('app_errors.telegram.chat_id')===static::APPROVED_CHAT_ID&&(int)config('app_errors.telegram.message_thread_id')>0;
    }
    public function reportBusiness(\Throwable $error, object $context): bool {$this->business[$error]=$context;return $this->report($error);}
    public function attachBusinessContext(\Throwable $error, object $context): void {$this->business[$error]=$context;}
    protected function refineContext(?object $context, \Throwable $error): ?object {return $context;}
    /** BC hook for app-owned typed context assembled from already-collected safe metadata. */
    protected function refineContextForFailure(?object $context, \Throwable $error, array $metadata, array $scope): ?object {return $this->refineContext($context,$error);}
    public function reportFromHandler(\Throwable $error): bool
    {
        $wrapper=($this->context['source']??null)==='scheduler'&&($this->context['scheduled_wrapper_failure']??false)===true;
        try { return $this->report($error,false,($this->context['source']??'')!=='queue'); }
        finally { if($wrapper){ScheduledRunBridge::finish();$this->clearContext();} }
    }
    public function report(\Throwable $error, bool $test=false, bool $allowRemote=true): bool
    {
        $business=null;
        try {
            $scope=$this->context;
            if ($this->failedExit!==null && !in_array($scope['source']??null,['queue','scheduler'],true)) $scope+=['source'=>'console','command_exit_code'=>$this->failedExit];
            $metadata=LaravelMetadataContext::collect($error,$scope,$this->definition,$this->consoleSelector(),$test);
            $reportingCommand=$metadata['scheduled_command']??$metadata['console_command']??null;
            // A delivery failure must never enqueue another delivery notification.
            if (!$test&&in_array($reportingCommand,['app-errors:deliver','app-errors:test'],true)) $allowRemote=false;
            if(!$test&&($metadata['scheduled_wrapper_failure']??false)===true&&ScheduledRunBridge::isActiveParentId($metadata['scheduled_run_id']??null)&&ScheduledRunBridge::delivered($metadata))$allowRemote=false;
            if (!$test) $business=$this->refineContextForFailure($this->business[$error]??$this->activeBusiness,$error,$metadata,$scope);
            if($business!==null&&(($data=get_object_vars($business))['kind']??null)==='unmapped_loaded_context'&&!is_string($data['operation']??null)){
                $registered=OperationResolver::resolve($error,(string)config('app_errors.display_name',$this->definition['display_name']),$this->definition['project_url'],$metadata,$this->definition);
                if($registered!==null){$data['operation']=$registered->failure->code;$business=(object)$data;}
            }
            $name=(string)config('app_errors.display_name',$this->definition['display_name']);$url=$this->definition['project_url'];
            $message=($business!==null||$test)?ContextAdapter::normalize($name,$url,$business,$this->definition,$test,$error):OperationResolver::resolve($error,$name,$url,$metadata,$this->definition);
            if(!$test&&$business!==null&&!$this->canUseUnknownFallback($metadata))$allowRemote=false;
            $unknownFallback=false;
            if (!$test && $business===null && $message===null && $this->canUseUnknownFallback($metadata)) {
                $message=ContextAdapter::normalize($name,$url,null,$this->definition,false,$error);
                $unknownFallback=$message!==null;
                if ($unknownFallback && $this->isBenignUnknownHttpFailure($error,$metadata)) $allowRemote=false;
            }
            $metadata=LaravelMetadataContext::withIdentity($metadata,$business,$message);
            if ($message!==null) {
                $items=$message->context;
                $runtimeContext=$metadata['scheduled_command']??$metadata['console_command']??$metadata['job_class']??null;
                if (is_string($runtimeContext)&&$runtimeContext!=='unknown'&&preg_match('/^[A-Za-z_][A-Za-z0-9_\\\\:_-]{0,119}$/D',$runtimeContext)) {
                    $label=isset($metadata['job_class'])?'Задание':'Команда';
                    $items[]=\Leolnid\Common\Reporting\Errors\ErrorContextItem::loaded('runtime_operation',$label,null,$runtimeContext);
                }
                if (!$test&&$message->reason->code==='processing_error') {
                    $chain=\Leolnid\Common\Reporting\Errors\FailureClassifier::diagnosticChain($error);$rootCause=$chain[array_key_last($chain)];
                    $items[]=\Leolnid\Common\Reporting\Errors\ErrorContextItem::loaded('error_source','Место ошибки',null,$rootCause['source_file'].':'.$rootCause['source_line']);
                    if (is_int($rootCause['exception_code'])&&$rootCause['exception_code']!==0) $items[]=\Leolnid\Common\Reporting\Errors\ErrorContextItem::loaded('exception_code','Код исключения',null,(string)$rootCause['exception_code']);
                }
                $message=new \Leolnid\Common\Reporting\Errors\ErrorMessage($message->project,$message->projectUrl,$message->failure,$message->reason,$message->objects,$message->test,$items,$message->eventCode,$message->repeatCount);
            }
            if ($unknownFallback) $metadata['unknown_operation_fallback']=true;
            $health=null;
            $healthAction=match ($metadata['scheduled_command']??$metadata['console_command']??'') {'health:check'=>'health_checks','health:queue-check-heartbeat'=>'queue_heartbeat','health:schedule-check-heartbeat'=>'scheduler_heartbeat',default=>null};
            $healthAction=match($metadata['job_class']??'') {'Spatie\\Health\\Jobs\\RunHealthChecksJob'=>'health_checks','Spatie\\Health\\Jobs\\HealthQueueJob'=>'queue_heartbeat',default=>$healthAction};
            if ($healthAction!==null) $health=new HealthCheckIdentity($metadata['project'],$metadata['server'],$healthAction);
            $result=$this->shared->report($error,$message,$metadata,$allowRemote,$health);$this->lastReceipt=$this->shared->lastReceipt;if($result)ScheduledRunBridge::recordDelivered($metadata);return $result;
        } catch (\Throwable $failure) {
            // Even a broken adapter retains the original failure locally, without raw exception text.
            $failureClass=$failure::class;$parts=explode(chr(92),$failureClass);foreach($parts as $part)if(preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,119}$/D',$part)!==1){$failureClass='Throwable';break;}if(strlen($failureClass)>120)$failureClass='Throwable';
            $this->shared->report($error,null,['project'=>$this->definition['project'],'source'=>'console','exception_class'=>'Throwable','observer_context_failed'=>true,'observer_failure_class'=>$failureClass],false);return false;
        }
    }
    private function canUseUnknownFallback(array $metadata): bool
    {
        $job=$metadata['job_class']??null;
        if (($metadata['source']??null)!=='queue' || !is_string($job) || !str_starts_with($job,'Closure ')) return true;
        $pin=$this->definition['closure_source_pins'][$job]??null;
        return is_array($pin)&&SourcePins::verify($this->definition['runtime_root']??'', $pin);
    }
    private function isBenignUnknownHttpFailure(\Throwable $error,array $metadata): bool
    {
        if (($metadata['source']??null)!=='http') return false;
        foreach (['Illuminate\\Validation\\ValidationException','Illuminate\\Auth\\AuthenticationException','Illuminate\\Auth\\Access\\AuthorizationException','Illuminate\\Database\\Eloquent\\ModelNotFoundException','Symfony\\Component\\HttpKernel\\Exception\\NotFoundHttpException','Symfony\\Component\\HttpKernel\\Exception\\AccessDeniedHttpException','Symfony\\Component\\HttpKernel\\Exception\\UnauthorizedHttpException'] as $class) if (is_a($error,$class)) return true;
        if (is_a($error,'Symfony\\Component\\HttpKernel\\Exception\\HttpExceptionInterface') && method_exists($error,'getStatusCode')) return in_array($error->getStatusCode(),[401,403,404,422],true);
        return false;
    }
    public function reportLegacy(?\Throwable $error, string $channel): bool
    {
        if ($error!==null) return $this->report($error);
        if (!preg_match('/^[A-Za-z0-9_.-]{1,64}$/D',$channel)) $channel='unknown';
        $synthetic=new \RuntimeException();
        try {$metadata=LaravelMetadataContext::collect($synthetic,$this->context,$this->definition,$this->consoleSelector(),false);}
        catch (\Throwable) {$metadata=['project'=>$this->definition['project'],'source'=>'console','exception_class'=>'RuntimeException'];}
        return $this->shared->report($synthetic,null,$metadata+['legacy_channel'=>$channel,'legacy_exception_missing'=>true,'classification_gap'=>true],false);
    }
    public function sendTest(): bool {return $this->report(new \RuntimeException(),true);}
    public function consoleStarting(string $name): void
    {
        if (!$this->consoleStack) {$this->failedConsole=null;$this->failedExit=null;}
        if (count($this->consoleStack)>=16) {$this->clearConsoleContext();return;}
        $this->consoleStack[]=(isset($this->definition['operations']['command:'.$name])||in_array($name,['app-errors:deliver','app-errors:test','reports:daily'],true))?$name:'unknown';
    }
    public function consoleFinished(string $name, int $exitCode): void
    {
        $name=(isset($this->definition['operations']['command:'.$name])||in_array($name,['app-errors:deliver','app-errors:test','reports:daily'],true))?$name:'unknown';
        if (!$this->consoleStack||array_pop($this->consoleStack)!==$name) {$this->clearConsoleContext();return;}
        $this->failedConsole=$exitCode!==0&&$name!=='unknown'?$name:null;$this->failedExit=$this->failedConsole!==null&&$exitCode>0&&$exitCode<=255?$exitCode:null;
    }
    public function clearConsoleContext(): void {$this->consoleStack=[];$this->failedConsole=null;$this->failedExit=null;}
    private function consoleSelector(): ?string
    {
        foreach (array_reverse($this->consoleStack)as$name) if ($name!=='unknown') return $name;
        return !$this->consoleStack?$this->failedConsole:null;
    }
}
