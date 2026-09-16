<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role->value,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
            'divisions' => $this->whenLoaded('divisions', fn () => $this->divisions->map(fn ($d) => ['id' => $d->id, 'name' => $d->name])->values()),
            'groups' => $this->whenLoaded('groups', fn () => $this->groups->map(fn ($g) => ['id' => $g->id, 'name' => $g->name, 'division_id' => $g->division_id])->values()),
        ];
    }
}
