<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'space' => new SpaceResource($this->whenLoaded('space')),
            'user' => new UserResource($this->whenLoaded('user')),
            'year' => $this->year,
            'week' => $this->week,
            'audit_type' => $this->audit_type,
            'audit_purpose' => $this->audit_purpose,
            'audit_date' => $this->audit_date?->toIso8601String(),
            'general_status' => $this->general_status,
            'observation' => $this->observation,
            'source' => $this->source,
            'approval_status' => $this->approval_status,
            'approved_by' => new UserResource($this->whenLoaded('approvedBy')),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'rejection_reason' => $this->rejection_reason,
            'access_code' => $this->when($this->access_code_id, function () {
                return $this->accessCode ? [
                    'id' => $this->accessCode->id,
                    'code' => $this->accessCode->code,
                    'label' => $this->accessCode->label,
                ] : null;
            }),
            'values' => AuditValueResource::collection($this->whenLoaded('values')),
            'photos' => AuditPhotoResource::collection($this->whenLoaded('photos')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
