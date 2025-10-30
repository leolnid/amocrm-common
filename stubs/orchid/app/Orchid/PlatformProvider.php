<?php

declare(strict_types=1);

namespace App\Orchid;

use App\Models\User;
use Orchid\Platform\Dashboard;
use Orchid\Platform\ItemPermission;
use Orchid\Platform\OrchidServiceProvider;
use Orchid\Screen\Actions\Menu;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Шаблон PlatformProvider для Orchid
 * 
 * Добавьте пункты меню в метод menu()
 * Настройте права доступа в методе permissions()
 */
class PlatformProvider extends OrchidServiceProvider
{
    /** @var array<int, OrchidServiceProvider> */
    private array $resourceProviders = [];

    public function register(): void
    {
        parent::register();
        $this->resourceProviders = $this->discoverResourceProviders();

        // Вызов register() у провайдеров ресурсов на этапе регистрации
        foreach ($this->resourceProviders as $provider) {
            if (method_exists($provider, 'register')) {
                $provider->register();
            }
        }
    }

    public function boot(Dashboard $dashboard): void
    {
        // Замена модели User Orchid на вашу модель User
        \Orchid\Support\Facades\Dashboard::useModel(
            \Orchid\Platform\Models\User::class, 
            User::class
        );

        parent::boot($dashboard);

        // Автоматический вызов boot() у провайдеров ресурсов
        foreach ($this->resourceProviders as $provider) {
            if (method_exists($provider, 'boot')) {
                $provider->boot($dashboard);
            }
        }
    }

    public function menu(): array
    {
        return [
            Menu::make('Главная')
                ->icon('bs.book')
                ->route(config('platform.index')),

            // Пример меню с правами доступа
            // Пример ссылок на системные разделы (могут быть перенесены в провайдеры ресурсов)
            Menu::make(__('Пользователи'))
                ->icon('bs.people')
                ->route('platform.systems.users')
                ->permission('platform.systems.users')
                ->title(__('Система')),

            Menu::make(__('Роли'))
                ->icon('bs.shield')
                ->route('platform.systems.roles')
                ->permission('platform.systems.roles'),

            // Пример динамического меню (если нужен)
            // Menu::make('Ресурсы')
            //     ->list(Resource::all()
            //         ->map(fn(Resource $resource) => Menu::make($resource->name)
            //             ->route('platform.resources.show', $resource->id))
            //         ->all()
            //     )
            //     ->icon('bs.layers')
            //     ->route('platform.resources')
            //     ->title('Администрирование'),

            // Пример меню с badge
            // Menu::make('Запросы')
            //     ->icon('bs.airplane')
            //     ->badge(fn() => Request::count())
            //     ->route('platform.requests.list'),
        ];
    }

    public function permissions(): array
    {
        // Права доступа ресурсов регистрируются в их провайдерах (app/Orchid/Resources/*/*Provider.php)
        return [];
    }

    /**
     * Поиск и инстанцирование провайдеров ресурсов из app/Orchid/Resources/**/**/*Provider.php
     * Провайдеры должны наследоваться от OrchidServiceProvider.
     *
     * @return array<int, OrchidServiceProvider>
     */
    protected function discoverResourceProviders(): array
    {
        $providers = [];
        $resourcesPath = base_path('app/Orchid/Resources');

        if (!is_dir($resourcesPath)) {
            return $providers;
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($resourcesPath));

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                continue;
            }

            $filename = $file->getFilename();
            if (!str_ends_with($filename, 'Provider.php')) {
                continue;
            }

            // Преобразуем путь файла в полное имя класса под пространством имен App\
            $fullPath = $file->getPathname();
            $relative = str_replace([base_path('app') . DIRECTORY_SEPARATOR, '.php'], ['', ''], $fullPath);
            $class = 'App\\' . str_replace(DIRECTORY_SEPARATOR, '\\', $relative);

            if (class_exists($class)) {
                $instance = app($class);
                if ($instance instanceof OrchidServiceProvider) {
                    $providers[] = $instance;
                }
            }
        }

        return $providers;
    }
}

