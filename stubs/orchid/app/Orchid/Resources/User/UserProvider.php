<?php

declare(strict_types=1);

namespace App\Orchid\Resources\User;

use App\Orchid\Resources\User\Screens\UserEditScreen;
use App\Orchid\Resources\User\Screens\UserListScreen;
use App\Orchid\Resources\User\Screens\UserProfileScreen;
use Orchid\Platform\Dashboard;
use Orchid\Platform\OrchidServiceProvider;
use Illuminate\Support\Facades\Route;
use Tabuna\Breadcrumbs\Trail;
use Orchid\Platform\ItemPermission;

/**
 * Провайдер для ресурса User.
 * Используйте для регистрации экранов/прав/меню, относящихся к пользователям.
 */
class UserProvider extends OrchidServiceProvider
{
    public function register(): void
    {
        parent::register();
        // Зарегистрируйте биндинги или события, связанные с User
    }

    public function boot(Dashboard $dashboard): void
    {
        parent::boot($dashboard);
        // Выполните действия при инициализации Orchid для ресурса User
    }

    public function routes(): void
    {
        parent::routes();
        // Platform > Profile
        Route::screen('profile', UserProfileScreen::class)
            ->name('platform.profile')
            ->breadcrumbs(fn(Trail $trail) => $trail
                ->parent('platform.index')
                ->push(__('Profile'), route('platform.profile')));

        // Platform > System > Users > User
        Route::screen('users/{user}/edit', UserEditScreen::class)
            ->name('platform.systems.users.edit')
            ->breadcrumbs(fn(Trail $trail, $user) => $trail
                ->parent('platform.systems.users')
                ->push($user->name, route('platform.systems.users.edit', $user)));

        // Platform > System > Users > Create
        Route::screen('users/create', UserEditScreen::class)
            ->name('platform.systems.users.create')
            ->breadcrumbs(fn(Trail $trail) => $trail
                ->parent('platform.systems.users')
                ->push(__('Create'), route('platform.systems.users.create')));

        // Platform > System > Users
        Route::screen('users', UserListScreen::class)
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
