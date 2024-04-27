<?php
namespace Leolnid\Common\Helpers;

class MaskModsHelper
{
    static function getForDate(): array
    {
        return self::format([
            'df(d\.m\.Y)' => 'Формат "ДД.ММ.ГГГГ"',
            'df(d F Y)' => 'Формат "ДД месяц ГГГГ"',
            'df(Y)' => 'Формат "ГГГГ"',
            'df(m)' => 'Формат "ММ"',
            'df(d)' => 'Формат "ДД"',
            'df(F)' => 'Формат "месяц"',
            'spell' => 'Прописью',
            //'df(F):case(g)' => 'Формат "месяц" в род. падеже',
        ]);
    }

    static private function format(array $arr): array
    {
        $result = [];
        foreach ($arr as $mod => $name) {
            $result[] = ['name' => $name, 'value' => $mod];
        }
        return $result;
    }

    static function getForNumeric(): array
    {
        return self::format([
            'format(0)' => 'Разбить по разрядам',
            'spell_num' => 'Число прописью',
            'spell_price' => 'Сумма прописью (руб)',
        ]);
    }

    static function getForString(): array
    {
        return self::format([
            'translit' => 'Транслитом',
            'lower' =>  'Строчными буквами',
            'upper' =>  'Прописными буквами',
            'ucfirst' =>  'С прописной буквы',
            //'fio(N)' => 'ФИО Имя',
            //'fio(F)' => 'ФИО Фамилия',
            //'fio(P)' => 'ФИО Отчество',
            'fio(F N P):abbr' => 'Фамилия И.О.',
            'fio(F N P):case(g)' => 'ФИО в род. падеже',
            'case(g)' => 'В родительном падеже'
        ]);
    }

    static function getForManager(): array
    {
        return self::format([
            'name' => 'Имя',
            'id' => 'ID',
            'email' => 'Email'
        ]);
    }
}
