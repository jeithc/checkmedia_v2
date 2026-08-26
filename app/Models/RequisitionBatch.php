<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Orchid\Filters\Filterable;
use Orchid\Screen\AsSource;

class RequisitionBatch extends Model
{
    use AsSource, Filterable, HasFactory;

    protected $fillable = [
        'name',
        'city',
        'created_by',
        'advisual_requisition_id',
        'advisual_sync_error',
        'advisual_synced_at',
        'cancelled_at',
        'cancelled_by',
    ];

    protected $casts = [
        'advisual_synced_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    protected $allowedSorts = [
        'name',
        'city',
        'created_at',
    ];

    public function maintenances(): HasMany
    {
        return $this->hasMany(Maintenance::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function cancelledBy()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    public function getTotalCostAttribute(): float
    {
        return (float) $this->maintenances()->sum('advisual_purchase_order_total');
    }

    public function getSpacesCountAttribute(): int
    {
        return $this->maintenances()->count();
    }

    public function getWithPoCountAttribute(): int
    {
        return $this->maintenances()->whereNotNull('advisual_purchase_order_id')->count();
    }
}
