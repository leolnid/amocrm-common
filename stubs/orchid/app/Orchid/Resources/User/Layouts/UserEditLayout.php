<?php

declare(strict_types=1);

namespace App\Orchid\Resources\User\Layouts;

use Orchid\Screen\Field;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Layouts\Rows;

/**
 * Шаблон Layout для формы редактирования пользователя
 */
class UserEditLayout extends Rows
{
    /**
     * The screen's layout elements.
     *
     * @return Field[]
     */
    public function fields(): array
    {
        return [
            Input::make('user.name')
                ->type('text')
                ->max(255)
                ->required()
                ->title(__('Name'))
                ->placeholder(__('Name')),

            Input::make('user.email')
                ->type('email')
                ->required()
                ->title(__('Email'))
                ->placeholder(__('Email')),
        ];
    }
}

