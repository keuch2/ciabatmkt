<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('param_value_history', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('dashboard_id')->constrained('dashboards')->cascadeOnDelete();
            $table->string('param_id', 100);
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            // insert = primera escritura, update = cambio de valor, delete = reset del override.
            $table->string('action', 10);
            $table->json('old_value')->nullable();
            $table->json('new_value')->nullable();
            $table->foreignUuid('changed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('changed_at', 6)->useCurrent();

            $table->index(['dashboard_id', 'changed_at'], 'param_history_lookup');
        });

        // La aplicación nunca escribe en esta tabla. Todo entra por estos triggers.
        DB::unprepared(<<<'SQL'
            create trigger param_values_history_ai after insert on param_values for each row
            insert into param_value_history
                (id, dashboard_id, param_id, user_id, action, old_value, new_value, changed_by, changed_at)
            values
                (uuid(), new.dashboard_id, new.param_id, new.user_id, 'insert', null, new.value, new.updated_by, now(6))
        SQL);

        // Sólo registra cambios reales de valor: un PUT repetido con el mismo valor no genera ruido.
        DB::unprepared(<<<'SQL'
            create trigger param_values_history_au after update on param_values for each row
            begin
                if not (old.value = new.value) then
                    insert into param_value_history
                        (id, dashboard_id, param_id, user_id, action, old_value, new_value, changed_by, changed_at)
                    values
                        (uuid(), new.dashboard_id, new.param_id, new.user_id, 'update', old.value, new.value, new.updated_by, now(6));
                end if;
            end
        SQL);

        // El actor del borrado se pasa por la variable de sesión @ciabay_actor_id antes del DELETE.
        // Si no está definida, se atribuye al último que escribió la fila.
        DB::unprepared(<<<'SQL'
            create trigger param_values_history_ad after delete on param_values for each row
            insert into param_value_history
                (id, dashboard_id, param_id, user_id, action, old_value, new_value, changed_by, changed_at)
            values
                (uuid(), old.dashboard_id, old.param_id, old.user_id, 'delete', old.value, null,
                 coalesce(@ciabay_actor_id, old.updated_by), now(6))
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('drop trigger if exists param_values_history_ai');
        DB::unprepared('drop trigger if exists param_values_history_au');
        DB::unprepared('drop trigger if exists param_values_history_ad');
        Schema::dropIfExists('param_value_history');
    }
};
