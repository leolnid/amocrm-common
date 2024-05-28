<?php

namespace Leolnid\Common\Helpers;

use Illuminate\Support\Str;
use NCL\NCL;
use NCL\NCLNameCaseRu;

class StringHelper
{
    public static function translit(string $input): string
    {
        $gost = [
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd',
            'е' => 'e', 'ё' => 'yo', 'ж' => 'j', 'з' => 'z', 'и' => 'i',
            'й' => 'i', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n',
            'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't',
            'у' => 'y', 'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch',
            'ш' => 'sh', 'щ' => 'sh', 'ы' => 'i', 'э' => 'e', 'ю' => 'u',
            'я' => 'ya',
            'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D',
            'Е' => 'E', 'Ё' => 'Yo', 'Ж' => 'J', 'З' => 'Z', 'И' => 'I',
            'Й' => 'I', 'К' => 'K', 'Л' => 'L', 'М' => 'M', 'Н' => 'N',
            'О' => 'O', 'П' => 'P', 'Р' => 'R', 'С' => 'S', 'Т' => 'T',
            'У' => 'Y', 'Ф' => 'F', 'Х' => 'H', 'Ц' => 'C', 'Ч' => 'Ch',
            'Ш' => 'Sh', 'Щ' => 'Sh', 'Ы' => 'I', 'Э' => 'E', 'Ю' => 'U',
            'Я' => 'Ya',
            'ь' => '', 'Ь' => '', 'ъ' => '', 'Ъ' => '',
            'ї' => 'j', 'і' => 'i', 'ґ' => 'g', 'є' => 'ye',
            'Ї' => 'J', 'І' => 'I', 'Ґ' => 'G', 'Є' => 'YE',
        ];

        return strtr($input, $gost);
    }

    public static function fioToGenitive(string $input): string
    {
        $nc = new NCLNameCaseRu();
        $nc->q($input);

        return $nc->getFormatted(NCL::$RODITLN);
    }

    public static function fioToAbbr(string $input): string
    {
        $nc = new NCLNameCaseRu();
        $nc->q($input);

        return $nc->getFormatted(NCL::$IMENITLN, 'S').' '
            .Str::limit($nc->getFormatted(NCL::$IMENITLN, 'N'), 1, null).'.'
            .Str::limit($nc->getFormatted(NCL::$IMENITLN, 'F'), 1, null).'.';
    }

    public static function toGenitive(string $value)
    {
        $nc = new NCLNameCaseRu();
        $nc->q($value);

        return $nc->getFormatted(NCL::$RODITLN);
    }
}
