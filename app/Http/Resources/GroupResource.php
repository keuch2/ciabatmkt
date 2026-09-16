<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Group */
class GroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'division_id' => $this->division_id,
            'name' => $this->name,
            'sort_order' => $this->sort_order,
            'users_count' => $this->whenCounted('users'),
            'dashboards_count' => $this->whenCounted('dashboards'),
        ];
    }
}
