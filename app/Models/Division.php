<?php

namespace App\Models;

use Database\Factories\DivisionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Sector de negocio. Organiza usuarios y dashboards; contiene grupos. */
class Division extends Model
{
    /** @use HasFactory<DivisionFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['name', 'sort_order'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    public function groups(): HasMany
    {
        return $this->hasMany(Group::class)->orderBy('sort_order')->orderBy('name');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function dashboards(): BelongsToMany
    {
        return $this->belongsToMany(Dashboard::class);
    }

    /** Dashboards asignados a la división o a cualquiera de sus grupos. */
    public function assignedDashboardsCount(): int
    {
        return Dashboard::query()
            ->where(fn ($q) => $q
                ->whereHas('divisions', fn ($d) => $d->whereKey($this->id))
                ->orWhereHas('groups', fn ($g) => $g->where('division_id', $this->id)))
            ->count();
    }
}
