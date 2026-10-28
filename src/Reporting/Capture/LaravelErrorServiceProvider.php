<?php
namespace Leolnid\Common\Reporting\Capture;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Throwable;
abstract class LaravelErrorServiceProvider extends ServiceProvider
{
    protected const REPORTER_CLASS='';
    protected const TRANSPORT_CONTRACT='';
    protected const TRANSPORT_CLASS='';
    protected const DELIVERY_COMMAND='';
    protected const REGISTERED_SCHEDULED_COMMANDS=[];
    private const DELIVERY_NAME='app-errors:deliver';
    private function reporter(): object { return $this->app->make(static::REPORTER_CLASS); }

    private function scheduledCommand(string $command): string
    {
        if(!preg_match('/\A(?:\x27([^\x27]+)\x27|\x22([^\x22]+)\x22|(\S+))\s+(?:\x27([^\x27]+)\x27|\x22([^\x22]+)\x22|(\S+))\s+([A-Za-z0-9:_-]+)(?:\s|$)/D',$command,$match))return 'unknown';
        $binary=$match[1]!==''?$match[1]:($match[2]!==''?$match[2]:$match[3]);
        $artisan=$match[4]!==''?$match[4]:($match[5]!==''?$match[5]:$match[6]);
        $expected=realpath(base_path('artisan'));
        if(!is_string($expected)||realpath($binary)!==realpath(PHP_BINARY)||realpath($artisan)!==$expected)return 'unknown';
        return $match[7]===self::DELIVERY_NAME||in_array($match[7],static::REGISTERED_SCHEDULED_COMMANDS,true)?$match[7]:'unknown';
    }
    public function register(): void
    {
        foreach([static::REPORTER_CLASS,static::TRANSPORT_CONTRACT,static::TRANSPORT_CLASS,static::DELIVERY_COMMAND]as$class)if(!is_string($class)||$class==='')throw new \LogicException('Reporting capture requires an explicit application adapter');
        $this->app->singleton(static::TRANSPORT_CONTRACT, static::TRANSPORT_CLASS);
        if(static::TRANSPORT_CONTRACT!==\Leolnid\Common\Reporting\Delivery\NotificationTransport::class)$this->app->alias(static::TRANSPORT_CONTRACT,\Leolnid\Common\Reporting\Delivery\NotificationTransport::class);
        $this->app->singleton(static::REPORTER_CLASS);
        $this->app->alias(static::REPORTER_CLASS,LaravelErrorReporter::class);
    }
    private function jobName(object $job): string
    {
        try { return (string) $job->resolveName(); } catch (Throwable) { return 'unknown'; }
    }
    public function boot(): void
    {
        if ($this->app->runningInConsole()) $this->commands([static::DELIVERY_COMMAND]);
        $this->callAfterResolving(\Illuminate\Console\Scheduling\Schedule::class, static function ($schedule): void {
            $schedule->command(self::DELIVERY_NAME)->everyMinute()->withoutOverlapping();
        });
        $this->callAfterResolving(ExceptionHandler::class, function ($handler): void {
            $handler->reportable(function (Throwable $error): void { try { $this->reporter()->reportFromHandler($error); } catch (Throwable) { error_log('Application error observer unavailable'); } });
        });
        Event::listen(JobProcessing::class, function (JobProcessing $event): void { try { 
            $this->reporter()->setContext(['source' => 'queue', 'job_class' => $this->jobName($event->job)]);
         } catch (Throwable) { error_log('Application event observer unavailable'); } });
        Event::listen(JobFailed::class, function (JobFailed $event): void { try { 
            $reporter = $this->reporter();
            $reporter->setContext(['source' => 'queue', 'job_class' => $this->jobName($event->job)]);
            $reporter->report($event->exception);
         } catch (Throwable) { error_log('Application event observer unavailable'); } });
        foreach ([JobProcessed::class, Looping::class, \Illuminate\Foundation\Http\Events\RequestHandled::class] as $eventClass) Event::listen($eventClass, function (): void { try { $this->reporter()->clearContext(); $this->reporter()->clearConsoleContext(); } catch (Throwable) { error_log('Application context observer unavailable'); } });
        Event::listen(ScheduledTaskStarting::class, function (ScheduledTaskStarting $event): void { try {
            $task=$event->task;$command=$this->scheduledCommand((string)($task->command??''));if($command===self::DELIVERY_NAME){ScheduledRunBridge::finish();$runId=null;}else{$runId=ScheduledRunBridge::begin($task);}
            $context=['source'=>'scheduler','scheduled_command'=>$command];if($runId!==null)$context['scheduled_run_id']=$runId;
            $this->reporter()->setContext($context);
         } catch (Throwable) { error_log('Application event observer unavailable'); } });
        Event::listen(ScheduledTaskFailed::class, function (ScheduledTaskFailed $event): void { try {
            if ($this->scheduledCommand((string)($event->task->command??''))===self::DELIVERY_NAME) {error_log('Application error outbox scheduler failed');ScheduledRunBridge::finish($event->task);return;}
            $reporter = $this->reporter();
            $context=['source'=>'scheduler','scheduled_command'=>$this->scheduledCommand((string)($event->task->command??'')),'scheduled_wrapper_failure'=>true];$runId=ScheduledRunBridge::activeIdFor($event->task);if($runId!==null)$context['scheduled_run_id']=$runId;
            $reporter->setContext($context);
            $reporter->report($event->exception);
         } catch (Throwable) { error_log('Application event observer unavailable'); } });
        foreach ([ScheduledTaskFinished::class, \Illuminate\Console\Events\ScheduledBackgroundTaskFinished::class] as $finishedClass) Event::listen($finishedClass, function ($event): void { try {
            if ($this->scheduledCommand((string)($event->task->command??''))===self::DELIVERY_NAME) {if((int)($event->task->exitCode??0)!==0)error_log('Application error outbox requires review');ScheduledRunBridge::finish($event->task);$this->reporter()->clearContext();return;}
            $failed=(int)($event->task->exitCode??0)!==0;
            try {
                $reporter=$this->reporter();
                if ($failed) {
                    $name=$this->scheduledCommand((string)($event->task->command??''));
                    $exit=$event->task->exitCode??null;
                    $valid=is_int($exit)&&$exit>=1&&$exit<=255;
                    $context=['source'=>'scheduler','scheduled_command'=>$valid?$name:'unknown','scheduled_exit_failure'=>true,'scheduled_wrapper_failure'=>true];$runId=ScheduledRunBridge::activeIdFor($event->task);if($runId!==null)$context['scheduled_run_id']=$runId;
                    $reporter->setContext($context+($valid?['command_exit_code'=>$exit]:[]));
                    $reporter->report(new \RuntimeException());
                }
                // Laravel dispatches ScheduledTaskFinished before it throws for a
                // non-zero child exit; the resulting ScheduledTaskFailed and
                // exception-handler callbacks still need this run's receipt marker.
                if(!$failed)$reporter->clearContext();
            } catch (Throwable) { error_log('Application scheduler observer unavailable'); }
            finally { if(!$failed)ScheduledRunBridge::finish($event->task); }
         } catch (Throwable) { error_log('Application event observer unavailable'); } });
        Event::listen(\Illuminate\Console\Events\CommandStarting::class, function ($event): void { try {
            if(is_string($event->command??null)) $this->reporter()->consoleStarting($event->command);
        } catch (Throwable) { error_log('Console context observer unavailable'); } });
        Event::listen(\Illuminate\Console\Events\CommandFinished::class, function ($event): void { try {
            if(is_string($event->command??null)&&is_int($event->exitCode??null)) $this->reporter()->consoleFinished($event->command,$event->exitCode);
            if(($event->command??null)==='schedule:run'){ScheduledRunBridge::finish();$this->reporter()->clearContext();}
        } catch (Throwable) { error_log('Console context observer unavailable'); } });
        Event::listen(\Illuminate\Foundation\Events\Terminating::class, function (): void { try {
            ScheduledRunBridge::finish();
            $this->reporter()->clearConsoleContext();
        } catch (Throwable) { error_log('Console context observer unavailable'); } });
    }
}
