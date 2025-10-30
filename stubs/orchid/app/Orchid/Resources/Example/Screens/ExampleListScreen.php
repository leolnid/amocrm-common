<?php

declare(strict_types=1);

namespace App\Orchid\Resources\Example\Screens;

use App\Models\Example;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Screen;
use Orchid\Screen\TD;
use Orchid\Support\Facades\Layout;

class ExampleListScreen extends Screen
{
    public function query(): iterable
    {
        return [
            'examples' => Example::query()
                ->defaultSort('id', 'desc')
                ->paginate(),
        ];
    }

    public function name(): ?string
    {
        return 'Примеры';
    }

    public function commandBar(): iterable
    {
        return [
            Link::make('Добавить')
                ->icon('bs.plus-circle')
                ->route('platform.examples.create'),
        ];
    }

    public function layout(): iterable
    {
        return [
            Layout::table('examples', [
                TD::make('id', 'ID')->sort(),
                TD::make('name', 'Название')
                    ->filter(Input::make())
                    ->render(fn(Example $example) => Link::make($example->name)
                        ->route('platform.examples.edit', $example->id)),
                TD::make('created_at', 'Создан'),
            ]),
        ];
    }
}
