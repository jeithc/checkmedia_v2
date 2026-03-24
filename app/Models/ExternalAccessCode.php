<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ExternalAccessCode extends Model
{
    protected $fillable = [
        'code',
        'label',
        'created_by',
        'max_uses',
        'times_used',
        'expires_at',
        'is_revoked',
        'last_used_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
        'is_revoked' => 'boolean',
        'max_uses' => 'integer',
        'times_used' => 'integer',
    ];

    public static function generateCode(): string
    {
        do {
            $code = 'AUD-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(2));
        } while (static::where('code', $code)->exists());

        return $code;
    }

    public function isValid(): bool
    {
        if ($this->is_revoked) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        if ($this->times_used >= $this->max_uses) {
            return false;
        }

        return true;
    }

    public function recordUsage(): void
    {
        $this->increment('times_used');
        $this->update(['last_used_at' => now()]);
    }

    public function remainingUses(): int
    {
        return max(0, $this->max_uses - $this->times_used);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function audits()
    {
        return $this->hasMany(Audit::class, 'access_code_id');
    }
}
