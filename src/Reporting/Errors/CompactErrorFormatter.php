<?php
declare(strict_types=1);
namespace Leolnid\Common\Reporting\Errors;
final class CompactErrorFormatter
{
    public static function render(ErrorMessage $message): string
    {
        $title = '⚠️ <b><a href="'.self::escape($message->projectUrl).'">'.self::escape($message->project).'</a></b>';
        $objects = [];
        foreach ($message->objects as $object) {
            $label = self::escape($object->label);
            $objects[] = $object->url === null ? $label : '<a href="'.self::escape($object->url).'">'.$label.'</a>';
        }
        $failure = self::escape(rtrim($message->failure->text($message->reason), '. ').'.');
        $body = ($objects === [] ? '' : implode(' · ', $objects).': ').$failure;
        $reason = 'Причина: '.self::escape(rtrim($message->reason->text(), '. ').'.');
        return $title."\n\n".$body."\n".$reason.($message->test ? "\n\n<i>Пример, не новый сбой</i>" : '');
    }
    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
