<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedReport extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'description',
        'selected_columns',
        'filters',
        'advanced_config',
        'is_shared',
        'is_advanced',
    ];

    protected $casts = [
        'selected_columns' => 'array',
        'filters' => 'array',
        'advanced_config' => 'array',
        'is_shared' => 'boolean',
        'is_advanced' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeShared($query)
    {
        return $query->where('is_shared', true);
    }

    public function scopePersonal($query, $userId)
    {
        return $query->where('is_shared', false)->where('user_id', $userId);
    }

    public function scopeAdvanced($query)
    {
        return $query->where('is_advanced', true);
    }

    public function scopeStandard($query)
    {
        return $query->where('is_advanced', false);
    }
}
