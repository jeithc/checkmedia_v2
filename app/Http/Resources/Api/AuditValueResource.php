<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditValueResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'criterion_id' => $this->audit_criterion_id,
            'criterion' => new CriterionResource($this->whenLoaded('criterion')),
            'value' => $this->value,
        ];
    }
}
