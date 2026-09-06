<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ParamValueHistory */
class HistoryEntryResource extends JsonResource
{
    /** @param  array<string, string>  $labels  param_id => label del manifiesto vigente */
    public function __construct($resource, private readonly array $labels = [])
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'param_id' => $this->param_id,
            'label' => $this->labels[$this->param_id] ?? null,
            'scope' => $this->user_id === null ? 'base' : 'user',
            'user' => $this->whenLoaded('user', fn () => $this->user ? ['id' => $this->user->id, 'name' => $this->user->name] : null),
            'action' => $this->action,
            'old_value' => $this->old_value,
            'new_value' => $this->new_value,
            'changed_by' => $this->whenLoaded('actor', fn () => $this->actor ? ['id' => $this->actor->id, 'name' => $this->actor->name] : null),
            'changed_at' => $this->changed_at?->toIso8601String(),
        ];
    }
}
