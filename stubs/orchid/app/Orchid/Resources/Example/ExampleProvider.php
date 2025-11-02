<?php

declare(strict_types=1);

namespace App\Orchid\Resources\Example;

use App\Orchid\Resources\Example\Screens\ExampleEditScreen;
use App\Orchid\Resources\Example\Screens\ExampleListScreen;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Orchid\Platform\Dashboard;
use Orchid\Platform\ItemPermission;
use Orchid\Platform\OrchidServiceProvider;
use Tabuna\Breadcrumbs\Trail;

class ExampleProvider extends OrchidServiceProvider
{
    public function register(): void
    {
        parent::register();
    }

    public function boot(Dashboard $dashboard): void
    {
        parent::boot($dashboard);
    }

    public function routes(Router $router): void
    {
        $router->prefix('examples')->group(function () {
            Route::screen('create', ExampleEditScreen::class)
                ->name('platform.examples.create')
                ->breadcrumbs(fn(Trail $trail) => $trail
                    ->parent('platform.examples')
                    ->push('Создание', route('platform.examples.create')));

            Route::screen('{example}/edit', ExampleEditScreen::class)
                ->name('platform.examples.edit')
                ->breadcrumbs(fn(Trail $trail, $example) => $trail
                    ->parent('platform.examples')
                    ->push('Редактирование', route('platform.examples.edit', $example)));

            Route::screen('', ExampleListScreen::class)
                ->name('platform.examples')
                ->breadcrumbs(fn(Trail $trail) => $trail
                    ->parent('platform.index')
                    ->push('Примеры', route('platform.examples')));
        });
    }

    public function permissions(): array
    {
        return [
            ItemPermission::group('Examples')
                ->addPermission('platform.examples', 'Examples'),
        ];
    }
}
