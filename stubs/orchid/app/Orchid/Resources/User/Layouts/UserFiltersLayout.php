<?php

namespace App\Orchid\Resources\User\Layouts;

use App\Orchid\Resources\Role\Filters\RoleFilter;
use Orchid\Filters\Filter;
use Orchid\Screen\Layouts\Selection;

/**
 * Шаблон Layout для фильтров списка пользователей
 */
class UserFiltersLayout extends Selection
{
    /**
     * @return string[]|Filter[]
     */
    public function filters(): array
    {
        return [
            RoleFilter::class,
        ];
    }
}

