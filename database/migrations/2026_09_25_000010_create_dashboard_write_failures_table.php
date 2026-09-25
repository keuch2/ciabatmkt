<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Escrituras de datos que la plataforma rechazó o que no cambiaron nada. Es la evidencia para el
 * diagnóstico de un dashboard: si un usuario dice "no guarda", acá está el motivo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_write_failures', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('dashboard_id')->constrained('dashboards')->cascadeOnDelete();
            $table->string('collection', 60);
            $table->string('record_id', 100)->nullable();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('operation', 10);
            // invalid (422), conflict (409), forbidden (403), noop (guardado sin cambios)
            $table->string('code', 20);
            $table->string('message', 500);
            $table->unsignedInteger('bytes')->nullable();
            $table->timestamp('created_at', 6)->useCurrent();

            $table->index(['dashboard_id', 'created_at'], 'dashboard_write_failures_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_write_failures');
    }
};
