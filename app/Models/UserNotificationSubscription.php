<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserNotificationSubscription extends Model
{
    protected $fillable = [
        'user_id',
        'event_type',
        'filter_key',
        'filter_value',
        'channel',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
