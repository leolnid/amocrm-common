<?php

declare(strict_types=1);

namespace App\Orchid\Resources\Role;

use App\Orchid\Resources\Role\Screens\RoleEditScreen;
use App\Orchid\Resources\Role\Screens\RoleListScreen;
use Illuminate\Routing\Router;
use Orchid\Platform\Dashboard;
use Orchid\Platform\OrchidServiceProvider;
use Illuminate\Support\Facades\Route;
use Tabuna\Breadcrumbs\Trail;
use Orchid\Platform\ItemPermission;

/**
 * Провайдер для ресурса Role.
 * Используйте для регистрации экранов/прав/меню, относящихся к ролям.
 */
class RoleProvider extends OrchidServiceProvider
{
    public function register(): void
    {
        parent::register();
        // Зарегистрируйте биндинги или события, связанные с Role
    }

    public function boot(Dashboard $dashboard): void
    {
        parent::boot($dashboard);
        // Выполните действия при инициализации Orchid для ресурса Role
    }

    public function routes(Router $router): void
    {
        $router->screen('roles/{role}/edit', RoleEditScreen::class)
            ->name('platform.systems.roles.edit')
            ->breadcrumbs(fn(Trail $trail, $role) => $trail
                ->parent('platform.systems.roles')
                ->push($role->name, route('platform.systems.roles.edit', $role)));

        // Platform > System > Roles > Create
        Route::screen('roles/create', RoleEditScreen::class)
            ->name('platform.systems.roles.create')
            ->breadcrumbs(fn(Trail $trail) => $trail
                ->parent('platform.systems.roles')
                ->push(__('Create'), route('platform.systems.roles.create')));

        // Platform > System > Roles
        Route::screen('roles', RoleListScreen::class)
            ->name('platform.systems.roles')
            ->breadcrumbs(fn(Trail $trail) => $trail
                ->parent('platform.index')
                ->push(__('Roles'), route('platform.systems.roles')));
    }

    public function permissions(): array
    {
        return [
            ItemPermission::group(__('System'))
                ->addPermission('platform.systems.roles', __('Roles')),
        ];
    }
}
