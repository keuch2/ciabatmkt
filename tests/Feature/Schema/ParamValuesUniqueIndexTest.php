<?php

namespace Tests\Feature\Schema;

use App\Models\Dashboard;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * El índice único con coalesce(user_id) debe permitir una sola fila base por parámetro
 * y una sola fila por usuario, sin que los NULL se cuelen como "distintos".
 */
class ParamValuesUniqueIndexTest extends TestCase
{
    use RefreshDatabase;

    private Dashboard $dashboard;

    private User $editor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dashboard = Dashboard::factory()->create();
        $this->editor = User::factory()->create();
    }

    private function insert(?string $userId, mixed $value = 1): void
    {
        DB::table('param_values')->insert([
            'id' => (string) Str::uuid(),
            'dashboard_id' => $this->dashboard->id,
            'param_id' => 'meta',
            'user_id' => $userId,
            'value' => json_encode($value),
            'updated_by' => $this->editor->id,
        ]);
    }

    public function test_only_one_base_row_per_param(): void
    {
        $this->insert(null);

        $this->expectException(QueryException::class);
        $this->insert(null);
    }

    public function test_only_one_override_per_user_and_param(): void
    {
        $user = User::factory()->create();
        $this->insert($user->id);

        $this->expectException(QueryException::class);
        $this->insert($user->id);
    }

    public function test_base_and_overrides_from_different_users_coexist(): void
    {
        [$a, $b] = User::factory()->count(2)->create();

        $this->insert(null);
        $this->insert($a->id);
        $this->insert($b->id);

        $this->assertSame(3, DB::table('param_values')->where('dashboard_id', $this->dashboard->id)->count());
    }
}
