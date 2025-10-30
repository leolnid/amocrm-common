# Шаблоны Orchid Platform

Этот пакет содержит шаблоны для быстрого внедрения Laravel Orchid Platform в новый проект.

## Структура

```
stubs/orchid/
├── app/Orchid/                    # Основные классы Orchid
│   ├── PlatformProvider.php       # Провайдер платформы (меню, права)
│   ├── PlatformScreen.php         # Главный экран
│   └── Resources/                 # Ресурсы (User, Role - примеры)
│       ├── User/                  # Полный пример CRUD для User
│       └── Role/                  # Полный пример CRUD для Role
├── routes/
│   └── platform.php              # Маршруты Orchid
├── config/
│   └── platform.php              # Конфигурация Orchid
└── database/migrations/           # Миграции для таблиц Orchid
    ├── 2015_04_12_000000_create_orchid_users_table.php
    ├── 2015_10_19_214424_create_orchid_roles_table.php
    └── 2015_10_19_214425_create_orchid_role_users_table.php
```

## Установка в новый проект

### 1. Установка Orchid

```bash
composer require orchid/platform
```

### 2. Копирование файлов

Скопируйте все файлы из `packages/leolnid/common/stubs/orchid/` в корень проекта:

```bash
# Windows PowerShell
Copy-Item -Path "packages/leolnid/common/stubs/orchid/app" -Destination "app" -Recurse -Force
Copy-Item -Path "packages/leolnid/common/stubs/orchid/routes/platform.php" -Destination "routes/platform.php" -Force
Copy-Item -Path "packages/leolnid/common/stubs/orchid/config/platform.php" -Destination "config/platform.php" -Force
Copy-Item -Path "packages/leolnid/common/stubs/orchid/database/migrations/*" -Destination "database/migrations/" -Force
```

Или вручную скопируйте файлы через IDE.

### 3. Публикация ресурсов Orchid

```bash
php artisan orchid:install
```

### 4. Настройка моделей

Убедитесь, что модель `User` использует trait `AsSource`:

```php
use Orchid\Screen\AsSource;

class User extends Authenticatable
{
    use AsSource;
    // ...
}
```

### 5. Настройка отношений

В модели `User` добавьте отношение к ролям:

```php
use Orchid\Access\RoleAccess;

class User extends Authenticatable
{
    use RoleAccess;
    
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_users');
    }
}
```

### 6. Выполнение миграций

```bash
php artisan migrate
```

### 7. Настройка провайдеров

В `bootstrap/providers.php` или `config/app.php` убедитесь, что Orchid зарегистрирован (обычно регистрируется автоматически через package discovery).

Провайдеры ресурсов автоматически обнаруживаются:
- Любые классы, оканчивающиеся на `Provider.php` в `app/Orchid/Resources/**` и наследующиеся от `OrchidServiceProvider`,
  будут автоматически найдены корневым `App/Orchid/PlatformProvider` и для них будет вызван `register()` и `boot()`.

Примеры провайдеров:
- `app/Orchid/Resources/User/UserProvider.php`
- `app/Orchid/Resources/Role/RoleProvider.php`

### 8. Настройка маршрутов

Убедитесь, что маршруты Orchid подключены. Обычно это делается автоматически, но проверьте `routes/platform.php`.

## Документация

Полная документация по работе с Orchid находится в `packages/leolnid/common/docs/orchid-prompt.md`.

## Создание нового ресурса

Для создания нового ресурса (CRUD экрана) скопируйте структуру из `Resources/User/` или `Resources/Role/` и адаптируйте под вашу модель.

Пример структуры:
```
app/Orchid/Resources/YourResource/
├── Screens/
│   ├── YourResourceListScreen.php
│   └── YourResourceEditScreen.php
└── Layouts/
    ├── YourResourceListLayout.php
    └── YourResourceEditLayout.php
```

## Полезные ссылки

- Документация Orchid: https://orchid.software/docs
- Bootstrap Icons: https://icons.getbootstrap.com/

