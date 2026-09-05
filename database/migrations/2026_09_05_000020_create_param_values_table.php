<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * UUID nulo usado como comodín para que el índice único trate a las filas base
     * (user_id null) como una sola fila por dashboard y parámetro.
     */
    public const NIL_UUID = '00000000-0000-0000-0000-000000000000';

    public function up(): void
    {
        Schema::create('param_values', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('dashboard_id')->constrained('dashboards')->cascadeOnDelete();
            $table->string('param_id', 100);
            // null = valor base definido por el super administrador; con valor = override personal.
            $table->foreignUuid('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            // Escalar JSON crudo: 500000000, true, "Sucursal Centro". Sin objeto envolvente.
            $table->json('value');
            $table->foreignUuid('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index(['dashboard_id', 'user_id'], 'param_values_lookup');
        });

        // MySQL no permite NULLS NOT DISTINCT; el coalesce sobre una parte funcional del índice
        // hace que sólo pueda existir una fila base por (dashboard, parámetro).
        DB::statement(sprintf(
            "create unique index param_values_unique on param_values (dashboard_id, param_id, (coalesce(user_id, '%s')))",
            self::NIL_UUID,
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('param_values');
    }
};
