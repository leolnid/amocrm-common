<?php

declare(strict_types=1);

namespace App\Orchid\Resources\User;

use App\Orchid\Resources\User\Screens\UserEditScreen;
use App\Orchid\Resources\User\Screens\UserListScreen;
use App\Orchid\Resources\User\Screens\UserProfileScreen;
use Illuminate\Routing\Router;
use Orchid\Platform\Dashboard;
use Orchid\Platform\ItemPermission;
use Orchid\Platform\OrchidServiceProvider;
use Tabuna\Breadcrumbs\Trail;

/**
 * Провайдер для ресурса User.
 * Используйте для регистрации экранов/прав/меню, относящихся к пользователям.
 */
class UserProvider extends OrchidServiceProvider
{
    public function routes(Router $router): void
    {
        // Platform > Profile
        $router->screen('profile', UserProfileScreen::class)
            ->name('platform.profile')
            ->breadcrumbs(fn(Trail $trail) => $trail
                ->parent('platform.index')
                ->push(__('Profile'), route('platform.profile')));

        // Platform > System > Users > User
        $router->screen('users/{user}/edit', UserEditScreen::class)
            ->name('platform.systems.users.edit')
            ->breadcrumbs(fn(Trail $trail, $user) => $trail
                ->parent('platform.systems.users')
                ->push($user->name, route('platform.systems.users.edit', $user)));

        // Platform > System > Users > Create
        $router->screen('users/create', UserEditScreen::class)
            ->name('platform.systems.users.create')
            ->breadcrumbs(fn(Trail $trail) => $trail
                ->parent('platform.systems.users')
                ->push(__('Create'), route('platform.systems.users.create')));

        // Platform > System > Users
        $router->screen('users', UserListScreen::class)
            ->name('platform.systems.users')
            ->breadcrumbs(fn(Trail $trail) => $trail
                ->parent('platform.index')
                ->push(__('Users'), route('platform.systems.users')));
    }

    public function permissions(): array
    {
        return [
            ItemPermission::group(__('System'))
                ->addPermission('platform.systems.users', __('Users')),
        ];
    }
}
