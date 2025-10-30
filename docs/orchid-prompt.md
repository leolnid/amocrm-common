# Промпт для работы с Laravel Orchid Platform

## Описание
Этот промпт описывает стандарты и подходы к работе с Laravel Orchid Platform, используемые в проекте. Orchid - это административная панель для Laravel приложений.

## Структура проекта

### Основные файлы и папки

1. **app/Orchid/** - Основная папка с конфигурацией Orchid
   - `PlatformProvider.php` - Корневой провайдер платформы (меню, автопоиск ресурсных провайдеров)
   - `PlatformScreen.php` - Главный экран платформы
   - `Resources/` - Ресурсы (CRUD экраны для моделей) и их провайдеры

2. **routes/platform.php** - Базовые маршруты платформы Orchid (главная). Маршруты ресурсов регистрируются в их провайдерах

3. **config/platform.php** - Конфигурация Orchid

4. **database/migrations/** - Миграции для таблиц Orchid (users, roles, role_users, attachments, notifications)

## Архитектура экранов Orchid

### Структура ресурса

Каждый ресурс должен иметь следующую структуру:

```
app/Orchid/Resources/{ResourceName}/
├── Screens/
│   ├── {ResourceName}ListScreen.php    # Список записей
│   ├── {ResourceName}EditScreen.php    # Создание/редактирование
│   └── {ResourceName}ShowScreen.php    # Просмотр (опционально)
├── Layouts/
│   ├── {ResourceName}ListLayout.php    # Layout для списка
│   ├── {ResourceName}EditLayout.php    # Layout для редактирования
│   └── {ResourceName}FiltersLayout.php  # Фильтры (опционально)
└── Filters/
    └── {ResourceName}Filter.php        # Кастомные фильтры (опционально)
```

### Типы экранов

1. **ListScreen** - Экран списка записей
   - Метод `query()` - получение данных с пагинацией
   - Метод `layout()` - возвращает `Layout::table()` с TD колонками
   - Метод `commandBar()` - кнопки действий (обычно "Добавить")

2. **EditScreen** - Экран создания/редактирования
   - Метод `query()` - получение данных для редактирования
   - Метод `layout()` - возвращает блоки с формами (`Layout::block()`)
   - Метод `commandBar()` - кнопки сохранения/удаления
   - Метод `save()` - сохранение данных
   - Метод `remove()` - удаление записи

3. **ShowScreen** - Экран просмотра (часто совмещают с EditScreen)

### Layouts

#### Рекомендация: инлайн для одноразовых Layout

Если Layout используется только один раз на экране — делайте его инлайново через `Layout::rows()` или `Layout::table()` прямо в `Screen::layout()`. Выносить в отдельный класс стоит только если:
- он используется повторно в нескольких экранах;
- он существенно усложнён и требует изоляции.

#### Table Layout (для списков)

```php
// Инлайн-таблица внутри экрана
return [
    Layout::table('resources', [
        TD::make('id', 'ID')->sort()->filter(Input::make()),
        TD::make('name', 'Название')
            ->sort()
            ->filter(Input::make())
            ->render(fn($resource) => Link::make($resource->name)
                ->route('platform.resources.edit', $resource->id)),
        TD::make(__('Actions'))
            ->align(TD::ALIGN_CENTER)
            ->width('100px')
            ->render(fn($resource) => DropDown::make()
                ->list([
                    Link::make('Редактировать')->route('platform.resources.edit', $resource->id),
                    Button::make('Удалить')->method('remove', ['id' => $resource->id]),
                ])),
    ]),
];
```

#### Rows Layout (для форм)

```php
// Инлайн-поля внутри экрана
return [
    Layout::block([
        Layout::rows([
            Input::make('resource.name')->title('Название')->required()->placeholder('Введите название'),
            TextArea::make('resource.description')->title('Описание')->rows(5),
            Select::make('resource.status')
                ->options(['active' => 'Активный','inactive' => 'Неактивный'])
                ->title('Статус'),
        ]),
    ])->title('Основная информация')
];
```

#### Block Layout (обертка для форм)

В EditScreen используется `Layout::block()` для группировки полей:

```php
Layout::block(ResourceEditLayout::class)
    ->title('Основная информация')
    ->description('Основные данные ресурса')
    ->commands(
        Button::make('Сохранить')
            ->type(Color::BASIC)
            ->icon('bs.check-circle')
            ->method('save')
    ),
```

## Маршруты

- Базовые маршруты в `routes/platform.php` — только главная (`/main`) и общее.
- Маршруты каждого ресурса регистрируются в его провайдере (`app/Orchid/Resources/*/*Provider.php`) в методе `routes()`.

## PlatformProvider

### Настройка меню

```php
public function menu(): array
{
    return [
        Menu::make('Главная')
            ->icon('bs.book')
            ->route(config('platform.index')),
        
        Menu::make('Ресурсы')
            ->icon('bs.layers')
            ->route('platform.resources')
            ->title('Администрирование'),
        
        Menu::make('Пользователи')
            ->icon('bs.people')
            ->route('platform.systems.users')
            ->permission('platform.systems.users')
            ->title('Система'),
    ];
}
```

### Права доступа

- Права доступа конкретных ресурсов регистрируются в их провайдерах в методе `permissions()`.
- Корневой `PlatformProvider` не должен дублировать права ресурсов.

### Замена модели User

```php
public function boot(Dashboard $dashboard): void
{
    \Orchid\Support\Facades\Dashboard::useModel(
        \Orchid\Platform\Models\User::class, 
        \App\Models\User::class
    );
    
    parent::boot($dashboard);
}
```

## Модели

### Использование AsSource trait

Для работы с Orchid модели должны использовать trait `AsSource`:

```php
use Orchid\Screen\AsSource;

class Resource extends Model
{
    use AsSource;
    
    // ...
}
```

### Правила для моделей, используемых в Orchid

Для корректной работы со списками, фильтрами и сортировками все модели, используемые в экранах Orchid, должны:

- использовать трейты:
  - `Orchid\Screen\AsSource` — источник данных для экранов/layout'ов;
  - `Orchid\Filters\Filterable` — поддержка фильтрации и сортировки (отдельного трейта `Sortable` в Orchid нет);
  - при необходимости — `Orchid\Access\RoleAccess` (для пользователей/ролей).
- иметь публичные свойства c разрешёнными полями фильтров и сортировок:
  - `$allowedFilters = [...]` — список фильтруемых полей и/или `Where`/`Like` выражений;
  - `$allowedSorts = [...]` — список сортируемых полей.
- иметь заполненный `$fillable` (или надлежащую защиту mass assignment), используемый в `EditScreen::save()`.

Пример:

```php
use Illuminate\Database\Eloquent\Model;
use Orchid\Filters\Filterable;
use Orchid\Filters\Types\Like;
use Orchid\Filters\Types\Where;
use Orchid\Filters\Types\WhereDateStartEnd;
use Orchid\Screen\AsSource;

class Example extends Model
{
    use AsSource, Filterable;

    protected $fillable = [
        'name',
        'description',
        'status',
    ];

    protected array $allowedSorts = [
        'id', 'name', 'created_at', 'updated_at',
    ];

    protected array $allowedFilters = [
        // точное сравнение
        'id' => Where::class,
        // поиск по подстроке
        'name' => Like::class,
        'status' => Like::class,
        // фильтрация по дате (между)
        'created_at' => WhereDateStartEnd::class,
    ];
}
```

### Presenter для модели

Для поиска и отображения в UI создается Presenter:

```php
class ResourcePresenter extends Presenter implements Personable, Searchable
{
    public function label(): string
    {
        return 'Resources';
    }
    
    public function title(): string
    {
        return $this->entity->name;
    }
    
    public function url(): string
    {
        return route('platform.resources.edit', $this->entity);
    }
    
    public function image(): ?string
    {
        // URL к изображению
        return asset('images/default.png');
    }
}
```

В модели добавляется метод `presenter()`:

```php
public function presenter(): ResourcePresenter
{
    return new ResourcePresenter($this);
}
```

## Фильтры

### Создание фильтра

```php
use Orchid\Filters\Filter;

class ResourceFilter extends Filter
{
    public array $parameters = ['status'];
    
    public function name(): string
    {
        return 'Статус';
    }
    
    public function parameters(): ?array
    {
        return [
            Select::make('status')
                ->options([
                    'active' => 'Активный',
                    'inactive' => 'Неактивный',
                ])
                ->title('Статус'),
        ];
    }
    
    public function filter(Builder $builder, Collection $parameters): Builder
    {
        if ($parameters->get('status')) {
            $builder->where('status', $parameters->get('status'));
        }
        
        return $builder;
    }
}
```

### Использование фильтров в Layout

```php
class ResourceFiltersLayout extends Selection
{
    public function filters(): array
    {
        return [
            ResourceFilter::class,
        ];
    }
}
```

## Иконки

В проекте используются Bootstrap Icons. Доступные иконки:
- `bs.book` - книга
- `bs.people` - люди
- `bs.shield` - щит (роли)
- `bs.layers` - слои (модули)
- `bs.plus-circle` - добавить
- `bs.pencil` - редактировать
- `bs.trash3` - удалить
- `bs.three-dots-vertical` - меню действий

Список всех иконок: https://icons.getbootstrap.com/

## Миграции

### Таблица users (расширение)

Миграция добавляет поле `permissions` к таблице `users`:

```php
Schema::table('users', function (Blueprint $table) {
    $table->jsonb('permissions')->nullable();
});
```

### Таблица roles

```php
Schema::create('roles', function (Blueprint $table): void {
    $table->increments('id');
    $table->string('slug')->unique();
    $table->string('name');
    $table->jsonb('permissions')->nullable();
    $table->timestamps();
});
```

### Таблица role_users (связь many-to-many)

```php
Schema::create('role_users', function (Blueprint $table) {
    $table->unsignedBigInteger('user_id');
    $table->unsignedInteger('role_id');
    $table->primary(['user_id', 'role_id']);
    // внешние ключи
});
```

## Конфигурация (config/platform.php)

Основные настройки:
- `prefix` - префикс для маршрутов (по умолчанию `/admin`)
- `middleware` - middleware для роутов
- `guard` - guard для аутентификации
- `index` - главный маршрут
- `provider` - класс PlatformProvider

## Лучшие практики

1. **Именование**: Используй понятные названия для экранов и layout'ов
2. **Разделение ответственности**: Каждый Layout отвечает за свою часть UI
3. **Валидация**: Всегда валидируй данные в методах `save()`
4. **Права доступа**: Используй `permission()` метод в Screen для ограничения доступа
5. **Breadcrumbs**: Добавляй breadcrumbs для удобной навигации
6. **Toast уведомления**: Используй `Toast::info()` для обратной связи с пользователем
7. **Иконки**: Используй единый стиль иконок (Bootstrap Icons)
8. **Пагинация**: Используй `->paginate()` для списков

## Примеры использования

### CRUD экран для модели

1. Создай миграцию для таблицы
2. Создай модель с `AsSource` trait
3. Создай Screen'ы (List, Edit, Show)
4. Создай Layout'ы (List, Edit, Filters)
5. Добавь провайдер ресурса (`app/Orchid/Resources/{Resource}/{Resource}Provider.php`):
   - `routes()` — маршруты ресурса
   - `permissions()` — права ресурса
   - (опционально) `menu()` — пункт(ы) меню
6. Добавь пункт меню либо в провайдер ресурса, либо в `PlatformProvider`

### Работа с формами

- Используй `Layout::block()` для группировки полей
- Используй `->commands()` для кнопок действий внутри блока
- Используй `->required()` для обязательных полей
- Используй `->help()` для подсказок
- Используй `->placeholder()` для примеров значений

### Работа со списками

- Используй `TD::make()` для колонок
- Используй `->sort()` для сортировки
- Используй `->filter()` для фильтрации
- Используй `->render()` для кастомного отображения
- Используй `->align()` для выравнивания

## Дополнительные возможности

### Impersonation (вход от имени пользователя)

```php
use Orchid\Access\Impersonation;

public function loginAs(User $user)
{
    Impersonation::loginAs($user);
    return redirect()->route(config('platform.index'));
}
```

### Поиск (Search)

Для включения поиска:
1. Создай Presenter с интерфейсом `Searchable`
2. Добавь модель в `config/platform.php` -> `search`
3. Настрой Scout для модели

## Полезные ссылки

- Документация Orchid: https://orchid.software/docs
- Bootstrap Icons: https://icons.getbootstrap.com/