<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Audit extends Model
{
    use HasFactory;

    protected $fillable = [
        'advertising_space_id',
        'user_id',
        'year',
        'week',
        'audit_date',
        'general_status',
        'observation'
    ];

    public function advertisingSpace()
    {
        return $this->belongsTo(AdvertisingSpace::class);
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
