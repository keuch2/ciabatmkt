<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUuids, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === UserRole::SuperAdmin;
    }

    public function paramValues(): HasMany
    {
        return $this->hasMany(ParamValue::class);
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
     * Sincroniza divisiones y grupos. Un grupo cuya división no quedó asignada se descarta:
     * los grupos de un usuario siempre pertenecen a alguna de sus divisiones.
     *
     * @param  list<string>  $divisionIds
     * @param  list<string>  $groupIds
     */
    public function syncMemberships(array $divisionIds, array $groupIds): void
    {
        DB::transaction(function () use ($divisionIds, $groupIds) {
            $this->divisions()->sync($divisionIds);
            $allowed = Group::query()->whereIn('id', $groupIds)->whereIn('division_id', $divisionIds)->pluck('id')->all();
            $this->groups()->sync($allowed);
        });
    }
}
