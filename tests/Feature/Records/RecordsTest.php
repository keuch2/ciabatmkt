<?php

namespace Tests\Feature\Records;

use App\Models\Dashboard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RecordsTest extends TestCase
{
    use RefreshDatabase;

    private Dashboard $dashboard;

    private User $ana;

    private User $bruno;

    private string $url;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dashboard = Dashboard::factory()->withCollections([
            ['id' => 'solicitudes', 'label' => 'Solicitudes de traslado', 'maxRecords' => 3],
        ])->create();
        $this->ana = User::factory()->create(['name' => 'Ana']);
        $this->bruno = User::factory()->create(['name' => 'Bruno']);
        $this->url = "/api/dashboards/{$this->dashboard->id}/data/solicitudes";
    }

    public function test_any_user_can_create_read_update_and_delete_shared_records(): void
    {
        $this->actingAs($this->ana)->putJson("{$this->url}/ev-1", ['data' => ['evento' => 'EXPO', 'rows' => [['codigo' => '1']]]])
            ->assertOk()
            ->assertJsonPath('record.id', 'ev-1')
            ->assertJsonPath('record.version', 1)
            ->assertJsonPath('record.data.evento', 'EXPO')
            ->assertJsonPath('record.updated_by.name', 'Ana');

        // Bruno ve lo que cargó Ana y lo modifica.
        $list = $this->actingAs($this->bruno)->getJson($this->url)->assertOk()->json();
        $this->assertCount(1, $list['records']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d+$/', $list['server_time']);

        $this->actingAs($this->bruno)->putJson("{$this->url}/ev-1", ['data' => ['evento' => 'EXPO 2'], 'version' => 1])
            ->assertOk()
            ->assertJsonPath('record.version', 2)
            ->assertJsonPath('record.updated_by.name', 'Bruno');

        $this->actingAs($this->ana)->getJson($this->url)->assertJsonPath('records.0.data.evento', 'EXPO 2');

        // Cualquier usuario puede borrar.
        $this->actingAs($this->ana)->deleteJson("{$this->url}/ev-1")->assertOk()->assertJsonPath('deleted', true);
        $this->actingAs($this->ana)->deleteJson("{$this->url}/ev-1")->assertOk()->assertJsonPath('deleted', false);
        $this->assertDatabaseCount('dashboard_records', 0);
    }

    public function test_history_is_written_by_trigger_with_the_real_actor(): void
    {
        $this->actingAs($this->ana)->putJson("{$this->url}/ev-1", ['data' => ['a' => 1]]);
        $this->actingAs($this->bruno)->putJson("{$this->url}/ev-1", ['data' => ['a' => 2]]);
        $this->actingAs($this->bruno)->putJson("{$this->url}/ev-1", ['data' => ['a' => 2]]); // sin cambio real
        $this->actingAs($this->ana)->deleteJson("{$this->url}/ev-1");

        $rows = DB::table('dashboard_record_history')->where('dashboard_id', $this->dashboard->id)->orderBy('changed_at')->get();

        $this->assertSame(['insert', 'update', 'delete'], $rows->pluck('action')->all());
        $this->assertSame([$this->ana->id, $this->bruno->id, $this->ana->id], $rows->pluck('changed_by')->all());
        $this->assertSame([1, 2, 2], $rows->pluck('version')->map(fn ($v) => (int) $v)->all());
        $this->assertSame('{"a": 2}', $rows[2]->old_data);
    }

    public function test_concurrent_edit_is_rejected_with_the_current_version(): void
    {
        $this->actingAs($this->ana)->putJson("{$this->url}/ev-1", ['data' => ['a' => 1]]);
        $this->actingAs($this->bruno)->putJson("{$this->url}/ev-1", ['data' => ['a' => 2], 'version' => 1])->assertOk();

        // Ana sigue con la versión 1 en pantalla.
        $this->actingAs($this->ana)->putJson("{$this->url}/ev-1", ['data' => ['a' => 3], 'version' => 1])
            ->assertStatus(409)
            ->assertJsonPath('code', 'conflict')
            ->assertJsonPath('record.version', 2)
            ->assertJsonPath('record.data.a', 2);

        // Sin versión, la escritura gana igual (última escritura).
        $this->actingAs($this->ana)->putJson("{$this->url}/ev-1", ['data' => ['a' => 3]])->assertOk()->assertJsonPath('record.version', 3);
    }

    public function test_undeclared_collection_and_bad_ids_and_limits_are_rejected(): void
    {
        $this->actingAs($this->ana)->putJson("/api/dashboards/{$this->dashboard->id}/data/otra/x", ['data' => []])
            ->assertUnprocessable()
            ->assertJsonPath('errors.collection.0', 'La colección «otra» no está declarada en el manifiesto de este dashboard. Colecciones declaradas: solicitudes.');

        $this->actingAs($this->ana)->putJson("{$this->url}/con espacios", ['data' => []])->assertUnprocessable()->assertJsonValidationErrors('record_id');
        $this->actingAs($this->ana)->putJson("{$this->url}/x", ['data' => 'texto'])->assertUnprocessable()->assertJsonValidationErrors('data');
        $this->actingAs($this->ana)->putJson("{$this->url}/x", [])->assertUnprocessable()->assertJsonPath('errors.data.0', 'Enviá el campo "data" con el contenido del registro.');

        foreach (['a', 'b', 'c'] as $id) {
            $this->actingAs($this->ana)->putJson("{$this->url}/{$id}", ['data' => ['n' => $id]])->assertOk();
        }
        $this->actingAs($this->ana)->putJson("{$this->url}/d", ['data' => []])
            ->assertUnprocessable()
            ->assertJsonPath('errors.data.0', 'La colección «solicitudes» alcanzó el máximo de 3 registros.');
        // Actualizar uno existente sigue permitido.
        $this->actingAs($this->ana)->putJson("{$this->url}/a", ['data' => ['n' => 'a2']])->assertOk();
    }

    public function test_record_size_limit(): void
    {
        config(['dashboards.max_record_bytes' => 100]);
        $this->app->forgetInstance(\App\Services\Records\RecordStore::class);

        $this->actingAs($this->ana)->putJson("{$this->url}/big", ['data' => ['x' => str_repeat('a', 200)]])
            ->assertUnprocessable()
            ->assertJsonPath('errors.data.0', 'El registro «big» supera el tamaño máximo de 0 KB.');
    }

    public function test_seed_only_fills_an_empty_collection(): void
    {
        $records = [['id' => 'ev-1', 'data' => ['a' => 1]], ['id' => 'ev-2', 'data' => ['a' => 2]]];

        $this->actingAs($this->ana)->postJson("{$this->url}/seed", ['records' => $records])
            ->assertOk()->assertJsonPath('seeded', 2)->assertJsonCount(2, 'records');

        $this->actingAs($this->bruno)->putJson("{$this->url}/ev-1", ['data' => ['a' => 99]]);

        // Un segundo seed (otro usuario abriendo el dashboard) no toca nada.
        $this->actingAs($this->bruno)->postJson("{$this->url}/seed", ['records' => $records])
            ->assertOk()->assertJsonPath('seeded', 0)->assertJsonPath('records.0.data.a', 99);
    }

    public function test_replace_is_admin_only_and_swaps_the_whole_collection(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($this->ana)->putJson("{$this->url}/ev-1", ['data' => ['a' => 1]]);

        $this->actingAs($this->ana)->postJson("{$this->url}/replace", ['records' => []])
            ->assertForbidden()
            ->assertJsonPath('message', 'Sólo un super administrador puede reemplazar los datos de una colección (restaurar un respaldo).');

        $this->actingAs($admin)->postJson("{$this->url}/replace", ['records' => [['id' => 'r-1', 'data' => ['b' => 1]], ['id' => 'r-2', 'data' => ['b' => 2]]]])
            ->assertOk()->assertJsonPath('replaced', 2)->assertJsonCount(2, 'records');

        $this->assertDatabaseMissing('dashboard_records', ['record_id' => 'ev-1']);
        $this->assertSame(['insert', 'delete', 'insert', 'insert'], DB::table('dashboard_record_history')->orderBy('changed_at')->pluck('action')->all());
        $this->assertSame($admin->id, DB::table('dashboard_record_history')->where('action', 'delete')->value('changed_by'));

        $this->actingAs($admin)->postJson("{$this->url}/replace", ['records' => [['id' => 'x', 'data' => []], ['id' => 'x', 'data' => []]]])
            ->assertUnprocessable()->assertJsonPath('errors.records.0', 'El id «x» está repetido en los registros enviados.');
    }

    public function test_changes_since_cursor_include_updates_and_deletions(): void
    {
        $this->actingAs($this->ana)->putJson("{$this->url}/ev-1", ['data' => ['a' => 1]]);
        $this->actingAs($this->ana)->putJson("{$this->url}/ev-2", ['data' => ['a' => 1]]);
        $cursor = $this->actingAs($this->bruno)->getJson($this->url)->json('server_time');

        $this->actingAs($this->bruno)->getJson("{$this->url}/changes?since=".urlencode($cursor))
            ->assertOk()->assertJsonPath('changed', [])->assertJsonPath('deleted', []);

        $this->actingAs($this->ana)->putJson("{$this->url}/ev-1", ['data' => ['a' => 2]]);
        $this->actingAs($this->ana)->deleteJson("{$this->url}/ev-2");
        $this->actingAs($this->ana)->putJson("{$this->url}/ev-3", ['data' => ['a' => 3]]);

        $response = $this->actingAs($this->bruno)->getJson("{$this->url}/changes?since=".urlencode($cursor))->assertOk();

        $this->assertEqualsCanonicalizing(['ev-1', 'ev-3'], array_column($response->json('changed'), 'id'));
        $this->assertSame(['ev-2'], $response->json('deleted'));
        $this->assertSame(2, collect($response->json('changed'))->firstWhere('id', 'ev-1')['version']);

        // Con el cursor nuevo no hay nada pendiente.
        $this->actingAs($this->bruno)->getJson("{$this->url}/changes?since=".urlencode($response->json('server_time')))
            ->assertJsonPath('changed', [])->assertJsonPath('deleted', []);

        $this->actingAs($this->bruno)->getJson("{$this->url}/changes?since=ayer")->assertUnprocessable();
    }

    public function test_unpublished_dashboard_is_hidden_from_users_and_guests_are_rejected(): void
    {
        $draft = Dashboard::factory()->withCollections([['id' => 'c', 'label' => 'C']])->unpublished()->create();

        $this->getJson($this->url)->assertUnauthorized();
        $this->actingAs($this->ana)->getJson("/api/dashboards/{$draft->id}/data/c")->assertNotFound();
        $this->actingAs(User::factory()->superAdmin()->create())->getJson("/api/dashboards/{$draft->id}/data/c")->assertOk();
    }

    public function test_detail_exposes_collections_and_viewer(): void
    {
        $this->actingAs($this->ana)->getJson("/api/dashboards/{$this->dashboard->id}")
            ->assertOk()
            ->assertJsonPath('data.collections.0.id', 'solicitudes')
            ->assertJsonPath('data.viewer', ['id' => $this->ana->id, 'name' => 'Ana', 'role' => 'user']);
    }
}
