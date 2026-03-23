<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Orchid\Filters\Filterable;
use Orchid\Screen\AsSource;
use Orchid\Filters\Types\Like;
use Orchid\Filters\Types\Where;

class AuditCriterion extends Model
{
    use HasFactory, AsSource, Filterable;

    protected $table = 'audit_criteria'; // Non-standard plural

    protected $fillable = [
        'name',
        'key',
        'type',
        'is_active',
        'order_index',
        'category',
    ];

    public function scopeForCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    /**
     * @var array
     */
    protected $allowedSorts = [
        'name',
        'key',
        'type',
        'is_active',
        'order_index'
    ];

    /**
     * @var array
     */
    protected $allowedFilters = [
        'name' => Like::class,
        'key' => Like::class,
    ];
}
