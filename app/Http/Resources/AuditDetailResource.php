<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AuditDetailResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'advertising_space_id' => $this->advertising_space_id,
            'year' => $this->year,
            'week' => $this->week,
            'audit_type' => $this->audit_type,
            'audit_purpose' => $this->audit_purpose,
            'general_status' => $this->general_status,
            'observation' => $this->observation,
            'audit_date' => optional($this->audit_date)->toIso8601String(),
            'has_open_maintenance' => $this->hasOpenMaintenance(),
            'values' => $this->values->map(fn ($v) => [
                'criterion_id' => $v->audit_criterion_id,
                'name' => $v->criterion?->name,
                'key' => $v->criterion?->key,
                'value' => $v->value,
                'comment' => $v->comment,
            ])->values(),
            'photos' => $this->photos->map(fn ($p) => [
                'id' => $p->id,
                'url' => $p->url,
                'file_type' => $p->file_type,
            ])->values(),
        ];
    }
}
