<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditCriterion extends Model
{
    use HasFactory;

    protected $table = 'audit_criteria'; // Non-standard plural

    protected $fillable = [
        'name',
        'key',
        'type',
        'is_active',
        'order_index'
    ];
}
