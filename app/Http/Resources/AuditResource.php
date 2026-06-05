<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AuditResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'client_uuid' => $this->client_uuid,
            'advertising_space_id' => $this->advertising_space_id,
            'year' => $this->year,
            'week' => $this->week,
            'audit_type' => $this->audit_type,
            'general_status' => $this->general_status,
            'audit_date' => optional($this->audit_date)->toIso8601String(),
        ];
    }
}
