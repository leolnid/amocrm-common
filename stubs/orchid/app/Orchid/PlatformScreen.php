<?php

declare(strict_types=1);

namespace App\Orchid;

use Orchid\Screen\Screen;

/**
 * Шаблон PlatformScreen для главной страницы Orchid
 * 
 * Измените name() и description() на нужные вам значения
 * Добавьте layout() если нужен контент на главной странице
 */
class PlatformScreen extends Screen
{
    public function query(): iterable
    {
        return [];
    }

    public function name(): ?string
    {
        return 'Административная панель';
    }

    public function description(): ?string
    {
        return 'Главная страница административной панели';
    }

    public function commandBar(): iterable
    {
        return [];
    }

    public function layout(): iterable
    {
        return [
            // Добавьте Layout::metrics(), Layout::info(), и т.д. при необходимости
        ];
    }
}

