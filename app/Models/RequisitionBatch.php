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
        'sending_at',
        'cancelled_at',
        'cancelled_by',
    ];

    protected $casts = [
        'advisual_synced_at' => 'datetime',
        'sending_at' => 'datetime',
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

    const STATUS_CANCELLED = 'cancelled';

    const STATUS_ERROR = 'error';

    const STATUS_SENDING = 'sending';

    const STATUS_UNSENT = 'unsent';

    const STATUS_WITH_PO = 'with_po';

    const STATUS_ACTIVE = 'active';

    /**
     * Derived lifecycle state, in priority order. Not stored: every input already
     * lives on the row, and a stored copy would drift.
     */
    public function getStatusAttribute(): string
    {
        if ($this->cancelled_at) {
            return self::STATUS_CANCELLED;
        }
        if ($this->advisual_sync_error) {
            return self::STATUS_ERROR;
        }
        if ($this->sending_at) {
            return self::STATUS_SENDING;
        }
        if (! $this->advisual_requisition_id) {
            return self::STATUS_UNSENT;
        }
        if ($this->spaces_count > 0 && $this->with_po_count === $this->spaces_count) {
            return self::STATUS_WITH_PO;
        }

        return self::STATUS_ACTIVE;
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_CANCELLED => 'Cancelado',
            self::STATUS_ERROR => 'Error',
            self::STATUS_SENDING => 'Enviando',
            self::STATUS_UNSENT => 'Sin enviar',
            self::STATUS_WITH_PO => 'Con OC',
            default => 'Activo',
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_CANCELLED => 'secondary',
            self::STATUS_ERROR => 'danger',
            self::STATUS_SENDING => 'warning',
            self::STATUS_UNSENT => 'secondary',
            self::STATUS_WITH_PO => 'success',
            default => 'primary',
        };
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
