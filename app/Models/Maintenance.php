<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Maintenance extends Model
{
    use HasFactory;

    protected $fillable = [
        'advertising_space_id',
        'type',
        'category',
        'status',
        'priority',
        'matrix_data',
        'description',
        'estimated_cost',
        'final_cost'
    ];

    protected $casts = [
        'matrix_data' => 'array',
    ];

    public function advertisingSpace()
    {
        return $this->belongsTo(AdvertisingSpace::class);
    }
}
