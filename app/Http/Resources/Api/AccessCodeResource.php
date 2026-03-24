<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccessCodeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'label' => $this->label,
            'max_uses' => $this->max_uses,
            'times_used' => $this->times_used,
            'remaining_uses' => $this->remainingUses(),
            'is_revoked' => $this->is_revoked,
            'is_valid' => $this->isValid(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'last_used_at' => $this->last_used_at?->toIso8601String(),
            'created_by' => new UserResource($this->whenLoaded('createdBy')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
