<?php

namespace App\Models;

use Database\Factories\DashboardFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dashboard extends Model
{
    /** @use HasFactory<DashboardFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'slug',
        'title',
        'description',
        'version',
        'html',
        'manifest',
        'is_published',
        'icon',
        'visible_to_all',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'manifest' => 'array',
            'is_published' => 'boolean',
            'visible_to_all' => 'boolean',
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

    public function divisions(): BelongsToMany
    {
        return $this->belongsToMany(Division::class)->orderBy('sort_order')->orderBy('name');
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class)->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Única regla de visibilidad. Un super administrador ve todo. Un usuario ve un dashboard si
     * está publicado y es para toda la empresa, o está asignado a una de sus divisiones, o a uno
     * de sus grupos. Sin asignación, nadie lo ve.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        return $query->where('is_published', true)->where(function (Builder $q) use ($user) {
            $q->where('visible_to_all', true)
                ->orWhereHas('divisions.users', fn (Builder $u) => $u->whereKey($user->id))
                ->orWhereHas('groups.users', fn (Builder $u) => $u->whereKey($user->id));
        });
    }

    public function isVisibleTo(User $user): bool
    {
        return $user->isSuperAdmin() || static::query()->whereKey($this->getKey())->visibleTo($user)->exists();
    }

    /**
     * Colecciones de registros declaradas en el manifiesto.
     *
     * @return list<array<string, mixed>>
     */
    public function manifestCollections(): array
    {
        return array_values($this->manifest['collections'] ?? []);
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
