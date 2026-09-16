<?php

namespace Tests\Feature\Dashboards;

use App\Models\Dashboard;
use App\Models\Division;
use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsDashboardHtml;
use Tests\TestCase;

class VisibilityTest extends TestCase
{
    use BuildsDashboardHtml, RefreshDatabase;

    private Division $division;

    private Group $group;

    private User $member;

    private User $groupMember;

    private User $outsider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->division = Division::factory()->create(['name' => 'Comercial']);
        $this->group = Group::factory()->create(['division_id' => $this->division->id, 'name' => 'Sucursales']);
        $this->member = User::factory()->create();
        $this->member->syncMemberships([$this->division->id], []);
        $this->groupMember = User::factory()->create();
        $this->groupMember->syncMemberships([$this->division->id], [$this->group->id]);
        $this->outsider = User::factory()->create();
    }

    private function ids(User $user): array
    {
        return array_column($this->actingAs($user)->getJson('/api/dashboards')->assertOk()->json('data'), 'id');
    }

    public function test_dashboard_assigned_to_a_division_is_visible_to_its_members_only(): void
    {
        $dashboard = Dashboard::factory()->restricted()->create();
        $dashboard->divisions()->sync([$this->division->id]);

        $this->assertSame([$dashboard->id], $this->ids($this->member));
        $this->assertSame([$dashboard->id], $this->ids($this->groupMember));
        $this->assertSame([], $this->ids($this->outsider));

        $this->actingAs($this->member)->getJson("/api/dashboards/{$dashboard->id}")->assertOk();
        $this->actingAs($this->outsider)->getJson("/api/dashboards/{$dashboard->id}")
            ->assertNotFound()->assertJsonPath('message', 'El dashboard solicitado no existe o no está publicado.');
    }

    public function test_dashboard_assigned_only_to_a_group_is_visible_to_group_members_only(): void
    {
        $dashboard = Dashboard::factory()->restricted()->create();
        $dashboard->groups()->sync([$this->group->id]);

        $this->assertSame([$dashboard->id], $this->ids($this->groupMember));
        $this->assertSame([], $this->ids($this->member), 'estar en la división no alcanza si el dashboard es de un grupo');
        $this->actingAs($this->member)->getJson("/api/dashboards/{$dashboard->id}")->assertNotFound();
    }

    public function test_unassigned_dashboard_is_only_for_super_admins_and_visible_to_all_for_everyone(): void
    {
        $hidden = Dashboard::factory()->restricted()->create();
        $company = Dashboard::factory()->create(); // visible_to_all por defecto en la factory

        $this->assertSame([$company->id], $this->ids($this->outsider));
        $this->assertSame([$company->id], $this->ids($this->member));

        $admin = User::factory()->superAdmin()->create();
        $this->assertEqualsCanonicalizing([$hidden->id, $company->id], $this->ids($admin));
        $this->actingAs($admin)->getJson("/api/dashboards/{$hidden->id}")->assertOk();
    }

    public function test_drafts_are_hidden_from_members_but_not_from_super_admins(): void
    {
        $draft = Dashboard::factory()->restricted()->unpublished()->create();
        $draft->divisions()->sync([$this->division->id]);

        $this->assertSame([], $this->ids($this->member));
        $this->actingAs($this->member)->getJson("/api/dashboards/{$draft->id}")->assertNotFound();
        $this->actingAs(User::factory()->superAdmin()->create())->getJson("/api/dashboards/{$draft->id}")->assertOk();
    }

    public function test_every_dashboard_endpoint_enforces_the_same_rule(): void
    {
        $dashboard = Dashboard::factory()->restricted()->withCollections([['id' => 'c', 'label' => 'C']])->create(['manifest' => $this->fullManifest(['collections' => [['id' => 'c', 'label' => 'C']]])]);
        $dashboard->divisions()->sync([$this->division->id]);
        $url = "/api/dashboards/{$dashboard->id}";

        $this->actingAs($this->outsider)->putJson("{$url}/params/meta", ['value' => 1])->assertNotFound();
        $this->actingAs($this->outsider)->getJson("{$url}/history")->assertNotFound();
        $this->actingAs($this->outsider)->getJson("{$url}/data/c")->assertNotFound();
        $this->actingAs($this->outsider)->putJson("{$url}/data/c/x", ['data' => []])->assertNotFound();

        $this->actingAs($this->member)->putJson("{$url}/params/meta", ['value' => 1])->assertOk();
        $this->actingAs($this->member)->getJson("{$url}/data/c")->assertOk();
    }

    public function test_removing_a_user_from_the_division_hides_the_dashboard(): void
    {
        $dashboard = Dashboard::factory()->restricted()->create();
        $dashboard->divisions()->sync([$this->division->id]);
        $this->assertSame([$dashboard->id], $this->ids($this->member));

        $this->member->syncMemberships([], []);
        $this->assertSame([], $this->ids($this->member));
    }
}
