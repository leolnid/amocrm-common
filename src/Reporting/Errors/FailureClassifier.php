<?php
declare(strict_types=1);
namespace Leolnid\Common\Reporting\Errors;
final class FailureClassifier
{
    public static function hasHttpResponse(\Throwable $error): bool
    {
        $seen=new \WeakMap;
        for($depth=0;$depth<5&&!isset($seen[$error]);$depth++){
            $seen[$error]=true;
            if(is_a($error,'Illuminate\\Http\\Client\\RequestException')||is_a($error,'GuzzleHttp\\Exception\\RequestException'))return true;
            $error=$error->getPrevious();if($error===null)break;
        }
        return false;
    }

    public static function classify(\Throwable $error, string $service = 'external'): ErrorReason
    {
        $original=$error;$seen=new \WeakMap;$fallback=null;
        for ($depth=0;$depth<5 && !isset($seen[$error]);$depth++) {
            $seen[$error]=true;$class=$error::class;$crm=str_starts_with($class,'AmoCRM\\');$httpClient=is_a($error,'Illuminate\\Http\\Client\\RequestException')||is_a($error,'Illuminate\\Http\\Client\\ConnectionException')||is_a($error,'GuzzleHttp\\Exception\\RequestException')||is_a($error,'GuzzleHttp\\Exception\\ConnectException');$current=$crm?'amocrm':(($service==='internal'&&$httpClient)?'external':$service);
            if (is_a($error,'AmoCRM\\Exceptions\\InvalidArgumentException') && str_starts_with($error->getMessage(),'Error parsing given access token.')) return new ErrorReason('credential_format','amocrm');
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
                if (is_a($error,'Illuminate\\Http\\Client\\RequestException')) {
                    $response=$error->response??null;
                    if (is_object($response)&&method_exists($response,'status')) $status=self::status($response->status());
                }
                if ($status !== null) {
                    $host=self::upstreamHost($error);
                    return match (true) {
                        $status===401 || $status===403=>new ErrorReason('access_rejected',$current,$status,null,null,$host),
                        $status===429=>new ErrorReason('rate_limited',$current,$status,null,null,$host),
                        $status===404=>new ErrorReason('object_not_found',$current,$status,null,null,$host),
                        $status<500=>new ErrorReason('request_rejected',$current,$status,null,null,$host),
                        default=>new ErrorReason('unavailable',$current,$status,null,null,$host),
                    };
                }
            } catch (\Throwable) { /* Faulty metadata access never changes the original business exception. */ }
            $previous=$error->getPrevious();if ($previous===null) break;$error=$previous;
        }
        return $fallback??new ErrorReason('processing_error','internal',null,null,null,null,self::safeExceptionClass($original::class));
    }
    private static function upstreamHost(\Throwable $error): ?string
    {
        try {
            $uri=null;
            if (is_a($error,'Illuminate\\Http\\Client\\RequestException')) {
                $response=$error->response??null;
                if (is_object($response)&&method_exists($response,'effectiveUri')) $uri=$response->effectiveUri();
            } elseif (is_a($error,'GuzzleHttp\\Exception\\RequestException')&&method_exists($error,'getRequest')) {
                $request=$error->getRequest();
                if (is_object($request)&&method_exists($request,'getUri')) $uri=$request->getUri();
            }
            if (!is_object($uri)||!method_exists($uri,'getHost')) return null;
            $host=$uri->getHost();
            return is_string($host)&&ErrorReason::isUpstreamHost($host)?$host:null;
        } catch (\Throwable) { return null; }
    }
    private static function safeExceptionClass(string $class): string
    {
        return preg_match('/^[A-Za-z_][A-Za-z0-9_\\\\]{0,119}$/D',$class)===1?$class:'Throwable';
    }
    /** Return only a short human-readable fragment after conservative secret/PII/path removal. */
    public static function safeDetail(string $message): ?string
    {
        if (!preg_match('//u',$message)) return null;
        $detail=strip_tags($message);
        $credentialProbe=rawurldecode($detail);
        if (preg_match('/\b(?:bearer|basic|token|secret|password|passwd|cookie|authorization|api[_\s-]?key|client[_\s-]?secret|access[_\s-]?token|refresh[_\s-]?token)\b|токен|секрет|парол|куки|авторизац/iu',$credentialProbe)) return null;
        $detail=preg_replace('/\b(?:bearer|basic)\s+[A-Za-z0-9._~+\/-]+=*/i','[скрыто]',$detail)??'';
        $detail=preg_replace('~[\"\x27]?(?:authorization|password|passwd|secret|api[_ -]?key|client[_ -]?secret|access[_ -]?token|refresh[_ -]?token|cookie)[\"\x27]?\s*[:=]\s*(?:\"[^\"]{0,300}\"|\x27[^\x27]{0,300}\x27|[^\s,;}]+)~i','[скрыто]',$detail)??'';
        $detail=preg_replace('/\bCookie\s*:\s*[^,\r\n]+/i','[cookie скрыта]',$detail)??'';
        $detail=preg_replace('/\beyJ[A-Za-z0-9_-]{8,}\.[A-Za-z0-9_-]{8,}\.[A-Za-z0-9_-]{8,}\b/','[данные скрыты]',$detail)??'';
        $detail=preg_replace('/(?<!\w)\+?\d[\d ().-]{8,}\d(?!\w)/u','[номер скрыт]',$detail)??'';
        $detail=preg_replace('~\b(?:response|http)\s+(?:body|payload)\s*[:=].*$~isu','[содержимое HTTP скрыто]',$detail)??'';
        $detail=preg_replace('~\b(?:select|insert\s+into|update\s+\w+\s+set|delete\s+from)\b.*$~isu','[SQL-запрос скрыт]',$detail)??'';
        $detail=preg_replace('~https?://[^\s<>]+~i','[ссылка]',$detail)??'';
        $detail=preg_replace('/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i','[адрес скрыт]',$detail)??'';
        $detail=preg_replace('~(?:[A-Za-z]:\\\\|/(?:var|home|etc|tmp|srv|app)/)[^\s,;]+~','[путь скрыт]',$detail)??'';
        $detail=preg_replace('/\b[A-Za-z0-9_+\/-]{32,}={0,2}\b/','[данные скрыты]',$detail)??'';
        $detail=preg_replace('/\{[^{}]{0,500}\}/u','[данные скрыты]',$detail)??'';
        $detail=preg_replace('/[\x00-\x1f\x7f]+/u',' ',$detail)??'';
        $detail=trim(preg_replace('/\s+/u',' ',$detail)??'');
        if ($detail==='' || preg_match('/^(?:\[скрыто\]|\[ссылка\]|\[адрес скрыт\]|\[путь скрыт\]|\[данные скрыты\])(?:\s|$)/u',$detail)) return null;
        if (function_exists('mb_substr')) $detail=mb_substr($detail,0,160,'UTF-8');
        else $detail=substr($detail,0,160);
        return preg_match('//u',$detail)===1 && trim($detail)!=='' ? trim($detail) : null;
    }
    private static function status(mixed $value): ?int
    {
        if (is_string($value) && preg_match('/^[0-9]{3}$/D',$value)) $value=(int)$value;
        return is_int($value) && $value>=400 && $value<=599 ? $value : null;
    }
}
