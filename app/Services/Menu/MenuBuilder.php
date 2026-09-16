<?php

namespace App\Services\Menu;

use App\Models\Dashboard;
use App\Models\Division;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Arma el menú lateral de un usuario: sus dashboards agrupados por división y grupo. Se apoya
 * en Dashboard::visibleTo, así el menú nunca muestra algo que show() rechazaría.
 *
 * Cada dashboard aparece una sola vez: gana la división; el grupo sólo cuando el dashboard no
 * está asignado a la división entera. Los dashboards "toda la empresa" que no están en ninguna
 * división del usuario van en `company`. `unassigned` (sin asignación alguna) sólo para super admin.
 */
class MenuBuilder
{
    public function build(User $user): array
    {
        $dashboards = Dashboard::query()->visibleTo($user)
            ->with(['divisions:divisions.id', 'groups:groups.id,division_id'])
            ->orderBy('title')
            ->get(['dashboards.id', 'slug', 'title', 'icon', 'is_published', 'visible_to_all']);

        $divisions = $user->isSuperAdmin()
            ? Division::query()->with('groups')->orderBy('sort_order')->orderBy('name')->get()
            : $user->divisions()->with('groups')->get();
        $myGroupIds = $user->isSuperAdmin() ? null : $user->groups()->pluck('groups.id')->all();

        $placed = [];
        $out = [];
        foreach ($divisions as $division) {
            $direct = $dashboards->filter(fn ($d) => $d->divisions->contains('id', $division->id));
            $direct->each(function ($d) use (&$placed) { $placed[$d->id] = true; });

            $groups = [];
            foreach ($division->groups as $group) {
                if ($myGroupIds !== null && ! in_array($group->id, $myGroupIds, true)) {
                    continue;
                }
                $viaGroup = $dashboards->filter(fn ($d) => $d->groups->contains('id', $group->id) && ! isset($placed[$d->id]));
                if ($viaGroup->isEmpty()) {
                    continue;
                }
                $viaGroup->each(function ($d) use (&$placed) { $placed[$d->id] = true; });
                $groups[] = ['id' => $group->id, 'name' => $group->name, 'dashboards' => $this->items($viaGroup)];
            }

            if ($direct->isEmpty() && $groups === []) {
                continue;
            }
            $out[] = ['id' => $division->id, 'name' => $division->name, 'dashboards' => $this->items($direct), 'groups' => $groups];
        }

        $company = $dashboards->filter(fn ($d) => $d->visible_to_all && ! isset($placed[$d->id]));
        $company->each(function ($d) use (&$placed) { $placed[$d->id] = true; });

        $unassigned = $user->isSuperAdmin()
            ? $dashboards->filter(fn ($d) => ! isset($placed[$d->id]))
            : collect();

        return ['divisions' => $out, 'company' => $this->items($company), 'unassigned' => $this->items($unassigned)];
    }

    private function items(Collection $dashboards): array
    {
        return $dashboards->map(fn (Dashboard $d) => [
            'id' => $d->id, 'slug' => $d->slug, 'title' => $d->title, 'icon' => $d->icon, 'is_published' => (bool) $d->is_published,
        ])->values()->all();
    }
}
