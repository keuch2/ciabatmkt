<?php

namespace App\Models;

use Database\Factories\DashboardFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dashboard extends Model
{
    /** @use HasFactory<DashboardFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'slug',
        'title',
        'version',
        'html',
        'manifest',
        'is_published',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'manifest' => 'array',
            'is_published' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function paramValues(): HasMany
    {
        return $this->hasMany(ParamValue::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(ParamValueHistory::class);
    }

    /**
     * Parámetros declarados en el manifiesto, en el orden en que aparecen.
     *
     * @return list<array<string, mixed>>
     */
    public function manifestParams(): array
    {
        return array_values($this->manifest['params'] ?? []);
    }
}
