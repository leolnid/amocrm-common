<?php
declare(strict_types=1);
namespace Leolnid\Common\Reporting\Errors;
/** Converts only already-loaded public scalar context through an application-owned fixed catalog. */
final class ContextAdapter
{
    public static function normalize(string $project, string $projectUrl, ?object $context, array $definition, bool $test = false, ?\Throwable $error = null): ?ErrorMessage
    {
        $data=[];$spec=[];
        try {
            if ($test) return new ErrorMessage($project,$projectUrl,ErrorAction::known('test'),new ErrorReason('internal_error'),[],true);
            if ($context === null) return $error===null?null:self::fallback($project,$projectUrl,$data,$definition,$error,[],[]);
            $data=get_object_vars($context);$kind=$data['kind']??null;
            if (!is_string($kind)) return $error===null?null:self::fallback($project,$projectUrl,$data,$definition,$error,[],[]);
            $defaults=self::defaultKinds($definition['operations']??[]);
            if (!isset($defaults[$kind]) && !isset($definition['kinds'][$kind])) return $error===null?null:self::fallback($project,$projectUrl,$data,$definition,$error,self::fallbackSpecs($definition),[]);
            $spec=array_replace_recursive($defaults[$kind]??[],$definition['kinds'][$kind]??[]);$action=self::action($data,$spec['action']);$reason=self::reason($data,$spec['reason']);$objects=[];
            if ($reason->code === 'processing_error' && $error !== null) $reason=FailureClassifier::classify($error,$definition['default_service']??'external');
            foreach ($spec['objects']??[] as $object) {
                if (isset($object['when']) && ($data[$object['when']['field']]??null) !== $object['when']['equals']) continue;
                if (isset($object['crm_account'])) {
                    $host=$data[$object['crm_account']]??null;if (!is_string($host)) throw new \InvalidArgumentException('Loaded CRM account missing');
                    $objects[]=ErrorObject::crmAccount($host);continue;
                }
                $id=$data[$object['id']]??null;
                if (($object['optional']??false) && ($id === null || $id === 0)) continue;
                $url=null;
                if (isset($object['url'])) {
                    try { $url=self::url($object['url'],$data,$object['params']??[]); }
                    catch (\InvalidArgumentException $failure) { if (!($object['optional_url']??false)) throw $failure; }
                }
                $host=isset($object['display_host']) ? ($data[$object['display_host']]??null) : null;
                $objects[]=$object['type']==='account' ? ErrorObject::accountReference($id,$url,is_string($host)?$host:null) : ErrorObject::reference($object['type'],$id,$url);
            }
            $contextItems=self::contextItems($data,$spec['context']??[],$definition['context_catalog']??[]);
            return new ErrorMessage($project,$projectUrl,$action,$reason,$objects,false,$contextItems);
        } catch (\InvalidArgumentException|\TypeError) { return $error===null?null:self::fallback($project,$projectUrl,$data,$definition,$error,$spec['context']??self::fallbackSpecs($definition),$spec['action']??[]); }
    }
    private static function fallback(string $project,string $projectUrl,array $data,array $definition,\Throwable $error,array $contextSpec,array $actionSpec): ErrorMessage
    {
        $action=ErrorAction::unknown();$operations=$definition['operations']??[];$operation=$data['operation']??null;
        if (isset($actionSpec['known'])) { try { $action=ErrorAction::known($actionSpec['known']); } catch (\InvalidArgumentException) {} }
        if (isset($actionSpec['registered_field'])) $operation=$data[$actionSpec['registered_field']]??$operation;
        if (is_string($operation) && isset($operations[$operation])) $action=ErrorAction::registered($operation,$operations);
        if (isset($actionSpec['field'],$actionSpec['map'])) { $code=$actionSpec['map'][$data[$actionSpec['field']]??'']??null; if(is_string($code)){try{$action=ErrorAction::known($code);}catch(\InvalidArgumentException){}} }
        $reason=FailureClassifier::classify($error,$definition['default_service']??'external');
        try { $items=self::contextItems($data,$contextSpec,$definition['context_catalog']??[]); }
        catch (\InvalidArgumentException|\TypeError) { $items=[]; }
        return new ErrorMessage($project,$projectUrl,$action,$reason,[],false,$items);
    }
    private static function contextItems(array $data,array $specs,array $catalog): array
    {
        $items=[];
        $providedItems=$data['context_items']??[];
        if (is_array($providedItems)&&array_is_list($providedItems)) foreach ($providedItems as $provided) {
            try {
                if (!$provided instanceof ErrorContextItem || ($catalog[$provided->key]??null)!==$provided->label) continue;
                $items[]=$provided;
            } catch (\Throwable) { continue; }
        }
        foreach ($specs as $item) {
            try {
                if (!is_array($item)) continue;
                if (isset($item['when']) && ($data[$item['when']['field']]??null) !== $item['when']['equals']) continue;
                $key=$item['key']??null;$label=is_string($key)?($catalog[$key]??null):null;
                if (!is_string($key) || !is_string($label) || (!is_string($item['id']??null) && !is_string($item['name']??null))) continue;
                $id=isset($item['id']) ? ($data[$item['id']]??null) : null;
                if ($id===0 || $id==='') $id=null;
                $name=isset($item['name']) ? ($data[$item['name']]??null) : null;
                if ($name!==null && !is_string($name)) $name=null;
                if ($id===null && ($name===null || trim($name)==='')) {if ($item['optional']??false) continue;}
                $receivedAt=isset($item['received_at']) ? ($data[$item['received_at']]??null) : null;
                if ($receivedAt!==null && !is_string($receivedAt)) $receivedAt=null;
                if ($key==='request' && $id===null) continue;
                $url=null;
                if (isset($item['url'])) {
                    try { $url=self::url($item['url'],$data,$item['params']??[]); }
                    catch (\InvalidArgumentException) { if (!($item['optional_url']??false)) $url=null; }
                }
                $items[]=ErrorContextItem::loaded($key,$label,$id,$name,$url,$receivedAt);
            } catch (\InvalidArgumentException|\TypeError) { continue; }
        }
        return $items;
    }
    /** Select only mappings whose meaning is identical across all registered operation catalogs. */
    private static function fallbackSpecs(array $definition): array
    {
        $specs=$definition['fallback_context']??[];
        $seen=[];$conflicts=[];
        foreach (($definition['kinds']??[]) as $kind) foreach (($kind['context']??[]) as $item) {
            if (!is_array($item) || !is_string($item['key']??null)) continue;
            $identity=($item['id']??'').'|'.($item['name']??'').'|'.($item['url']??'').'|'.json_encode($item['params']??[]);
            $key=$item['key'].'|'.$identity;
            $fieldIdentity=($item['id']??'').'|'.($item['name']??'');
            if (isset($seen[$fieldIdentity]) && $seen[$fieldIdentity]!==$item['key']) $conflicts[$fieldIdentity]=true;
            $seen[$fieldIdentity]=$item['key'];
            $specs[$key]=$item;
        }
        foreach ($specs as $key=>$item) {
            $fieldIdentity=($item['id']??'').'|'.($item['name']??'');
            if (isset($conflicts[$fieldIdentity])) unset($specs[$key]);
        }
        return array_values($specs);
    }
    private static function defaultKinds(array $catalog): array
    {
        $reasons=self::registeredReasons();
        return [
            'rule_field_mapping'=>['action'=>['known'=>'lead_create'],'reason'=>['code'=>'field_mapping'],'objects'=>[['type'=>'rule','id'=>'entityId']]],
            'photo_unavailable'=>['action'=>['known'=>'photo_upload'],'reason'=>['code'=>'photo_unavailable'],'objects'=>[['type'=>'lead','id'=>'entityId']]],
            'lead_processing_failed_unknown'=>['action'=>['known'=>'lead_process'],'reason'=>['code'=>'processing_error','optional_field'=>'reason','shared_map'=>'operation'],'objects'=>[['type'=>'lead','id'=>'entityId']]],
            'project_operation'=>['action'=>['registered_field'=>'operation','catalog'=>$catalog],'reason'=>['field'=>'reason','map'=>$reasons],'objects'=>[]],
        ];
    }
    private static function registeredReasons(): array
    {
        $reasons=[];
        foreach (['crm_400'=>['request_rejected','amocrm',400],'crm_4xx'=>['request_rejected','amocrm'],'crm_auth'=>['access_rejected','amocrm'],'crm_rate'=>['rate_limited','amocrm'],'crm_unavailable'=>['unavailable','amocrm'],'remote_400'=>['request_rejected','external',400],'remote_4xx'=>['request_rejected','external'],'remote_auth'=>['access_rejected','external'],'remote_rate'=>['rate_limited','external'],'remote_unavailable'=>['unavailable','external'],'type_error'=>['data_type','internal'],'value_error'=>['value_error','internal'],'database_error'=>['database_error','internal'],'internal_error'=>['processing_error','internal'],'mail_connection_failed'=>['mail_connection_failed','mail'],'mail_configuration_error'=>['mail_configuration_error','mail']] as $key=>$row) $reasons[$key]=['code'=>$row[0],'service'=>$row[1],'status'=>$row[2]??null];
        return $reasons;
    }
    private static function action(array $data, array $spec): ErrorAction
    {
        if (isset($spec['known'])) return ErrorAction::known($spec['known']);
        if (isset($spec['registered_field'])) {
            $code=$data[$spec['registered_field']]??null;
            if (!is_string($code)) throw new \InvalidArgumentException('Registered action missing');
            return ErrorAction::registered($code,$spec['catalog']);
        }
        $raw=$data[$spec['field']]??null;
        if (!is_string($raw) || !isset($spec['map'][$raw])) throw new \InvalidArgumentException('Known action missing');
        return ErrorAction::known($spec['map'][$raw]);
    }
    private static function reason(array $data, array $spec): ErrorReason
    {
         $map=($spec['shared_map']??null)==='operation' ? self::registeredReasons() : ($spec['map']??[]);
        if (isset($spec['field'])) {
            $raw=$data[$spec['field']]??null;
            if (!is_string($raw) || !isset($map[$raw])) throw new \InvalidArgumentException('Known reason missing');
            $selected=$map[$raw];
        } elseif (isset($spec['optional_field']) && ($data[$spec['optional_field']]??null)!==null) {
            $raw=$data[$spec['optional_field']];
            if (!is_string($raw) || !isset($map[$raw])) throw new \InvalidArgumentException('Optional known reason invalid');
            $selected=$map[$raw];
        } else $selected=$spec;
        $service=$selected['service']??'internal';
        if (isset($spec['service_field'])) {
            $raw=$data[$spec['service_field']]??null;
            if (!is_string($raw) || !isset($spec['service_map'][$raw])) throw new \InvalidArgumentException('Known service missing');
            $service=$spec['service_map'][$raw];
        }
        $status=$selected['status']??null;
        if (isset($spec['status_field'])) {
            $raw=$data[$spec['status_field']]??null;
            if ($raw !== null && (!is_int($raw) || $raw<400 || $raw>599)) throw new \InvalidArgumentException('Known HTTP error status invalid');
            $status=$raw;
        }
        return new ErrorReason($selected['code'],$service,$status);
    }
    private static function url(string $template, array $data, array $params): string
    {
        $url=preg_replace_callback('/\{([A-Za-z][A-Za-z0-9_]*)\}/',static function(array $match)use($data,$params):string {
            $field=$match[1];$value=$data[$field]??null;$type=$params[$field]??null;
            if ($type === 'crm_host') {
                if (!is_string($value) || !ErrorObject::crmHost($value)) throw new \InvalidArgumentException('Loaded CRM host invalid');
                return $value;
            }
            if ($type === 'uuid') {
                if (!is_string($value) || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/iD',$value)) throw new \InvalidArgumentException('Loaded account UUID invalid');
                return strtolower($value);
            }
            if ($type !== 'id') throw new \InvalidArgumentException('Unregistered object link parameter');
            return ErrorObject::id($value);
        },$template);
        if (!is_string($url)) throw new \InvalidArgumentException('Object link unavailable');
        ErrorMessage::url($url);return $url;
    }
}
