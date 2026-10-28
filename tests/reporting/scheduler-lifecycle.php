<?php
declare(strict_types=1);
$vendor=getenv('SCHEDULER_FIXTURE_AUTOLOAD')?:($_SERVER['SCHEDULER_FIXTURE_AUTOLOAD']??$argv[1]??'');
if($vendor===''||!is_file($vendor))throw new RuntimeException('Pass an installed Laravel vendor/autoload.php path');
putenv('SCHEDULER_FIXTURE_AUTOLOAD='.$vendor);
require $vendor;
spl_autoload_register(static function($class){$prefix='Leolnid\\Common\\Reporting\\';if(str_starts_with($class,$prefix))require __DIR__.'/../../src/Reporting/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';},true,true);
$candidateCapture=realpath(__DIR__.'/../../src/Reporting/Capture');
foreach ([Leolnid\Common\Reporting\Capture\LaravelErrorReporter::class,Leolnid\Common\Reporting\Capture\LaravelErrorServiceProvider::class,Leolnid\Common\Reporting\Capture\ScheduledRunBridge::class] as $candidateClass) {
    $loadedFrom=realpath((new ReflectionClass($candidateClass))->getFileName());
    if($candidateCapture===false||$loadedFrom===false||!str_starts_with($loadedFrom,$candidateCapture.DIRECTORY_SEPARATOR)) throw new RuntimeException('Scheduler fixture did not load candidate source: '.$candidateClass);
}

final class SchedulerFixtureRuntime
{
    public static ?object $app=null;
    public static ?object $cache=null;
    public static string $root='';
    public static function boot(string $root): void
    {
        self::$root=$root;
        self::$app=new Illuminate\Foundation\Application($root);
        self::$app->instance('config',new Illuminate\Config\Repository(['app_errors'=>['enabled'=>true,'telegram'=>['bot_token'=>'fixture-only','chat_id'=>'-1000000000000','message_thread_id'=>1],'project_index'=>0,'dedup_seconds'=>60,'project'=>'scheduler-fixture','server'=>'local','display_name'=>'Scheduler fixture']]));
        self::$cache=new SchedulerFixtureCache($root.'/shared-cache');
        self::$app->instance('cache',self::$cache);
        self::$app->instance('log',new SchedulerFixtureLogger());
        $events=new Illuminate\Events\Dispatcher(self::$app);
        self::$app->instance('events',$events);
        Illuminate\Support\Facades\Cache::setFacadeApplication(self::$app);
        Illuminate\Support\Facades\Log::setFacadeApplication(self::$app);
        Illuminate\Support\Facades\Event::setFacadeApplication(self::$app);
    }
}

final class SchedulerFixtureCache
{
    public function __construct(private string $directory){if(!is_dir($directory))mkdir($directory,0700,true);}
    private function path(string $key): string{return $this->directory.'/'.hash('sha256',$key).'.cache';}
    private function throwing(string $key): bool{return str_contains($key,'scheduler-delivered')&&getenv('SCHEDULER_FIXTURE_CACHE_THROW')==='1';}
    public function get(string $key,mixed $default=null): mixed{if($this->throwing($key))throw new RuntimeException('fixture cache failure');$p=$this->path($key);return is_file($p)?unserialize((string)file_get_contents($p),['allowed_classes'=>false]):$default;}
    public function put(string $key,mixed $value,mixed $ttl=null): bool{if($this->throwing($key))throw new RuntimeException('fixture cache failure');file_put_contents($this->path($key),serialize($value),LOCK_EX);return true;}
    public function add(string $key,mixed $value,mixed $ttl=null): bool{$p=$this->path($key);$h=@fopen($p,'x');if($h===false)return false;fwrite($h,serialize($value));fclose($h);return true;}
    public function forget(string $key): bool{$p=$this->path($key);return !is_file($p)||unlink($p);}
    public function lock(string $key,int $seconds=0): object{return new class {public function block(int $seconds,callable $callback):mixed{return $callback();}};}
}
final class SchedulerFixtureLogger
{
    public function channel(string $name): self{return $this;}
    public function log(string $level,string $message,array $context=[]): void{SchedulerFixtureRuntime::$cache?->put('fixture:last_log',['level'=>$level,'message'=>$message,'reason_code'=>$context['reason_code']??null,'event_kind'=>$context['event_kind']??null,'exception_class'=>$context['exception_class']??null,'observer_failure_class'=>$context['observer_failure_class']??null],600);}
}
final class SchedulerFixtureTransport implements Leolnid\Common\Reporting\Delivery\NotificationTransport
{
    public function send(array $payload): array
    {
        $parent=(int)getenv('SCHEDULER_FIXTURE_PARENT_PID');$child=getmypid()!==$parent;
        if($child&&getenv('SCHEDULER_FIXTURE_CHILD_MODE')==='throw')throw new RuntimeException('synthetic transport failure');
        $count=(int)SchedulerFixtureRuntime::$cache->get('fixture:sent',0)+1;SchedulerFixtureRuntime::$cache->put('fixture:sent',$count,600);
        $records=SchedulerFixtureRuntime::$cache->get('fixture:sent_records',[]);$records[]=['child'=>$child,'text'=>$payload['text']??''];SchedulerFixtureRuntime::$cache->put('fixture:sent_records',$records,600);
        if($child){$ids=SchedulerFixtureRuntime::$cache->get('fixture:child_ids',[]);$ids[]=getenv('LEOLNID_REPORTING_SCHEDULE_RUN_ID')?:'';SchedulerFixtureRuntime::$cache->put('fixture:child_ids',$ids,600);}
        return ['message_id'=>$count];
    }
}
final class SchedulerFixtureOutbox
{
    public function __construct(Leolnid\Common\Reporting\Delivery\NotificationTransport $transport){}
    public function put(array $payload,Throwable $failure): void{$count=(int)SchedulerFixtureRuntime::$cache->get('fixture:outbox',0)+1;SchedulerFixtureRuntime::$cache->put('fixture:outbox',$count,600);}
}
final class SchedulerFixtureDefinition
{
    public const VALUE=['project'=>'scheduler-fixture','display_name'=>'Scheduler fixture','project_url'=>'https://bp.amorenat.ru/admin','operations'=>['command:fixture:run'=>'Локальная проверка'],'default_service'=>'internal'];
}
final class SchedulerFixtureReporter extends Leolnid\Common\Reporting\Capture\LaravelErrorReporter
{
    protected const DEFINITION_CLASS=SchedulerFixtureDefinition::class;
    protected const OUTBOX_CLASS=SchedulerFixtureOutbox::class;
    protected const APPROVED_CHAT_ID='-1000000000000';
}
final class SchedulerFixtureDeliveryCommand {}
final class SchedulerFixtureProvider extends Leolnid\Common\Reporting\Capture\LaravelErrorServiceProvider
{
    protected const REPORTER_CLASS=SchedulerFixtureReporter::class;
    protected const TRANSPORT_CONTRACT=Leolnid\Common\Reporting\Delivery\NotificationTransport::class;
    protected const TRANSPORT_CLASS=SchedulerFixtureTransport::class;
    protected const DELIVERY_COMMAND=SchedulerFixtureDeliveryCommand::class;
    protected const REGISTERED_SCHEDULED_COMMANDS=['fixture:run'];
}
final class SchedulerFixtureHandler
{
    public bool $skip=false;
    private array $callbacks=[];
    public function reportable(callable $callback): void{$this->callbacks[]=$callback;}
    public function report(Throwable $error): void{if($this->skip)return;foreach($this->callbacks as $callback)$callback($error);}
}
final class SchedulerFixtureMutex implements Illuminate\Console\Scheduling\EventMutex
{
    public function create(Illuminate\Console\Scheduling\Event $event){return true;}
    public function exists(Illuminate\Console\Scheduling\Event $event){return false;}
    public function forget(Illuminate\Console\Scheduling\Event $event): void{}
}
final class SchedulerFixtureComponents
{
    public function task(string $description,callable $callback): mixed{return $callback();}
    public function bulletList(array $items): void{}
}
final class SchedulerFixtureRunCommand extends Illuminate\Console\Scheduling\ScheduleRunCommand
{
    public function runFixture(Illuminate\Console\Scheduling\Event $event,object $app,object $dispatcher,object $handler): void
    {
        $this->laravel=$app;$this->dispatcher=$dispatcher;$this->handler=$handler;$this->phpBinary=PHP_BINARY;$this->components=new SchedulerFixtureComponents();$this->runEvent($event);
    }
}

if(getenv('SCHEDULER_FIXTURE_CHILD')==='1'){
    $root=(string)getenv('SCHEDULER_FIXTURE_ROOT');
    if($root==='')exit(31);
    SchedulerFixtureRuntime::boot($root);
    SchedulerFixtureRuntime::$app->instance(Leolnid\Common\Reporting\Delivery\NotificationTransport::class,new SchedulerFixtureTransport());
    $reporter=SchedulerFixtureRuntime::$app->make(SchedulerFixtureReporter::class);
    $reporter->setContext(['source'=>'scheduler','scheduled_command'=>'fixture:run']);
    if(getenv('SCHEDULER_FIXTURE_CHILD_MODE')!=='silent'){$ok=$reporter->report(new RuntimeException('private child failure body token=fixture-secret'));SchedulerFixtureRuntime::$cache->put('fixture:child_result',['ok'=>$ok,'ready'=>$reporter->ready(),'receipt'=>$reporter->lastReceipt,'run_id'=>getenv('LEOLNID_REPORTING_SCHEDULE_RUN_ID')?:null],600);}
    exit(1);
}

function schedulerFixtureEnv(string $key,string $value): void{putenv($key.'='.$value);$_ENV[$key]=$value;$_SERVER[$key]=$value;}

$root=sys_get_temp_dir().'/schedule-run-'.bin2hex(random_bytes(8));mkdir($root,0700,true);mkdir($root.'/storage',0700,true);
$autoload=realpath($vendor);$fixture=realpath(__FILE__);$artisan=$root.'/artisan';
schedulerFixtureEnv('SCHEDULER_FIXTURE_ROOT',$root);schedulerFixtureEnv('SCHEDULER_FIXTURE_CHILD','1');schedulerFixtureEnv('SCHEDULER_FIXTURE_PARENT_PID',(string)getmypid());
file_put_contents($artisan,"<?php require getenv('SCHEDULER_FIXTURE_AUTOLOAD'); require ".var_export($fixture,true).";\n");
schedulerFixtureEnv('SCHEDULER_FIXTURE_AUTOLOAD',$autoload);
SchedulerFixtureRuntime::boot($root);
$app=SchedulerFixtureRuntime::$app;$dispatcher=$app->make('events');$handler=new SchedulerFixtureHandler();$app->instance(Illuminate\Contracts\Debug\ExceptionHandler::class,$handler);
$provider=new SchedulerFixtureProvider($app);$provider->register();$provider->boot();$app->make(Illuminate\Contracts\Debug\ExceptionHandler::class);
$mutex=new SchedulerFixtureMutex();$runner=new SchedulerFixtureRunCommand();
$run=function(string $mode,bool $throwOnCache=false)use($root,$artisan,$mutex,$runner,$app,$dispatcher,$handler):void{
    schedulerFixtureEnv('SCHEDULER_FIXTURE_CHILD_MODE',$mode);schedulerFixtureEnv('SCHEDULER_FIXTURE_CACHE_THROW',$throwOnCache?'1':'0');
    $command='"'.PHP_BINARY.'" "'.$artisan.'" fixture:run';$event=(new Illuminate\Console\Scheduling\Event($mutex,$command))->sendOutputTo($root.'/task-output.log');
    $runner->runFixture($event,$app,$dispatcher,$handler);
    $GLOBALS['scheduler_fixture_last_exit']=$event->exitCode;
    if(!$handler->skip&&getenv('LEOLNID_REPORTING_SCHEDULE_RUN_ID')!==false)throw new RuntimeException('scheduler run id leaked after event completion');
};
$run('deliver');
$sent=(int)SchedulerFixtureRuntime::$cache->get('fixture:sent',0);if($sent!==1)throw new RuntimeException('child receipt bridge did not suppress the scheduler wrapper');
$run('throw');$sent2=(int)SchedulerFixtureRuntime::$cache->get('fixture:sent',0);$outbox=(int)SchedulerFixtureRuntime::$cache->get('fixture:outbox',0);if($sent2!==2||$outbox!==1)throw new RuntimeException('child outbox did not leave the parent alert enabled');
$run('silent');if((int)SchedulerFixtureRuntime::$cache->get('fixture:sent',0)!==3)throw new RuntimeException('missing child detail did not produce parent failure alert');
$run('deliver',true);$sent=(int)SchedulerFixtureRuntime::$cache->get('fixture:sent',0);if($sent!==5)throw new RuntimeException('marker cache failure did not fail open');
$ids=SchedulerFixtureRuntime::$cache->get('fixture:child_ids',[]);$run('deliver');$run('deliver');$ids=SchedulerFixtureRuntime::$cache->get('fixture:child_ids',[]);$tail=array_slice($ids,-2);if(count($tail)!==2||$tail[0]===''||$tail[1]===''||$tail[0]===$tail[1])throw new RuntimeException('separate actual scheduled runs shared an id');
if((int)SchedulerFixtureRuntime::$cache->get('fixture:sent',0)!==7)throw new RuntimeException('separate same-command runs were deduplicated');
$handler->skip=true;$run('silent');$stale=getenv('LEOLNID_REPORTING_SCHEDULE_RUN_ID');if(!is_string($stale)||preg_match('/^[a-f0-9]{32}$/D',$stale)!==1)throw new RuntimeException('failed handler fixture did not leave a pending run for recovery');
$backgroundTask=(object)['runInBackground'=>true];if(Leolnid\Common\Reporting\Capture\ScheduledRunBridge::begin($backgroundTask)!==null||Leolnid\Common\Reporting\Capture\ScheduledRunBridge::activeId()!==null||getenv('LEOLNID_REPORTING_SCHEDULE_RUN_ID')!==false)throw new RuntimeException('background task inherited stale foreground correlation state');
$handler->skip=false;$run('deliver');$ids=SchedulerFixtureRuntime::$cache->get('fixture:child_ids',[]);$tail=array_slice($ids,-1);if(count($tail)!==1||$tail[0]===$stale||(int)SchedulerFixtureRuntime::$cache->get('fixture:sent',0)!==9)throw new RuntimeException('a stale run ID crossed into the next task');
echo json_encode(['status'=>'PASS','framework'=>'Laravel ScheduleRunCommand + Event','candidate_source_loaded'=>true,'scenarios'=>['child_delivery_suppresses_parent_wrapper','outbox_pending_keeps_parent_alert','no_child_delivery_keeps_parent_alert','marker_cache_failure_fails_open','distinct_runs_are_not_suppressed','stale_handler_state_is_cleared_before_next_task','background_start_clears_stale_foreground_bridge'],'network_requests'=>0,'business_actions'=>0]);
