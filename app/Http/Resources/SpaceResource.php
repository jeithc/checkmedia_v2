<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SpaceResource extends JsonResource
{
    /**
     * Computed metadata set on the instance before returning the resource.
     */
    public array $meta = [];

    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'external_code' => $this->external_code,
            'type' => $this->type,
            'duplicate' => $this->meta['duplicate'] ?? false,
            'existing_audit_id' => $this->meta['existing_audit_id'] ?? null,
            'booking' => $this->meta['booking'] ?? null,
        ];
    }
}
