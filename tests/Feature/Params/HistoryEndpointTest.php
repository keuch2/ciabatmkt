<?php

namespace Tests\Feature\Params;

use App\Models\Dashboard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsDashboardHtml;
use Tests\TestCase;

class HistoryEndpointTest extends TestCase
{
    use BuildsDashboardHtml, RefreshDatabase;

    public function test_history_is_written_by_trigger_and_scoped_to_the_user(): void
    {
        $dashboard = Dashboard::factory()->create(['manifest' => $this->fullManifest()]);
        [$ana, $bruno] = User::factory()->count(2)->create();
        $url = "/api/dashboards/{$dashboard->id}";

        $this->actingAs($ana)->putJson("{$url}/params/meta", ['value' => 10]);
        $this->actingAs($ana)->putJson("{$url}/params/meta", ['value' => 20]);
        $this->actingAs($ana)->putJson("{$url}/params/meta", ['value' => 20]); // sin cambio real: no genera fila
        $this->actingAs($ana)->deleteJson("{$url}/params/meta");
        $this->actingAs($bruno)->putJson("{$url}/params/meta", ['value' => 99]);

        $response = $this->actingAs($ana)->getJson("{$url}/history");

        $response->assertOk()->assertJsonCount(3, 'data')->assertJsonPath('meta.total', 3);
        $entries = $response->json('data');

        $this->assertSame(['delete', 'update', 'insert'], array_column($entries, 'action'));
        $this->assertSame([20, 10, null], array_column($entries, 'old_value'));
        $this->assertSame([null, 20, 10], array_column($entries, 'new_value'));
        $this->assertSame('Meta', $entries[0]['label']);
        $this->assertSame('user', $entries[0]['scope']);
        $this->assertSame($ana->id, $entries[0]['changed_by']['id'], 'el reset se atribuye a quien lo hizo');
        $this->assertSame($ana->id, $entries[0]['user']['id']);

        $this->actingAs($bruno)->getJson("{$url}/history")->assertJsonCount(1, 'data')->assertJsonPath('data.0.new_value', 99);
    }

    public function test_history_can_be_filtered_by_param(): void
    {
        $dashboard = Dashboard::factory()->create(['manifest' => $this->fullManifest()]);
        $user = User::factory()->create();
        $url = "/api/dashboards/{$dashboard->id}";

        $this->actingAs($user)->putJson("{$url}/params/meta", ['value' => 10]);
        $this->actingAs($user)->putJson("{$url}/params/titulo", ['value' => 'x']);

        $this->actingAs($user)->getJson("{$url}/history?param_id=titulo")
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.param_id', 'titulo');
    }

    public function test_base_value_changes_do_not_appear_in_user_history(): void
    {
        $dashboard = Dashboard::factory()->create(['manifest' => $this->fullManifest()]);
        $admin = User::factory()->superAdmin()->create();
        $user = User::factory()->create();

        $this->actingAs($admin)->putJson("/api/dashboards/{$dashboard->id}/params/meta", ['value' => 10, 'scope' => 'base']);

        $this->actingAs($user)->getJson("/api/dashboards/{$dashboard->id}/history")->assertJsonCount(0, 'data');
    }
}
