<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Valor almacenado de un parámetro. user_id null = valor base; con valor = override personal.
 * Las escrituras van por App\Services\Params\ParamWriter (upsert crudo), no por save().
 */
class ParamValue extends Model
{
    use HasUuids;

    public const NIL_UUID = '00000000-0000-0000-0000-000000000000';

    public const CREATED_AT = null;

    protected $fillable = [
        'dashboard_id',
        'param_id',
        'user_id',
        'value',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'json',
            'updated_at' => 'datetime',
        ];
    }

    public function dashboard(): BelongsTo
    {
        return $this->belongsTo(Dashboard::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function isBase(): bool
    {
        return $this->user_id === null;
    }
}
