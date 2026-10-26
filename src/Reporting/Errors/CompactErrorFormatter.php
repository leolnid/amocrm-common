<?php
declare(strict_types=1);
namespace Leolnid\Common\Reporting\Errors;
final class CompactErrorFormatter
{
    public static function render(ErrorMessage $message): string
    {
        $title = '⚠️ <b><a href="'.self::escape($message->projectUrl).'">'.self::escape($message->project).'</a></b>';
        $failureText=self::bounded($message->failure->text($message->reason),115);
        $failure = '<b>Ошибка:</b> '.self::escape(rtrim($failureText, '. ').'.');
        if ($message->repeatCount>0) $failure.=' <i>(Повторов: '.$message->repeatCount.')</i>';
        $reason = '<b>Причина:</b> '.self::escape(rtrim(self::bounded($message->reason->text(),105), '. ').'.');
        $context=[];$seen=[];
        foreach ($message->context as $item) {
            $identity=self::logicalIdentity($item->key,$item->id);
            if ($identity!==null && isset($seen[$identity])) continue;
            if ($identity!==null) $seen[$identity]=true;
            $context[]=self::contextLine($item->label,$item->display(),$item->url);
        }
        $legacyLabels=['account'=>'Аккаунт','module'=>'Модуль','request'=>'Запрос','lead'=>'Сделка','deal'=>'Сделка','contact'=>'Контакт','order'=>'Заказ','rule'=>'Правило'];
        foreach ($message->objects as $object) {
            $id=preg_match('/^#([0-9]+)$/D',$object->value,$match)?$match[1]:null;
            $type=in_array($object->type,['lead','deal'],true)?'deal':$object->type;
            $identity=self::logicalIdentity($type,$id);
            if ($identity!==null && isset($seen[$identity])) continue;
            if ($identity!==null) $seen[$identity]=true;
            $label=$object->type==='account'&&str_contains($object->value,'-')?'Подключение':($legacyLabels[$object->type]??'Объект');
            $context[]=self::contextLine($label,$object->value,$object->url);
        }
        $body=$title."\n\n".$failure."\n".$reason;
        if (($message->reason->code==='processing_error'||$message->failure->code==='unknown_operation') && $message->eventCode!==null) $body.="\n<b>Код события:</b> <code>".self::escape($message->eventCode)."</code>";
        if ($context!==[]) {
            $body.="\n\nКонтекст:";
            $omitted=0;
            foreach ($context as $line) {
                $candidate=$body."\n".$line;
                if (self::length($candidate)>3800) {$omitted++;continue;}
                $body=$candidate;
            }
            if ($omitted>0) {
                $event=$message->eventCode===null?'':'; код события: '.$message->eventCode;
                $body.="\n".self::escape('Контекст сокращён: '.$omitted.' полей не помещено'.$event.'.');
            }
        }
        if ($message->test) $body.="\n\n<i>Пример, не новый сбой</i>";
        return $body;
    }
    private static function bounded(string $value,int $limit): string
    {
        if (self::length($value)<=$limit) return $value;
        $slice=function_exists('mb_substr')?mb_substr($value,0,$limit-1,'UTF-8'):substr($value,0,$limit-1);
        return rtrim($slice).'…';
    }
    private static function length(string $value): int
    {
        if (function_exists('mb_strlen')) return mb_strlen($value,'UTF-8');
        $matched=preg_match_all('/./us',$value,$unused);
        return $matched===false?strlen($value):$matched;
    }
    private static function logicalIdentity(string $key,?string $id): ?string
    {
        if ($id===null) return null;
        $key=in_array($key,['lead','deal'],true)?'deal':$key;
        return $key.':'.$id;
    }
    private static function contextLine(string $label,string $value,?string $url): string
    {
        $label='<b>'.self::escape($label).'</b>: ';$value=self::escape($value);
        return $label.($url===null?$value:'<a href="'.self::escape($url).'">'.$value.'</a>');
    }
    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
