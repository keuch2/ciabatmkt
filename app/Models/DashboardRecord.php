<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un registro de una colección de un dashboard. Se escribe por App\Services\Records\RecordStore.
 */
class DashboardRecord extends Model
{
    use HasUuids;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            // 'object' y no 'array': un {} vacío debe volver como {} al dashboard (como array se convierte en []).
            'data' => 'object',
            'version' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function dashboard(): BelongsTo
    {
        return $this->belongsTo(Dashboard::class);
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** @return array{id: string, data: array, version: int, updated_at: ?string, updated_by: ?array{id: string, name: string}} */
    public function toRecordArray(): array
    {
        return [
            'id' => $this->record_id,
            'data' => $this->data,
            'version' => $this->version,
            'updated_at' => $this->updated_at?->toIso8601String(),
            'updated_by' => $this->relationLoaded('editor') && $this->editor ? ['id' => $this->editor->id, 'name' => $this->editor->name] : null,
        ];
    }
}
