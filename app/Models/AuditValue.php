<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditValue extends Model
{
    use HasFactory;

    protected $fillable = [
        'audit_id',
        'audit_criterion_id',
        'value',
        'comment',
    ];

    public function criterion()
    {
        return $this->belongsTo(AuditCriterion::class, 'audit_criterion_id');
    }

    public function audit()
    {
        return $this->belongsTo(Audit::class);
    }

    public function maintenances()
    {
        return $this->belongsToMany(Maintenance::class, 'maintenance_audit_value')->withTimestamps();
    }
}
