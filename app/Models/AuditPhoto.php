<?php

namespace App\Models;

use App\Support\MediaStorage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditPhoto extends Model
{
    use HasFactory;

    protected $fillable = [
        'audit_id',
        'file_path',
        'file_type',
    ];

    public function getUrlAttribute(): ?string
    {
        return MediaStorage::url($this->file_path);
    }
}
