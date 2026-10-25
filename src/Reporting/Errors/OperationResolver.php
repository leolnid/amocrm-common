<?php
declare(strict_types=1);
namespace Leolnid\Common\Reporting\Errors;
final class OperationResolver
{
    public static function resolve(\Throwable $error, string $project, string $projectUrl, array $metadata, array $definition): ?ErrorMessage
    {
        $http=is_string($metadata['http_action']??null)?$metadata['http_action']:'';$job=is_string($metadata['job_class']??null)?$metadata['job_class']:'';if (is_string($http)&&str_ends_with($http,'@__invoke')) $http=substr($http,0,-9);
        $key=match($metadata['source']??null) {
            'queue'=>'job:'.$job,
            'http'=>$http==='Closure' ? (($metadata['http_closure_verified']??false)===true ? 'route:'.($metadata['route_template']??'') : '') : 'http:'.$http,
            'scheduler'=>'command:'.($metadata['scheduled_command']??''),
            'console'=>'command:'.($metadata['console_command']??''),
            default=>'',
        };
        try {
            $catalog=$definition['operations']??[];
            if (!isset($catalog[$key])) return null;
            if (($metadata['source']??null)==='queue' && str_starts_with((string)($metadata['job_class']??''),'Closure ')) {
                $pin=$definition['closure_source_pins'][$metadata['job_class']]??null;
                if (!is_array($pin)||!SourcePins::verify($definition['runtime_root']??'',$pin)) return null;
            }
            $reason=FailureClassifier::classify($error,$definition['operation_services'][$key]??'external');
            $exit=$metadata['command_exit_code']??null;
            if ($reason->code==='processing_error' && is_int($exit) && $exit>0 && $exit<=255 && in_array($metadata['source']??'', ['console','scheduler'],true)) $reason=new ErrorReason('command_failed','internal',null,$exit);
            return new ErrorMessage($project,$projectUrl,ErrorAction::registered($key,$catalog),$reason);
        } catch (\InvalidArgumentException|\TypeError) { return null; }
    }
}
