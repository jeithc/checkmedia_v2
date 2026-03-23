<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommercialBooking extends Model
{
    use HasFactory;

    protected $fillable = [
        'advertising_space_id',
        'year',
        'week',
        'client_name',
        'contract_code',
        'product_name',
    ];

    public function advertisingSpace()
    {
        return $this->belongsTo(AdvertisingSpace::class);
    }
}
