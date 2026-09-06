<?php

namespace Tests\Feature\Params;

use App\Models\Dashboard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsDashboardHtml;
use Tests\TestCase;

class UserIsolationTest extends TestCase
{
    use BuildsDashboardHtml, RefreshDatabase;

    public function test_two_users_modify_the_same_param_and_each_sees_only_their_value(): void
    {
        $dashboard = Dashboard::factory()->create(['manifest' => $this->fullManifest()]);
        [$ana, $bruno] = User::factory()->count(2)->create();
        $url = "/api/dashboards/{$dashboard->id}";

        $this->actingAs($ana)->putJson("{$url}/params/meta", ['value' => 111])->assertOk();
        $this->actingAs($bruno)->putJson("{$url}/params/meta", ['value' => 222])->assertOk();
        $this->actingAs($ana)->putJson("{$url}/params/titulo", ['value' => 'de Ana'])->assertOk();

        $this->actingAs($ana)->getJson($url)
            ->assertJsonPath('data.params.meta.value', 111)
            ->assertJsonPath('data.params.titulo.value', 'de Ana');

        $this->actingAs($bruno)->getJson($url)
            ->assertJsonPath('data.params.meta.value', 222)
            ->assertJsonPath('data.params.titulo.value', 'Hola')
            ->assertJsonPath('data.params.titulo.source', 'default');

        // Un tercero sin overrides ve los defaults.
        $this->actingAs(User::factory()->create())->getJson($url)
            ->assertJsonPath('data.params.meta.value', 100)
            ->assertJsonPath('data.params.meta.source', 'default');

        $this->assertDatabaseCount('param_values', 3);
    }

    public function test_reset_of_one_user_does_not_touch_the_other(): void
    {
        $dashboard = Dashboard::factory()->create(['manifest' => $this->fullManifest()]);
        [$ana, $bruno] = User::factory()->count(2)->create();
        $url = "/api/dashboards/{$dashboard->id}";

        $this->actingAs($ana)->putJson("{$url}/params/meta", ['value' => 111]);
        $this->actingAs($bruno)->putJson("{$url}/params/meta", ['value' => 222]);

        $this->actingAs($ana)->deleteJson("{$url}/params/meta")->assertOk()->assertJsonPath('data.source', 'default');

        $this->actingAs($bruno)->getJson($url)->assertJsonPath('data.params.meta.value', 222);
        $this->assertDatabaseHas('param_values', ['user_id' => $bruno->id, 'param_id' => 'meta']);
        $this->assertDatabaseMissing('param_values', ['user_id' => $ana->id, 'param_id' => 'meta']);
    }
}
