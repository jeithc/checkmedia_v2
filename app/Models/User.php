<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Orchid\Filters\Types\Like;
use Orchid\Filters\Types\Where;
use Orchid\Filters\Types\WhereDateStartEnd;
use Orchid\Platform\Models\User as Authenticatable;

class User extends Authenticatable
{
    use HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    /**
     * `is_active`, `must_change_password` and `is_superuser` are deliberately
     * NOT mass assignable: they gate access (hasAccess() short-circuits to true
     * for superusers), so they must only ever be set explicitly by a screen
     * that has checked who is asking. `permissions` stays fillable because
     * Orchid's own User::createAdmin() — behind `artisan orchid:admin` — mass
     * assigns it; screens must whitelist their input instead of fill()ing raw
     * request data.
     */
    protected $fillable = [
        'name',
        'username', // Added
        'advisual_usuario_guid', // Advisual solicitante (UsuarioGUID)
        'email',
        'password',
        'permissions', // Required by Orchid's createAdmin()
        'avatar_path', // Added
    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
        'permissions',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'permissions' => 'array',
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
        'must_change_password' => 'boolean',
        'is_superuser' => 'boolean',
    ];

    public function hasAccess(string $permit, bool $cache = true): bool
    {
        if ($this->is_superuser) {
            return true;
        }

        return parent::hasAccess($permit, $cache);
    }

    /**
     * @param  mixed  $permissions
     */
    public function hasAnyAccess($permissions, bool $cache = true): bool
    {
        if ($this->is_superuser) {
            return true;
        }

        return parent::hasAnyAccess($permissions, $cache);
    }

    /**
     * The attributes for which you can use filters in url.
     *
     * @var array
     */
    protected $allowedFilters = [
        'id' => Where::class,
        'name' => Like::class,
        'email' => Like::class,
        'username' => Like::class,
        'is_superuser' => Where::class,
        'updated_at' => WhereDateStartEnd::class,
        'created_at' => WhereDateStartEnd::class,
    ];

    /**
     * The attributes for which can use sort in url.
     *
     * @var array
     */
    protected $allowedSorts = [
        'id',
        'name',
        'email',
        'username',
        'updated_at',
        'created_at',
    ];

    /**
     * Get the notification subscriptions for the user.
     */
    public function notificationSubscriptions()
    {
        return $this->hasMany(UserNotificationSubscription::class);
    }

    /**
     * Get the saved reports for the user.
     */
    public function savedReports()
    {
        return $this->hasMany(SavedReport::class);
    }

    /**
     * Send the password reset notification.
     *
     * @param  string  $token
     * @return void
     */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token)
    {
        $this->notify(new \App\Notifications\CustomResetPassword($token));
    }
}
