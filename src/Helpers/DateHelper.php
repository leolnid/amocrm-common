<?php

namespace Leolnid\Common\Helpers;

use Carbon\Carbon;
use Illuminate\Support\Str;

class DateHelper
{
    public static function format(Carbon $value, string $mod): string
    {
        $dfMod = substr($mod, 3, -1);

        return self::transliteMonth($value->format($dfMod));
    }

    private static function transliteMonth(string $string): string
    {
        $from = [
            'January',
            'February',
            'March',
            'April',
            'May',
            'June',
            'July',
            'August',
            'September',
            'October',
            'November',
            'December',
        ];
        $to = [
            'января',
            'февраля',
            'марта',
            'апреля',
            'мая',
            'июня',
            'июля',
            'августа',
            'сентября',
            'октября',
            'ноября',
            'декабря',
        ];

        return Str::replace($from, $to, $string);
    }
}
