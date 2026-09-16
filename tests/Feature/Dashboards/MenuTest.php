<?php

namespace Tests\Feature\Dashboards;

use App\Models\Dashboard;
use App\Models\Division;
use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_menu_groups_dashboards_by_division_and_group_without_duplicates(): void
    {
        $d1 = Division::factory()->create(['name' => 'Comercial', 'sort_order' => 0]);
        $d2 = Division::factory()->create(['name' => 'Finanzas', 'sort_order' => 1]);
        $g2 = Group::factory()->create(['division_id' => $d2->id, 'name' => 'Tesorería']);
        $g1 = Group::factory()->create(['division_id' => $d1->id, 'name' => 'Sucursales']);

        $a = Dashboard::factory()->restricted()->create(['title' => 'A ventas', 'icon' => 'cart']);
        $a->divisions()->sync([$d1->id]);
        $b = Dashboard::factory()->restricted()->create(['title' => 'B caja']);
        $b->groups()->sync([$g2->id]);
        $both = Dashboard::factory()->restricted()->create(['title' => 'C ambos']);
        $both->divisions()->sync([$d1->id]);
        $both->groups()->sync([$g1->id]);
        $company = Dashboard::factory()->create(['title' => 'D empresa']);
        $hidden = Dashboard::factory()->restricted()->create(['title' => 'E oculto']);
        Dashboard::factory()->restricted()->unpublished()->create(['title' => 'F borrador'])->divisions()->sync([$d1->id]);

        $user = User::factory()->create();
        $user->syncMemberships([$d1->id, $d2->id], [$g1->id, $g2->id]);

        $menu = $this->actingAs($user)->getJson('/api/menu')->assertOk()->json('data');

        $this->assertSame(['Comercial', 'Finanzas'], array_column($menu['divisions'], 'name'));
        $this->assertSame(['A ventas', 'C ambos'], array_column($menu['divisions'][0]['dashboards'], 'title'));
        $this->assertSame('cart', $menu['divisions'][0]['dashboards'][0]['icon']);
        $this->assertSame([], $menu['divisions'][0]['groups'], 'C ambos ya está en la división: no se repite en el grupo');
        $this->assertSame([], $menu['divisions'][1]['dashboards']);
        $this->assertSame('Tesorería', $menu['divisions'][1]['groups'][0]['name']);
        $this->assertSame(['B caja'], array_column($menu['divisions'][1]['groups'][0]['dashboards'], 'title'));
        $this->assertSame(['D empresa'], array_column($menu['company'], 'title'));
        $this->assertSame([], $menu['unassigned']);

        $admin = User::factory()->superAdmin()->create();
        $adminMenu = $this->actingAs($admin)->getJson('/api/menu')->assertOk()->json('data');
        $this->assertSame(['E oculto'], array_column($adminMenu['unassigned'], 'title'));
        $this->assertContains('F borrador', array_column($adminMenu['divisions'][0]['dashboards'], 'title'));
        $this->assertFalse(collect($adminMenu['divisions'][0]['dashboards'])->firstWhere('title', 'F borrador')['is_published']);
    }

    public function test_user_without_memberships_gets_only_company_dashboards(): void
    {
        Dashboard::factory()->create(['title' => 'Empresa']);
        Dashboard::factory()->restricted()->create(['title' => 'Oculto']);

        $menu = $this->actingAs(User::factory()->create())->getJson('/api/menu')->assertOk()->json('data');

        $this->assertSame([], $menu['divisions']);
        $this->assertSame(['Empresa'], array_column($menu['company'], 'title'));
        $this->assertSame([], $menu['unassigned']);
    }

    public function test_guest_cannot_read_menu(): void
    {
        $this->getJson('/api/menu')->assertUnauthorized();
    }
}
