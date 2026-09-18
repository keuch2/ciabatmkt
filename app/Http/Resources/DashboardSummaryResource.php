<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Dashboard */
class DashboardSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'description' => $this->description,
            'version' => $this->version,
            'is_published' => $this->is_published,
            'icon' => $this->icon,
            'visible_to_all' => (bool) $this->visible_to_all,
            'divisions' => $this->whenLoaded('divisions', fn () => $this->divisions->map(fn ($d) => ['id' => $d->id, 'name' => $d->name])->values()),
            'groups' => $this->whenLoaded('groups', fn () => $this->groups->map(fn ($g) => [
                'id' => $g->id, 'name' => $g->name, 'division_id' => $g->division_id,
                'division_name' => $g->relationLoaded('division') && $g->division ? $g->division->name : null,
            ])->values()),
            'param_count' => count($this->manifestParams()),
            'created_by' => $this->whenLoaded('creator', fn () => ['id' => $this->creator->id, 'name' => $this->creator->name]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
