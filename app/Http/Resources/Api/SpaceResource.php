<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SpaceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'external_code' => $this->external_code,
            'provider' => $this->provider,
            'type' => $this->type,
            'category' => $this->category,
            'ownership' => $this->ownership,
            'illumination_type' => $this->illumination_type,
            'city' => $this->city,
            'location_name' => $this->location_name,
            'address' => $this->address,
            'zone' => $this->zone,
            'location' => $this->location,
            'is_third_party' => (bool) $this->is_third_party,
            'booking' => new BookingResource($this->whenLoaded('currentBooking')),
        ];
    }
}
