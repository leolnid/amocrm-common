<?php
declare(strict_types=1);
namespace Leolnid\Common\Reporting\Errors;
final class FailureClassifier
{
    public static function classify(\Throwable $error, string $service = 'external'): ErrorReason
    {
        $seen=new \WeakMap;$fallback=null;
        for ($depth=0;$depth<5 && !isset($seen[$error]);$depth++) {
            $seen[$error]=true;$class=$error::class;$crm=str_starts_with($class,'AmoCRM\\');$current=$crm?'amocrm':$service;
            if (is_a($error,'PhpImap\\Exceptions\\ConnectionException')) return new ErrorReason('mail_connection_failed','mail');
            if (is_a($error,'PhpImap\\Exceptions\\InvalidParameterException')) return new ErrorReason('mail_configuration_error','mail');
            if ($error instanceof \TypeError) return new ErrorReason('data_type');
            if ($error instanceof \ValueError) return new ErrorReason('value_error');
            if ($error instanceof \PDOException || is_a($error,'Illuminate\\Database\\QueryException')) return new ErrorReason('database_error');
            try {
                if (is_a($error,'Illuminate\\Http\\Client\\ConnectionException')) $fallback=new ErrorReason('connection_failed',$current);
                if (is_a($error,'GuzzleHttp\\Exception\\ConnectException')) {
                    $context=method_exists($error,'getHandlerContext')?$error->getHandlerContext():[];
                    if (is_array($context) && ($context['errno']??null)===28) return new ErrorReason('timeout',$current);
                    $fallback=new ErrorReason('connection_failed',$current);
                }
                if (is_a($error,'AmoCRM\\Exceptions\\AmoCRMApiConnectExceptionException')) $fallback=new ErrorReason('connection_failed','amocrm');
                if (is_a($error,'AmoCRM\\Exceptions\\AmoCRMoAuthApiException') || is_a($error,'AmoCRM\\Exceptions\\AmoCRMMissedTokenException')) return new ErrorReason('access_rejected','amocrm');
                if (is_a($error,'AmoCRM\\Exceptions\\AmoCRMApiNoContentException')) return new ErrorReason('object_not_found','amocrm');
                $status=$crm?self::status(method_exists($error,'getErrorCode')?$error->getErrorCode():$error->getCode()):null;
                if (str_starts_with($class,'GuzzleHttp\\') && method_exists($error,'getResponse')) {
                    $response=$error->getResponse();
                    if (is_object($response) && method_exists($response,'getStatusCode')) $status=self::status($response->getStatusCode());
                }
                if ($status !== null) return match (true) {
                    $status===401 || $status===403=>new ErrorReason('access_rejected',$current,$status),
                    $status===429=>new ErrorReason('rate_limited',$current,$status),
                    $status===404=>new ErrorReason('object_not_found',$current,$status),
                    $status<500=>new ErrorReason('request_rejected',$current,$status),
                    default=>new ErrorReason('unavailable',$current,$status),
                };
            } catch (\Throwable) { /* Faulty metadata access never changes the original business exception. */ }
            $previous=$error->getPrevious();if ($previous===null) break;$error=$previous;
        }
        return $fallback??new ErrorReason('processing_error');
    }
    private static function status(mixed $value): ?int
    {
        if (is_string($value) && preg_match('/^[0-9]{3}$/D',$value)) $value=(int)$value;
        return is_int($value) && $value>=400 && $value<=599 ? $value : null;
    }
}
