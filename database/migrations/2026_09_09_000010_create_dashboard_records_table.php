<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registros compartidos por dashboard: los datos que los usuarios cargan desde la propia
 * interfaz del dashboard (solicitudes, filas, etc.). Una colección es un nombre declarado en el
 * manifiesto; un registro es un JSON con id propio. El esquema no cambia por dashboard.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('dashboard_id')->constrained('dashboards')->cascadeOnDelete();
            $table->string('collection', 60);
            $table->string('record_id', 100);
            $table->json('data');
            // Sube en cada cambio; el cliente la envía para detectar escrituras concurrentes.
            $table->unsignedInteger('version')->default(1);
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at', 6)->useCurrent()->useCurrentOnUpdate();

            $table->unique(['dashboard_id', 'collection', 'record_id'], 'dashboard_records_unique');
            $table->index(['dashboard_id', 'collection', 'updated_at'], 'dashboard_records_lookup');
        });

        Schema::create('dashboard_record_history', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('dashboard_id')->constrained('dashboards')->cascadeOnDelete();
            $table->string('collection', 60);
            $table->string('record_id', 100);
            $table->string('action', 10);
            $table->json('old_data')->nullable();
            $table->json('new_data')->nullable();
            $table->unsignedInteger('version');
            $table->foreignUuid('changed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('changed_at', 6)->useCurrent();

            $table->index(['dashboard_id', 'collection', 'changed_at'], 'dashboard_record_history_lookup');
        });

        // La aplicación nunca escribe en el historial: todo entra por estos triggers.
        DB::unprepared(<<<'SQL'
            create trigger dashboard_records_history_ai after insert on dashboard_records for each row
            insert into dashboard_record_history
                (id, dashboard_id, collection, record_id, action, old_data, new_data, version, changed_by, changed_at)
            values
                (uuid(), new.dashboard_id, new.collection, new.record_id, 'insert', null, new.data, new.version, new.updated_by, now(6))
        SQL);

        DB::unprepared(<<<'SQL'
            create trigger dashboard_records_history_au after update on dashboard_records for each row
            begin
                if not (old.data = new.data) then
                    insert into dashboard_record_history
                        (id, dashboard_id, collection, record_id, action, old_data, new_data, version, changed_by, changed_at)
                    values
                        (uuid(), new.dashboard_id, new.collection, new.record_id, 'update', old.data, new.data, new.version, new.updated_by, now(6));
                end if;
            end
        SQL);

        // El actor del borrado se pasa por la variable de sesión @ciabay_actor_id antes del DELETE.
        DB::unprepared(<<<'SQL'
            create trigger dashboard_records_history_ad after delete on dashboard_records for each row
            insert into dashboard_record_history
                (id, dashboard_id, collection, record_id, action, old_data, new_data, version, changed_by, changed_at)
            values
                (uuid(), old.dashboard_id, old.collection, old.record_id, 'delete', old.data, null, old.version,
                 coalesce(@ciabay_actor_id, old.updated_by), now(6))
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('drop trigger if exists dashboard_records_history_ai');
        DB::unprepared('drop trigger if exists dashboard_records_history_au');
        DB::unprepared('drop trigger if exists dashboard_records_history_ad');
        Schema::dropIfExists('dashboard_record_history');
        Schema::dropIfExists('dashboard_records');
    }
};
