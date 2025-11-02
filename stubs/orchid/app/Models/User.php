<?php

namespace App\Models;

use App\Orchid\Resources\User\UserPresenter;
use Illuminate\Support\Facades\Hash;
use Orchid\Filters\Types\Like;
use Orchid\Filters\Types\Where;
use Orchid\Filters\Types\WhereDateStartEnd;
use Orchid\Platform\Models\User as Authenticatable;
use Orchid\Support\Facades\Dashboard;

class User extends Authenticatable
{
    protected $fillable = [
        'name',
        'email',
        'password',
        'permissions',
        'account_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'permissions',
    ];

    protected $casts = [
        'permissions' => 'array',
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    protected $allowedFilters = [
        'id' => Where::class,
        'name' => Like::class,
        'email' => Like::class,
        'updated_at' => WhereDateStartEnd::class,
        'created_at' => WhereDateStartEnd::class,
    ];

    protected $allowedSorts = [
        'id',
        'name',
        'email',
        'updated_at',
        'created_at',
    ];

    public static function createAdmin(string $name, string $email, string $password): void
    {
        throw_if(static::where('email', $email)->exists(), 'Пользователь уже существует');

        static::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'permissions' => Dashboard::getAllowAllPermission(),
        ]);
    }

    public function setPasswordAttribute($value): void
    {
        $this->attributes['password'] = Hash::make($value);
    }

    public function presenter()
    {
        return new UserPresenter($this);
    }
}
