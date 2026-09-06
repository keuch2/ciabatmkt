<?php

namespace Tests\Feature\Params;

use App\Models\Dashboard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsDashboardHtml;
use Tests\TestCase;

class OutOfRangeRejectedTest extends TestCase
{
    use BuildsDashboardHtml, RefreshDatabase;

    private Dashboard $dashboard;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dashboard = Dashboard::factory()->create(['manifest' => $this->fullManifest()]);
        $this->user = User::factory()->create();
    }

    private function putValue(string $paramId, mixed $value)
    {
        return $this->actingAs($this->user)->putJson("/api/dashboards/{$this->dashboard->id}/params/{$paramId}", ['value' => $value]);
    }

    public function test_number_above_max_is_rejected_with_the_range(): void
    {
        $this->putValue('meta', 5000)
            ->assertUnprocessable()
            ->assertJsonPath('errors.value.0', 'El valor de «meta» debe estar entre 0 y 1000.');

        $this->assertDatabaseCount('param_values', 0);
    }

    public function test_wrong_types_are_rejected(): void
    {
        $this->putValue('meta', '500')->assertUnprocessable()->assertJsonPath('errors.value.0', 'El valor de «meta» debe ser un número.');
        $this->putValue('activo', 'true')->assertUnprocessable()->assertJsonPath('errors.value.0', 'El valor de «activo» debe ser verdadero o falso.');
        $this->putValue('titulo', 123)->assertUnprocessable()->assertJsonPath('errors.value.0', 'El valor de «titulo» debe ser un texto.');
    }

    public function test_select_text_date_and_color_constraints(): void
    {
        $this->putValue('periodo', 'z')->assertUnprocessable()->assertJsonPath('errors.value.0', 'El valor de «periodo» debe ser una de las opciones: "a", "b".');
        $this->putValue('titulo', str_repeat('x', 21))->assertUnprocessable()->assertJsonPath('errors.value.0', 'El valor de «titulo» no puede superar 20 caracteres.');
        $this->putValue('corte', '2027-01-01')->assertUnprocessable()->assertJsonPath('errors.value.0', 'La fecha de «corte» no puede ser posterior a 2026-12-31.');
        $this->putValue('color', 'azul')->assertUnprocessable()->assertJsonPath('errors.value.0', 'El valor de «color» debe ser un color en formato #RRGGBB.');
        $this->putValue('descuento', -5)->assertUnprocessable()->assertJsonPath('errors.value.0', 'El valor de «descuento» debe estar entre 0 y 50.');
    }

    public function test_unknown_param_is_rejected_listing_the_available_ones(): void
    {
        $this->putValue('inexistente', 1)
            ->assertUnprocessable()
            ->assertJsonPath('errors.param_id.0', 'El parámetro «inexistente» no existe en la versión 1.0.0 de este dashboard. Parámetros disponibles: meta, titulo, activo, periodo, descuento, corte, color.');
    }

    public function test_missing_value_field_is_rejected_but_falsy_values_are_accepted(): void
    {
        $this->actingAs($this->user)
            ->putJson("/api/dashboards/{$this->dashboard->id}/params/meta", [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.value.0', 'Enviá el campo "value" con el nuevo valor del parámetro.');

        $this->putValue('activo', false)->assertOk()->assertJsonPath('data.value', false);
        $this->putValue('meta', 0)->assertOk()->assertJsonPath('data.value', 0);
        $this->putValue('titulo', '')->assertOk()->assertJsonPath('data.value', '');
    }

    public function test_invalid_scope_is_rejected(): void
    {
        $this->actingAs($this->user)
            ->putJson("/api/dashboards/{$this->dashboard->id}/params/meta", ['value' => 1, 'scope' => 'global'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.scope.0', 'El campo "scope" debe ser "user" (tu override) o "base" (valor para todos).');
    }
}
