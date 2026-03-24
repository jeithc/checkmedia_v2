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
    protected $fillable = [
        'name',
        'username', // Added
        'email',
        'password',
        'permissions', // Added for mass assignment
        'is_active', // Added
        'must_change_password', // Added
        'avatar_path', // Added
        'is_superuser', // Added
        'is_external',
        'phone',
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
        'is_external' => 'boolean',
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
