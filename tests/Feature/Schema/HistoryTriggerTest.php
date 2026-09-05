<?php

namespace Tests\Feature\Schema;

use App\Models\Dashboard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * El historial se llena exclusivamente por triggers. Estos tests escriben en param_values
 * con SQL directo y verifican que param_value_history refleje cada acción.
 */
class HistoryTriggerTest extends TestCase
{
    use RefreshDatabase;

    private Dashboard $dashboard;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dashboard = Dashboard::factory()->create();
        $this->user = User::factory()->create();
        DB::statement('set @ciabay_actor_id = null');
    }

    private function insertValue(mixed $value, ?string $userId = null): string
    {
        $id = (string) Str::uuid();

        DB::table('param_values')->insert([
            'id' => $id,
            'dashboard_id' => $this->dashboard->id,
            'param_id' => 'meta',
            'user_id' => $userId,
            'value' => json_encode($value),
            'updated_by' => $this->user->id,
        ]);

        return $id;
    }

    private function history(): array
    {
        return DB::table('param_value_history')
            ->where('dashboard_id', $this->dashboard->id)
            ->orderBy('changed_at')
            ->get()
            ->map(fn ($row) => [
                'action' => $row->action,
                'old' => json_decode($row->old_value),
                'new' => json_decode($row->new_value),
                'user_id' => $row->user_id,
                'changed_by' => $row->changed_by,
            ])
            ->all();
    }

    public function test_insert_is_recorded_with_null_old_value(): void
    {
        $this->insertValue(100, $this->user->id);

        $this->assertSame([[
            'action' => 'insert',
            'old' => null,
            'new' => 100,
            'user_id' => $this->user->id,
            'changed_by' => $this->user->id,
        ]], $this->history());
    }

    public function test_update_records_old_and_new_values(): void
    {
        $id = $this->insertValue(100, $this->user->id);

        DB::table('param_values')->where('id', $id)->update(['value' => json_encode(250)]);

        $history = $this->history();
        $this->assertCount(2, $history);
        $this->assertSame(['action' => 'update', 'old' => 100, 'new' => 250], array_intersect_key($history[1], array_flip(['action', 'old', 'new'])));
    }

    public function test_update_with_the_same_value_does_not_add_noise(): void
    {
        $id = $this->insertValue(100, $this->user->id);

        DB::table('param_values')->where('id', $id)->update(['value' => json_encode(100)]);

        $this->assertCount(1, $this->history());
    }

    public function test_delete_records_the_reset_and_attributes_it_to_the_session_actor(): void
    {
        $actor = User::factory()->create();
        $id = $this->insertValue('Sucursal Centro', $this->user->id);

        DB::statement('set @ciabay_actor_id = ?', [$actor->id]);
        DB::table('param_values')->where('id', $id)->delete();

        $history = $this->history();
        $this->assertCount(2, $history);
        $this->assertSame('delete', $history[1]['action']);
        $this->assertSame('Sucursal Centro', $history[1]['old']);
        $this->assertNull($history[1]['new']);
        $this->assertSame($actor->id, $history[1]['changed_by']);
    }

    public function test_delete_without_session_actor_falls_back_to_last_editor(): void
    {
        $id = $this->insertValue(true);

        DB::table('param_values')->where('id', $id)->delete();

        $history = $this->history();
        $this->assertSame('delete', $history[1]['action']);
        $this->assertNull($history[1]['user_id'], 'la fila base tiene user_id nulo');
        $this->assertSame($this->user->id, $history[1]['changed_by']);
    }

    public function test_scalar_json_types_survive_the_round_trip(): void
    {
        $this->insertValue(false);
        $this->insertValue('#ff0000', $this->user->id);

        $history = $this->history();
        $this->assertFalse($history[0]['new']);
        $this->assertSame('#ff0000', $history[1]['new']);
    }
}
