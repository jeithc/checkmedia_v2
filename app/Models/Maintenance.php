<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Orchid\Filters\Filterable;
use Orchid\Screen\AsSource;

class Maintenance extends Model
{
    use AsSource, Filterable, HasFactory;

    const STATUS_REPORTED = 'reported';

    const STATUS_PENDING_ADVISUAL = 'pending_advisual';

    const STATUS_IN_PROGRESS = 'in_progress';

    const STATUS_CLOSED = 'closed';

    const TYPE_CORRECTIVE = 'corrective';

    const TYPE_PREVENTIVE = 'preventive';

    protected $fillable = [
        'advertising_space_id',
        'audit_id',
        'requested_by',
        'requested_at',
        'type',
        'category',
        'status',
        'priority',
        'matrix_data',
        'description',
        'estimated_cost',
        'final_cost',
        'advisual_requisition_id',
        'advisual_synced_at',
        'advisual_sync_error',
        'advisual_purchase_order_id',
        'advisual_purchase_order_line_id',
        'advisual_purchase_order_description',
        'advisual_purchase_order_quantity',
        'advisual_purchase_order_unit_price',
        'advisual_purchase_order_total',
        'advisual_purchase_order_created_at',
        'advisual_purchase_order_committed_at',
        'advisual_purchase_order_executed_at',
        'advisual_purchase_order_last_checked_at',
        'advisual_purchase_order_sync_error',
        'closed_by',
        'closed_at',
        'closure_document_path',
        'closure_comment',
        'support_files_paths',
    ];

    protected $casts = [
        'matrix_data' => 'array',
        'requested_at' => 'datetime',
        'estimated_cost' => 'decimal:2',
        'final_cost' => 'decimal:2',
        'advisual_synced_at' => 'datetime',
        'advisual_purchase_order_quantity' => 'decimal:4',
        'advisual_purchase_order_unit_price' => 'decimal:2',
        'advisual_purchase_order_total' => 'decimal:2',
        'advisual_purchase_order_created_at' => 'datetime',
        'advisual_purchase_order_committed_at' => 'datetime',
        'advisual_purchase_order_executed_at' => 'datetime',
        'advisual_purchase_order_last_checked_at' => 'datetime',
        'closed_at' => 'datetime',
        'support_files_paths' => 'array',
    ];

    protected $allowedSorts = [
        'status',
        'category',
        'priority',
        'requested_at',
        'created_at',
    ];

    public function advertisingSpace()
    {
        return $this->belongsTo(AdvertisingSpace::class);
    }

    public function audit()
    {
        return $this->belongsTo(Audit::class);
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function closedBy()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function auditValues()
    {
        return $this->belongsToMany(AuditValue::class, 'maintenance_audit_value')->withTimestamps();
    }

    public function canBeClosed(): bool
    {
        return $this->status !== self::STATUS_CLOSED;
    }

    public function hasRequisition(): bool
    {
        return ! empty($this->advisual_requisition_id);
    }

    public function hasPurchaseOrder(): bool
    {
        return ! empty($this->advisual_purchase_order_id);
    }

    public function getCategoryLabelAttribute(): string
    {
        $names = $this->auditValues()
            ->with('criterion')
            ->get()
            ->pluck('criterion.name')
            ->filter()
            ->unique()
            ->values();

        if ($names->isNotEmpty()) {
            return $names->join(', ');
        }

        return MaintenanceCategory::label($this->category);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_REPORTED => 'Reportado',
            self::STATUS_PENDING_ADVISUAL => 'Pendiente Advisual',
            self::STATUS_IN_PROGRESS => 'En Progreso',
            self::STATUS_CLOSED => 'Cerrado',
            default => ucfirst($this->status),
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_REPORTED => 'warning',
            self::STATUS_PENDING_ADVISUAL => 'info',
            self::STATUS_IN_PROGRESS => 'primary',
            self::STATUS_CLOSED => 'success',
            default => 'secondary',
        };
    }
}
