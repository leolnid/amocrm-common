<?php

declare(strict_types=1);

namespace App\Orchid\Resources\Example\Screens;

use App\Models\Example;
use Illuminate\Http\Request;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class ExampleEditScreen extends Screen
{
    public Example $example;

    public function query(Example $example): iterable
    {
        return [
            'example' => $example,
        ];
    }

    public function name(): ?string
    {
        return $this->example->exists ? 'Редактирование' : 'Создание';
    }

    public function commandBar(): iterable
    {
        return [
            Button::make($this->example->exists ? 'Сохранить' : 'Создать')
                ->icon('bs.check-circle')
                ->method('save'),

            Button::make('Удалить')
                ->icon('bs.trash3')
                ->method('remove')
                ->canSee($this->example->exists),
        ];
    }

    public function layout(): iterable
    {
        return [
            Layout::block([
                Layout::rows([
                    Input::make('example.name')->required()->title('Название'),
                    TextArea::make('example.description')->title('Описание')->rows(5),
                ]),
            ])->title('Основная информация'),
        ];
    }

    public function save(Request $request, Example $example)
    {
        $example->fill($request->get('example'));
        $example->save();

        Toast::info('Сохранено');

        return redirect()->route('platform.examples');
    }

    public function remove(Example $example)
    {
        $example->delete();
        Toast::info('Удалено');
        return redirect()->route('platform.examples');
    }
}
