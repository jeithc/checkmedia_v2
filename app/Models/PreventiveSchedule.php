<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Orchid\Filters\Filterable;
use Orchid\Screen\AsSource;

class PreventiveSchedule extends Model
{
    use HasFactory, AsSource, Filterable;

    protected $fillable = [
        'element_type',
        'unit',
        'city',
        'frequency_days',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'frequency_days' => 'integer',
    ];

    protected $allowedSorts = [
        'element_type',
        'unit',
        'city',
        'frequency_days',
        'is_active',
        'updated_at',
    ];

    protected $allowedFilters = [
        'element_type' => \Orchid\Filters\Types\Like::class,
        'unit' => \Orchid\Filters\Types\Like::class,
        'city' => \Orchid\Filters\Types\Like::class,
    ];
}
