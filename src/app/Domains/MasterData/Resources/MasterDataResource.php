<?php

namespace App\Domains\MasterData\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MasterDataResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'typeLabel' => $this->type->label(),
            'name' => $this->name,
            'code' => $this->code,
            'parentId' => $this->parent_id,
            'parentName' => $this->whenLoaded('parent', fn () => $this->parent?->name),
            'positionsCount' => $this->whenCounted('positions'),
            'locationsCount' => $this->whenCounted('locations'),
            'locations' => $this->whenLoaded('locations', fn () => $this->locations->map(fn ($l) => [
                'id' => $l->id,
                'name' => $l->name,
                'parentId' => $l->parent_id,
                'isActive' => $l->is_active,
            ])->values()),
            'childrenCount' => $this->whenCounted('children'),
            'isActive' => $this->is_active,
            'sortOrder' => $this->sort_order,
            'createdAt' => $this->created_at,
        ];
    }
}
