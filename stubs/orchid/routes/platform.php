<?php

declare(strict_types=1);

use App\Orchid\PlatformScreen;
use Illuminate\Support\Facades\Route;
use Tabuna\Breadcrumbs\Trail;

/**
 * Шаблон маршрутов Orchid Platform
 *
 * Маршруты конкретных ресурсов (Users, Roles и т.д.)
 * регистрируются их провайдерами: app/Orchid/Resources/*/*Provider.php
 */

// Главная страница
Route::screen('/main', PlatformScreen::class)
    ->name('platform.main');

// Профиль, пользователи, роли и другие ресурсы
// регистрируются соответствующими провайдерами ресурсов.

// Пример маршрутов для ресурса (замените Resource на вашу модель)
/*
Route::prefix('resources')->group(function () {
    Route::screen('', ResourceListScreen::class)
        ->name('platform.resources')
        ->breadcrumbs(fn(Trail $trail) => $trail
            ->parent('platform.index')
            ->push('Ресурсы', route('platform.resources')));

    Route::screen('{resource}/show', ResourceShowScreen::class)
        ->name('platform.resources.show')
        ->breadcrumbs(fn(Trail $trail, $resource) => $trail
            ->parent('platform.resources')
            ->push('Просмотр', route('platform.resources.show', $resource)));

    Route::screen('create', ResourceEditScreen::class)
        ->name('platform.resources.create')
        ->breadcrumbs(fn(Trail $trail) => $trail
            ->parent('platform.resources')
            ->push('Создание', route('platform.resources.create')));

    Route::screen('{resource}/edit', ResourceEditScreen::class)
        ->name('platform.resources.edit')
        ->breadcrumbs(fn(Trail $trail, $resource) => $trail
            ->parent('platform.resources')
            ->push('Редактирование', route('platform.resources.edit', $resource)));
});
*/

