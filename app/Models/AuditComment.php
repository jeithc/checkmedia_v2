<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditComment extends Model
{
    use HasFactory;

    protected $fillable = [
        'audit_id',
        'user_id',
        'message',
        'type',
    ];

    public function audit()
    {
        return $this->belongsTo(Audit::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
