<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dashboards', function (Blueprint $table) {
            // Clave del set de íconos del menú (App\Support\DashboardIcons). Null = iniciales del título.
            $table->string('icon', 40)->nullable()->after('is_published');
            // "Toda la empresa": visible para cualquier usuario activo sin necesidad de asignación.
            $table->boolean('visible_to_all')->default(false)->after('icon');
        });

        // Carga única: los dashboards que ya existían antes de las divisiones seguían siendo visibles
        // para todos; se conserva ese comportamiento hasta que el administrador los asigne.
        DB::table('dashboards')->update(['visible_to_all' => true]);
    }

    public function down(): void
    {
        Schema::table('dashboards', function (Blueprint $table) {
            $table->dropColumn(['icon', 'visible_to_all']);
        });
    }
};
