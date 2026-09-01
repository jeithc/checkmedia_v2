<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class AuditPhoto extends Model
{
    use HasFactory;

    protected $fillable = [
        'audit_id',
        'file_path',
        'file_type',
    ];

    public function getIsPdfAttribute(): bool
    {
        return $this->file_type === 'pdf';
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk('s3')->url($this->file_path);
    }
}
