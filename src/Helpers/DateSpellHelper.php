<?php
namespace Leolnid\Common\Helpers;

use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class DateSpellHelper
{
    static function format(Carbon $date): string
    {
        return Str::ucfirst(self::dayToString($date->format('d'))
            .self::monthToString($date->format('m'))
            .self::yearToString($date->format('Y')).'года');
    }

    private static function dayToString($day): string
    {
        $ones = array(
            '',
            'первое ',
            'второе ',
            'третье ',
            'четвёртое  ',
            'пятое ',
            'шестое ',
            'седьмое ',
            'восьмое ',
            'девятое '
        );
        $tens0 = array('', '', 'двадцать ', 'тридцать ');
        $tens = array('', 'десятое ', 'двадцатое ', 'тридцатое ');
        $teens = array(
            '',
            'одиннадцатое ',
            'двенадцатое ',
            'тринадцатое ',
            'четырнадцатое ',
            'пятнадцатое ',
            'шестнадцатое ',
            'семнадцатое ',
            'восемнадцатое ',
            'девятнадцатое '
        );

        switch ($day) {
            case $day < 10:
                return $ones[$day];
            case $day > 10 and $day < 20:
                return $teens[substr($day, 1, 1)];
            case $day == 10 or $day == 20 or $day == 30:
                return $tens[substr($day, 0, 1)];
            default:
                return $tens0[substr($day, 0, 1)].$ones[substr($day, 1, 1)];
        }
    }

    private static function monthToString(int $mon): string
    {
        $arrMonth = array(
            '',
            "января",
            "февраля",
            "марта",
            "апреля",
            "мая",
            "июня ",
            "июля",
            "августа",
            "сентября",
            "октября",
            "ноября",
            "декабря"
        );
        return $arrMonth[$mon].' ';
    }

    private static function yearToString(int $year): string
    {
        $arrTeens = [];
        $arrTens = array(
            '',
            '',
            'двадцать ',
            'тридцать ',
            'сорок ',
            'пятьдесят ',
            'шестьдесят ',
            'семьдесят ',
            'восемьдесят ',
            'девяносто '
        );
        $arrTens0 = [];
        $arrFour = array(
            '',
            'первого ',
            'второго ',
            'третьего ',
            'четвертого ',
            'пятого ',
            'шестого ',
            'седьмого ',
            'восьмого ',
            'девятого '
        );

        if (substr($year, 2, 1) == 1) {
            $arrTeens = array(
                '',
                'одиннадцатого ',
                'двенадцатого ',
                'тринадцатого ',
                'четырнадцатого ',
                'пятнадцатого ',
                'шестнадцатого ',
                'семнадцатого ',
                'восемнадцатого ',
                'девятнадцатого '
            );
            $arrFour = [];
        }

        if (substr($year, 3, 1) == 0) {
            $arrTens0 = array(
                '',
                'десятого ',
                'двадцатого ',
                'тридцатого ',
                'сорокового ',
                'пятидесятого ',
                'шестидесятого ',
                'семидесятого ',
                'восьмидесятого ',
                'девяностого '
            );
            $arrTens = [];
            $arrFour = [];
        }

        switch ($year) {
            case ($year > 2000 and $year < 2038):
                $strOne = 'две тысячи ';
                break;
            case ($year < 2000 and $year > 1901):
                $strOne = 'одна тысяча девятьсот ';
                break;
            case ($year == 2000):
                $strOne = 'двухтысячного ';
                break;
            default:
                $strOne = '';
                break;
        }

        return $strOne.
            Arr::get($arrTeens, substr($year, 3, 1)).
            Arr::get($arrTens, substr($year, 2, 1)).
            Arr::get($arrTens0, substr($year, 2, 1)).
            Arr::get($arrFour, substr($year, 3, 1));
    }
}
