<?php
declare(strict_types=1);
namespace Leolnid\Common\Reporting\Capture;
use Leolnid\Common\Reporting\Errors\ErrorContextItem;
/** Shared, per-Throwable storage for already-loaded scalar entity context. */
abstract class AbstractLoadedContextObserver
{
    protected const LABELS=[];
    private static ?\WeakMap $attached=null;

    public static function attachEntity(\Throwable $error,string $key,object $entity,?string $url=null): void
    {
        $id=self::loadedValue($entity,['id','getId','getKey']);$name=self::loadedValue($entity,['name','title','getName','getTitle']);
        self::attachId($error,$key,$id,is_string($name)?$name:null,$url);
    }
    public static function attachEntities(\Throwable $error,string $key,iterable $entities): void
    {
        try {foreach($entities as $entity) if(is_object($entity)) self::attachEntity($error,$key,$entity);}catch(\Throwable){/* Broken lazy iterables never affect the business failure path. */}
    }
    public static function attachId(\Throwable $error,string $key,mixed $id,?string $name=null,?string $url=null): void
    {
        $label=static::LABELS[$key]??null;if(!is_string($label))return;
        try {$item=ErrorContextItem::loaded($key,$label,$id,$name,$url);}catch(\Throwable){return;}
        self::$attached??=new \WeakMap;$byClass=self::$attached[$error]??[];$items=$byClass[static::class]??[];
        foreach($items as$existing)if($existing->key===$item->key&&$existing->id===$item->id&&$item->id!==null)return;
        $items[]=$item;$byClass[static::class]=$items;self::$attached[$error]=$byClass;
    }
    public static function merge(?object $context,\Throwable $error): ?object
    {
        $items=self::$attached[$error][static::class]??[];if($items===[])return $context;
        $data=$context===null?[]:get_object_vars($context);$existing=$data['context_items']??[];if(!is_array($existing)||!array_is_list($existing))$existing=[];
        $merged=[];$seen=[];
        foreach(array_merge($existing,$items)as$item){if(!$item instanceof ErrorContextItem)continue;$identity=$item->key.'|'.($item->id??'');if($item->id!==null&&isset($seen[$identity]))continue;if($item->id!==null)$seen[$identity]=true;$merged[]=$item;}
        $data['kind']=is_string($data['kind']??null)?$data['kind']:'unmapped_loaded_context';$data['context_items']=$merged;
        return (object)$data;
    }
    private static function loadedValue(object $entity,array $names): mixed
    {
        $raw=[];
        $eloquent=is_a($entity,'Illuminate\\Database\\Eloquent\\Model');
        if($eloquent&&method_exists($entity,'getAttributes')){try{$candidate=$entity->getAttributes();if(is_array($candidate))$raw=$candidate;}catch(\Throwable){}}
        foreach($names as$name){if(array_key_exists($name,$raw)&&is_scalar($raw[$name]))return $raw[$name];if(array_key_exists($name,get_object_vars($entity))&&is_scalar(get_object_vars($entity)[$name]))return get_object_vars($entity)[$name];}
        foreach($names as$name){if(!str_starts_with($name,'get')||($name==='getKey'&&!$eloquent)||!method_exists($entity,$name))continue;try{$method=new \ReflectionMethod($entity,$name);if(!$method->isPublic()||$method->getNumberOfRequiredParameters()!==0)continue;$value=$method->invoke($entity);if(is_scalar($value))return $value;}catch(\Throwable){}}
        return null;
    }
}
