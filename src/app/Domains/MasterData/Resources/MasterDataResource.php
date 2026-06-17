<?php

namespace App\Domains\MasterData\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MasterDataResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'type'       => $this->type->value,
            'typeLabel'  => $this->type->label(),
            'name'       => $this->name,
            'code'       => $this->code,
            'isActive'   => $this->is_active,
            'sortOrder'  => $this->sort_order,
            'createdAt'  => $this->created_at,
        ];
    }
}
