<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Orchid\Metrics\Chartable;

class Audit extends Model
{
    use HasFactory, Chartable;

    protected $fillable = [
        'advertising_space_id',
        'user_id',
        'year',
        'week',
        'audit_date',
        'general_status',
        'observation'
    ];

    protected $casts = [
        'audit_date' => 'datetime',
    ];

    public function space()
    {
        return $this->belongsTo(AdvertisingSpace::class, 'advertising_space_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function values()
    {
        return $this->hasMany(AuditValue::class);
    }

    public function photos()
    {
        return $this->hasMany(AuditPhoto::class);
    }

    /**
     * Helper to get the total score or status from values
     */
    public function calculateGeneralStatus()
    {
        // Example logic: if any criterion is bad -> bad
        $hasIssues = $this->values()->where('value', 'bad')->exists();
        $this->update(['general_status' => $hasIssues ? 'bad' : 'good']);
    }
}
