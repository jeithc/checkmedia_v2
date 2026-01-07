<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SpaceActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'advertising_space_id',
        'user_id',
        'audit_id',
        'activity_type',
        'description',
        'metadata',
        'year',
        'week',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    // Activity Types Constants
    const TYPE_AUDIT_CREATED = 'audit_created';
    const TYPE_AUDIT_UPDATED = 'audit_updated';
    const TYPE_MARKED_THIRD_PARTY = 'marked_third_party';
    const TYPE_RESOLUTION_UPLOADED = 'resolution_uploaded';
    const TYPE_STATUS_CHANGED = 'status_changed';
    const TYPE_COMMENT_ADDED = 'comment_added';

    public function space()
    {
        return $this->belongsTo(AdvertisingSpace::class, 'advertising_space_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function audit()
    {
        return $this->belongsTo(Audit::class);
    }

    /**
     * Get human-readable activity type label
     */
    public function getActivityLabelAttribute(): string
    {
        return match ($this->activity_type) {
            self::TYPE_AUDIT_CREATED => 'Auditoría Creada',
            self::TYPE_AUDIT_UPDATED => 'Auditoría Actualizada',
            self::TYPE_MARKED_THIRD_PARTY => 'Marcado como Tercero',
            self::TYPE_RESOLUTION_UPLOADED => 'Revisión Cargada',
            self::TYPE_STATUS_CHANGED => 'Estado Cambiado',
            self::TYPE_COMMENT_ADDED => 'Comentario Agregado',
            default => ucfirst(str_replace('_', ' ', $this->activity_type)),
        };
    }

    /**
     * Get icon for activity type (Bootstrap Icons)
     */
    public function getActivityIconAttribute(): string
    {
        return match ($this->activity_type) {
            self::TYPE_AUDIT_CREATED => 'bs.plus-circle',
            self::TYPE_AUDIT_UPDATED => 'bs.pencil',
            self::TYPE_MARKED_THIRD_PARTY => 'bs.person-badge',
            self::TYPE_RESOLUTION_UPLOADED => 'bs.camera',
            self::TYPE_STATUS_CHANGED => 'bs.arrow-repeat',
            self::TYPE_COMMENT_ADDED => 'bs.chat-dots',
            default => 'bs.circle',
        };
    }

    /**
     * Get color class for activity type
     */
    public function getActivityColorAttribute(): string
    {
        return match ($this->activity_type) {
            self::TYPE_AUDIT_CREATED => 'success',
            self::TYPE_AUDIT_UPDATED => 'info',
            self::TYPE_MARKED_THIRD_PARTY => 'warning',
            self::TYPE_RESOLUTION_UPLOADED => 'primary',
            self::TYPE_STATUS_CHANGED => 'secondary',
            self::TYPE_COMMENT_ADDED => 'dark',
            default => 'secondary',
        };
    }

    /**
     * Helper to log an activity
     */
    public static function log(
        int $spaceId,
        string $type,
        string $description,
        ?int $userId = null,
        ?int $auditId = null,
        ?array $metadata = null,
        ?int $year = null,
        ?int $week = null
    ): self {
        return self::create([
            'advertising_space_id' => $spaceId,
            'user_id' => $userId ?? auth()->id(),
            'audit_id' => $auditId,
            'activity_type' => $type,
            'description' => $description,
            'metadata' => $metadata,
            'year' => $year,
            'week' => $week,
        ]);
    }
}
