<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditPhoto extends Model
{
    use HasFactory;

    protected $fillable = [
        'audit_id',
        'file_path',
        'file_type'
    ];
}
