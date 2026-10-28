<?php
declare(strict_types=1);
namespace Illuminate\Http\Client {
    final class UriFixture { public function __construct(private string $host){} public function getHost(): string{return $this->host;} }
    final class ResponseFixture { public function __construct(private int $status,private string $host){} public function status(): int{return $this->status;} public function effectiveUri(): object{return new UriFixture($this->host);} }
    final class RequestException extends \RuntimeException { public function __construct(public object $response,string $privateBody){parent::__construct($privateBody,$response->status());} }
}
namespace {
    spl_autoload_register(static function($class){$prefix='Leolnid\\Common\\Reporting\\';if(str_starts_with($class,$prefix))require __DIR__.'/../../src/Reporting/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';});
    use Leolnid\Common\Reporting\Errors\FailureClassifier;
    use Leolnid\Common\Reporting\Errors\{ErrorAction,ErrorMessage};
    use Leolnid\Common\Reporting\Capture\LaravelMetadataContext;
    $secret='response-body-secret';
    $error=new \Illuminate\Http\Client\RequestException(new \Illuminate\Http\Client\ResponseFixture(503,'api.partner.example'),$secret);
    $reason=FailureClassifier::classify($error,'moysklad');
    if($reason->code!=='unavailable'||$reason->httpStatus!==503||$reason->upstreamHost!=='api.partner.example'||!str_contains($reason->text(),'HTTP 503')||!str_contains($reason->text(),'api.partner.example')||!str_contains($reason->text(),'недоступен')||str_contains($reason->text(),$secret))throw new \RuntimeException('503 classification leaked detail or lost safe host/status');
    $internalDefault=FailureClassifier::classify($error,'internal');if($internalDefault->code!=='unavailable'||$internalDefault->service!=='external'||$internalDefault->httpStatus!==503)throw new \RuntimeException('known HTTP-client failure lost its external status classification');
    $badHost=new \Illuminate\Http\Client\RequestException(new \Illuminate\Http\Client\ResponseFixture(503,'api.partner.example.evil.test'),$secret);
    if(FailureClassifier::classify($badHost)->upstreamHost!=='api.partner.example.evil.test')throw new \RuntimeException('host must reflect exact URI hostname only');
    $formatted=$reason->text();if(str_contains($formatted,'private')||str_contains($formatted,'?'))throw new \RuntimeException('HTTP body/query leaked');
    $logMetadata=LaravelMetadataContext::withIdentity([],null,new ErrorMessage('fixture',null,ErrorAction::known('module_process'),$reason));if(($logMetadata['http_status']??null)!==503||($logMetadata['upstream_host']??null)!=='api.partner.example'||count(array_intersect(array_keys($logMetadata),['response_body','request_url','query','token']))!==0)throw new \RuntimeException('safe HTTP status/host were not retained in log metadata');
    $unclassified=FailureClassifier::classify(new \RuntimeException('Bearer private-token'));if($unclassified->safeDetail!==null||$unclassified->exceptionClass!=='RuntimeException'||str_contains($unclassified->text(),'private-token'))throw new \RuntimeException('unknown exception text leaked');
    echo json_encode(['status'=>'PASS','assertions'=>6,'requests'=>0,'business_writes'=>0,'safe_http_log_metadata'=>true]);
}
