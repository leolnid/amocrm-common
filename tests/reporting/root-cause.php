<?php
declare(strict_types=1);
namespace PhpImap\Exceptions { class ConnectionException extends \RuntimeException {} }
namespace GuzzleHttp\Exception {
    class ConnectException extends \RuntimeException {
        public function __construct(string $message,private string $host) {parent::__construct($message);}
        public function getHandlerContext():array {return ['errno'=>28];}
        public function getRequest():object {return new class($this->host) {public function __construct(private string $host){} public function getUri():object {return new class($this->host) {public function __construct(private string $host){} public function getHost():string{return $this->host;}};}};}
    }
}
namespace Illuminate\Console {
    class Command { public const SUCCESS=0; public array $output=[]; public function line(string $line):void {$this->output[]=$line;} }
}
namespace Illuminate\Support\Facades {
    final class Cache { private static array $keys=[]; public static function add(string $key,mixed $value,int $ttl):bool {if(isset(self::$keys[$key]))return false;self::$keys[$key]=true;return true;} }
    final class Log { public static array $rows=[]; public static function channel(string $name):object {return new class {public function warning(string $message,array $context):void {Log::$rows[]=[$message,$context];}};} }
}
namespace {
    spl_autoload_register(static function($class){$prefix='Leolnid\\Common\\Reporting\\';if(str_starts_with($class,$prefix))require __DIR__.'/../../src/Reporting/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';});
    function config(string $key,mixed $default=null):mixed {return $default;}
    function app():object {return new class {public function bound(string $name):bool{return false;}};}
    use Leolnid\Common\Reporting\Errors\{FailureClassifier,ErrorReason};
    use Leolnid\Common\Reporting\Capture\{LaravelMetadataContext,LaravelErrorOutbox,LaravelDeliverApplicationErrors};
    function check(bool $condition,string $message):void {if(!$condition)throw new RuntimeException($message);}
    $root=new RuntimeException('remote connection timed out',27);
    $wrapper=new RuntimeException('password=fixture-secret',0,$root);
    $reason=FailureClassifier::classify($wrapper);
    check($reason->safeDetail==='remote connection timed out','Root cause detail lost');
    check(!str_contains($reason->text(),'поддержк'),'Support handoff remains');
    $chain=FailureClassifier::diagnosticChain($wrapper);
    check(count($chain)===2&&$chain[1]['exception_code']===27&&$chain[0]['safe_message']===null,'Causal chain or secret suppression lost');
    check(!str_contains(json_encode($chain),'fixture-secret'),'Secret leaked in diagnostics');
    foreach(['password=fixture-secret','Cookie: session=fixture-secret','Bearer fixture-secret','https://user:fixture-secret@example.test/path','https://example.test/?access%5ftoken=fixture-secret',"\xff\xfe"] as $unsafe) {
        $safe=FailureClassifier::safeDetail($unsafe);
        check($safe===null||!str_contains($safe,'fixture-secret'),'Malicious diagnostic payload leaked');
    }
    $unicode=FailureClassifier::safeDetail(str_repeat('Ошибка соединения ',30));
    check(is_string($unicode)&&preg_match('//u',$unicode)===1,'Unicode detail was split');
    $imap=new TypeError('PhpImap\\Exceptions\\ConnectionException::__construct(): Argument #1 ($errors) must be of type array, false given, called in /private/vendor/php-imap/php-imap/src/PhpImap/Imap.php on line 715');
    check(FailureClassifier::classify($imap)->code==='mail_diagnostics_missing','PhpImap connection masked by TypeError');
    check(FailureClassifier::classify(new TypeError('array, false given'))->code==='data_type','Generic TypeError misclassified as mail');
    check(FailureClassifier::classify(new \PhpImap\Exceptions\ConnectionException('["Connection refused","No route to host"]'))->code==='mail_connection_refused_route','Refused and no-route cause lost');
    check(FailureClassifier::classify(new \PhpImap\Exceptions\ConnectionException('TLS/SSL failure: SSL negotiation failed'))->code==='mail_tls_failed','TLS cause lost');
    check(FailureClassifier::classify(new \GuzzleHttp\Exception\ConnectException('Resolving timed out after 5000 milliseconds','fixture.amocrm.ru'))->code==='amo_dns_failed','amoCRM DNS diagnosis lost');
    check(FailureClassifier::classify(new \GuzzleHttp\Exception\ConnectException('Resolving timed out after 5000 milliseconds','amocrm.ru.evil.test'))->code==='timeout','Non-amoCRM DNS promoted');
    $scheduled=new RuntimeException("Scheduled command ['/usr/bin/php8.5' 'artisan' app-errors:deliver] failed with exit code [1].");
    $metadata=LaravelMetadataContext::collect($scheduled,[],[],null,false);
    check($metadata['source']==='scheduler'&&$metadata['scheduled_command']==='app-errors:deliver'&&$metadata['command_exit_code']===1,'Actual Laravel wrapper not recovered');
    $outbox=new class extends LaravelErrorOutbox {public function __construct(){} public function drain():array {return ['sent'=>0,'pending'=>0,'expired_requires_review'=>4,'corrupt_requires_review'=>0,'interrupted_requires_review'=>0];}};
    $command=new class extends LaravelDeliverApplicationErrors {public function handle(LaravelErrorOutbox $outbox):int{return $this->deliverOutbox($outbox);}};
    check($command->handle($outbox)===0&&$command->handle($outbox)===0,'Historical exhausted messages create scheduler failure');
    check(count(\Illuminate\Support\Facades\Log::$rows)===1,'Review warning repeatedly logged');
    check(\Illuminate\Support\Facades\Log::$rows[0][1]['review_counts']['expired_requires_review']===4,'Actionable expired count absent');
    $broken=new class extends LaravelErrorOutbox {public function __construct(){} public function drain():array {throw new RuntimeException('Outbox lock unavailable');}};
    $propagated=false;try {$command->handle($broken);} catch(RuntimeException $failure) {$propagated=$failure->getMessage()==='Outbox lock unavailable';}
    check($propagated,'Genuine drain failure converted to success');
    foreach(['ambiguous_contact','migration_hold','client_not_found','required_fields_missing','document_xml_invalid','mail_diagnostics_missing','amo_dns_failed'] as $code) {
        $message=\Leolnid\Common\Reporting\Errors\ContextAdapter::normalize('Fixture',null,(object)['kind'=>'project_operation','operation'=>'process','reason'=>$code],['operations'=>['process'=>'Обработка']]);
        check($message?->reason->code===$code,'Typed project reason not registered: '.$code);
    }
    echo "PASS root cause, sanitized chain, PhpImap regression, actual scheduler wrapper, exhausted outbox without recursive failure; network=0\n";
}
