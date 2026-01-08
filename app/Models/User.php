<?php

namespace App\Models;

use Orchid\Filters\Types\Like;
use Orchid\Filters\Types\Where;
use Orchid\Filters\Types\WhereDateStartEnd;
use Orchid\Platform\Models\User as Authenticatable;

class User extends Authenticatable
{
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
        'is_active', // Added
        'must_change_password', // Added
        'avatar_path', // Added
        'is_superuser', // Added
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

    /**
     * @param string $permit
     * @param bool   $cache
     *
     * @return bool
     */
    public function hasAccess(string $permit, bool $cache = true): bool
    {
        if ($this->is_superuser) {
            return true;
        }

        return parent::hasAccess($permit, $cache);
    }

    /**
     * @param mixed $permissions
     * @param bool  $cache
     *
     * @return bool
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
}
