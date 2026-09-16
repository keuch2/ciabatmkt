<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Divisiones (sectores de negocio) y grupos dentro de cada división. Organizan usuarios y
 * dashboards: un dashboard se ve si está asignado a una división o a un grupo del usuario.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('divisions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 80)->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // "groups" es palabra reservada en MySQL 8: Laravel la escapa; cualquier SQL crudo debe usar backticks.
        Schema::create('groups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('division_id')->constrained('divisions')->cascadeOnDelete();
            $table->string('name', 80);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['division_id', 'name']);
        });

        Schema::create('division_user', function (Blueprint $table) {
            $table->foreignUuid('division_id')->constrained('divisions')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->primary(['division_id', 'user_id']);
        });

        Schema::create('group_user', function (Blueprint $table) {
            $table->foreignUuid('group_id')->constrained('groups')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->primary(['group_id', 'user_id']);
        });

        Schema::create('dashboard_division', function (Blueprint $table) {
            $table->foreignUuid('dashboard_id')->constrained('dashboards')->cascadeOnDelete();
            $table->foreignUuid('division_id')->constrained('divisions')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->primary(['dashboard_id', 'division_id']);
        });

        Schema::create('dashboard_group', function (Blueprint $table) {
            $table->foreignUuid('dashboard_id')->constrained('dashboards')->cascadeOnDelete();
            $table->foreignUuid('group_id')->constrained('groups')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->primary(['dashboard_id', 'group_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_group');
        Schema::dropIfExists('dashboard_division');
        Schema::dropIfExists('group_user');
        Schema::dropIfExists('division_user');
        Schema::dropIfExists('groups');
        Schema::dropIfExists('divisions');
    }
};
