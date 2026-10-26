<?php
declare(strict_types=1);
namespace Illuminate\Validation { final class ValidationException extends \RuntimeException {} }
namespace Illuminate\Support\Facades {
    final class Cache {
        private static array $values=[];
        public static function add(string $key,mixed $value,int $ttl=0): bool {if(array_key_exists($key,self::$values))return false;self::$values[$key]=$value;return true;}
        public static function get(string $key,mixed $default=null): mixed {return self::$values[$key]??$default;}
        public static function put(string $key,mixed $value,int $ttl=0): void {self::$values[$key]=$value;}
        public static function forget(string $key): void {unset(self::$values[$key]);}
        public static function lock(string $key,int $seconds): object {return new class {public function block(int $seconds,callable $callback): mixed{return $callback();}};}
    }
    final class Log { public static array $entries=[]; public static function channel(string $name): object {return new class {public function log(string $level,string $message,array $metadata=[]): void {\Illuminate\Support\Facades\Log::$entries[]=['message'=>$message,'metadata'=>$metadata];}};} }
}
namespace {
    function base_path(): string {return '/private/stage';}
    function storage_path(string $path=''): string {return '/private/stage/storage'.($path===''?'':'/'.$path);}
    function config(string $key,mixed $default=null): mixed {static $map=['app_errors.enabled'=>true,'app_errors.telegram.bot_token'=>'synthetic-only','app_errors.telegram.chat_id'=>'-1000000000000','app_errors.telegram.message_thread_id'=>1,'app_errors.project_index'=>0,'app_errors.dedup_seconds'=>60,'app_errors.project'=>'pipeline','app_errors.server'=>'fixture'];return $map[$key]??$default;}
    function app(): object {return new class {public function bound(string $key): bool{return false;}};}
    spl_autoload_register(static function($class){$prefix='Leolnid\\Common\\Reporting\\';if(str_starts_with($class,$prefix))require __DIR__.'/../../src/Reporting/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';});
    use Leolnid\Common\Reporting\Capture\{AbstractLoadedContextObserver,LaravelErrorReporter};
    use Leolnid\Common\Reporting\Delivery\NotificationTransport;
    use Leolnid\Common\Reporting\Errors\ErrorContextItem;
    final class LoadedContextObserverFixture extends AbstractLoadedContextObserver { protected const LABELS=['order'=>'Заказ','deal'=>'Сделка']; }
    final class LoadedEntityFixture {public function __construct(public int $id,public string $name){}}
    final class PipelineDefinition { public const VALUE=['project'=>'pipeline','display_name'=>'Pipeline','project_url'=>'https://bp.amorenat.ru/admin','operations'=>['job:App\\Jobs\\SyncOrder'=>'Синхронизация','job:sync'=>'Синхронизация'],'closure_source_pins'=>['Closure fixture'=>['path'=>'/not-present/closure.php','sha256'=>'0000000000000000000000000000000000000000000000000000000000000000']],'context_catalog'=>['order'=>'Заказ'],'default_service'=>'internal','kinds'=>['job_failed'=>['action'=>['registered_field'=>'operation','catalog'=>['job:sync'=>'Синхронизация']],'reason'=>['code'=>'database_error'],'context'=>[]]]]; }
    final class PipelineTransport implements NotificationTransport {public array $sent=[];public function send(array $payload):array{$this->sent[]=$payload;return ['message_id'=>1];}}
    final class PipelineReporter extends LaravelErrorReporter {
        public bool $unknownMode=false;public bool $returnContext=true;public bool $observerMode=false;public int $orderId=42;
        protected const DEFINITION_CLASS=PipelineDefinition::class;protected const APPROVED_CHAT_ID='-1000000000000';
        protected function refineContextForFailure(?object $context,\Throwable $error,array $metadata,array $scope):?object {
            if($this->observerMode)return LoadedContextObserverFixture::merge(null,$error);
            if(!$this->returnContext)return null;
            if(($metadata['source']??null)!=='queue'||($metadata['job_class']??null)!=='App\\Jobs\\SyncOrder'||($scope['source']??null)!=='queue')throw new \RuntimeException('pipeline metadata missing');
            return $this->unknownMode ? (object)['kind'=>'unmapped','operation'=>'job:not_registered','context_items'=>[ErrorContextItem::loaded('order','Заказ',$this->orderId,'Заказ '.$this->orderId,'https://bp.amorenat.ru/admin/orders/'.$this->orderId)]] : (object)['kind'=>'job_failed','operation'=>'job:sync','context_items'=>[ErrorContextItem::loaded('order','Заказ',$this->orderId,'Заказ '.$this->orderId,'https://bp.amorenat.ru/admin/orders/'.$this->orderId)]];
        }
    }
    $transport=new PipelineTransport();$reporter=new PipelineReporter($transport);$reporter->setContext(['source'=>'queue','job_class'=>'App\\Jobs\\SyncOrder']);$ok=$reporter->report(new \RuntimeException('safe database failure'));
    if(!$ok||count($transport->sent)!==1||!str_contains($transport->sent[0]['text'],'<b>Заказ</b>: <a href="https://bp.amorenat.ru/admin/orders/42">Заказ 42 (42)</a>')||!str_contains($transport->sent[0]['text'],'Синхронизация'))throw new \RuntimeException('actual reporter pipeline fixture failed');
    $reporter->unknownMode=true;$unknownOk=$reporter->report(new \RuntimeException('safe unknown detail'));$last=$transport->sent[1]['text']??'';$correlated=false;foreach(\Illuminate\Support\Facades\Log::$entries as $entry)if(($entry['message']??null)==='Application error'&&preg_match('/^[a-f0-9]{16}$/D',$entry['metadata']['correlation_id']??''))$correlated=true;if(!$unknownOk||!str_contains($last,'<b>Заказ</b>:')||!str_contains($last,'unknown detail')||!str_contains($last,'<b>Код события:</b>')||!$correlated)throw new \RuntimeException('unknown error fallback pipeline fixture failed');
    $reporter->orderId=43;$distinct=$reporter->report(new \RuntimeException('safe unknown detail'));$duplicate=$reporter->report(new \RuntimeException('safe unknown detail'));$contextIdentity=false;foreach(\Illuminate\Support\Facades\Log::$entries as $entry)if(($entry['metadata']['context_identity'][0]['key']??null)==='order'&&($entry['metadata']['context_identity'][0]['id']??null)==='43')$contextIdentity=true;if(!$distinct||$duplicate||count($transport->sent)!==3||!$contextIdentity)throw new \RuntimeException('typed context dedup identity fixture failed');
    $reporter->returnContext=false;$reporter->setContext(['source'=>'queue','job_class'=>'App\\Jobs\\Unmapped']);$unknownOperation=$reporter->report(new \RuntimeException('unexpected import failure'));$unknownBody=$transport->sent[3]['text']??'';if(!$unknownOperation||!str_contains($unknownBody,'Операция приложения не завершена')||!str_contains($unknownBody,'unexpected import failure')||!str_contains($unknownBody,'<b>Код события:</b>')||str_contains($unknownBody,'<b>Контекст</b>:'))throw new \RuntimeException('unregistered operation fallback was lost');
    $reporter->setContext(['source'=>'http']);$before=count($transport->sent);$benign=$reporter->report(new \Illuminate\Validation\ValidationException('validation rejected'));if($benign||count($transport->sent)!==$before)throw new \RuntimeException('benign validation noise was sent');
    $observerError=new \RuntimeException('observer fixture');LoadedContextObserverFixture::attachEntity($observerError,'deal',new LoadedEntityFixture(88,'Loaded deal'));LoadedContextObserverFixture::attachId($observerError,'order',99,'Loaded order');LoadedContextObserverFixture::attachId($observerError,'order',99,'Duplicate name');$merged=LoadedContextObserverFixture::merge((object)['kind'=>'unmapped','operation'=>'job:App\\Jobs\\SyncOrder'],$observerError);if(count($merged->context_items??[])!==2||($merged->operation??null)!=='job:App\\Jobs\\SyncOrder'||$merged->context_items[0]->name!=='Loaded deal')throw new \RuntimeException('shared loaded-context WeakMap merge/dedup fixture failed');
    $observerReporter=new PipelineReporter($observerTransport=new PipelineTransport());$observerReporter->observerMode=true;$observerReporter->setContext(['source'=>'queue','job_class'=>'App\\Jobs\\SyncOrder']);$observerError=new \RuntimeException('loaded observer failure');LoadedContextObserverFixture::attachEntity($observerError,'order',new LoadedEntityFixture(63,'Loaded order'));if(!$observerReporter->report($observerError)||!str_contains($observerTransport->sent[0]['text'],'Синхронизация')||!str_contains($observerTransport->sent[0]['text'],'<b>Заказ</b>:'))throw new \RuntimeException('observer merge trusted-operation enrichment failed');
    $closureTransport=new PipelineTransport();$closureReporter=new PipelineReporter($closureTransport);$closureReporter->observerMode=true;$closureReporter->setContext(['source'=>'queue','job_class'=>'Closure fixture']);$closureError=new \RuntimeException('closure failure');LoadedContextObserverFixture::attachId($closureError,'order',71,'Closure order');$closureResult=$closureReporter->report($closureError);if($closureResult||$closureTransport->sent!==[])throw new \RuntimeException('unverified Closure context bypassed source-pin guard');
    echo json_encode(['status'=>'PASS','pipeline'=>'LaravelErrorReporter->refineContextForFailure->ContextAdapter->ErrorReporter->CompactErrorFormatter->fakeTransport','assertions'=>25,'unknown_unregistered_operation_not_dropped'=>true,'registered_operation_enriched_for_loaded_observer'=>true,'unverified_closure_still_suppressed'=>true,'correlation_persisted_in_local_log'=>true,'typed_object_dedup_identity'=>true,'loaded_context_weakmap_merge'=>true,'benign_validation_suppressed'=>true,'real_transport_calls'=>0,'business_writes'=>0]);
}

