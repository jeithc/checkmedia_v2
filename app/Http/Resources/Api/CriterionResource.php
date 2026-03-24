<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CriterionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'key' => $this->key,
            'type' => $this->type,
            'category' => $this->category,
            'order_index' => $this->order_index,
        ];
    }
}
