<?php

namespace Tests\Feature\Params;

use App\Models\Dashboard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsDashboardHtml;
use Tests\TestCase;

class BaseScopeDeniedToUserTest extends TestCase
{
    use BuildsDashboardHtml, RefreshDatabase;

    private Dashboard $dashboard;

    private string $url;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dashboard = Dashboard::factory()->create(['manifest' => $this->fullManifest()]);
        $this->url = "/api/dashboards/{$this->dashboard->id}";
    }

    public function test_regular_user_cannot_write_base_values(): void
    {
        $this->actingAs(User::factory()->create())
            ->putJson("{$this->url}/params/meta", ['value' => 500, 'scope' => 'base'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Sólo un super administrador puede definir valores base. Usá scope "user" para guardar tu propio valor.');

        $this->assertDatabaseCount('param_values', 0);
    }

    public function test_regular_user_cannot_delete_base_values(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin)->putJson("{$this->url}/params/meta", ['value' => 500, 'scope' => 'base']);

        $this->actingAs(User::factory()->create())->deleteJson("{$this->url}/params/meta?scope=base")->assertForbidden();
        $this->actingAs(User::factory()->create())->deleteJson("{$this->url}/params?scope=base")->assertForbidden();

        $this->assertDatabaseHas('param_values', ['param_id' => 'meta', 'user_id' => null]);
    }

    public function test_super_admin_writes_base_rows_with_null_user_id(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->putJson("{$this->url}/params/meta", ['value' => 500, 'scope' => 'base'])->assertOk();

        $this->assertDatabaseHas('param_values', ['param_id' => 'meta', 'user_id' => null, 'updated_by' => $admin->id]);

        // El super administrador también puede tener su propio override, separado del base.
        $this->actingAs($admin)->putJson("{$this->url}/params/meta", ['value' => 900, 'scope' => 'user'])->assertOk();
        $this->assertDatabaseCount('param_values', 2);

        $this->actingAs($admin)->deleteJson("{$this->url}/params/meta?scope=base")->assertOk()->assertJsonPath('data.source', 'default');
        $this->assertDatabaseMissing('param_values', ['param_id' => 'meta', 'user_id' => null]);
    }
}
