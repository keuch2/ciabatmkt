<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dashboards', function (Blueprint $table) {
            // Ícono propio subido por el administrador (PNG o SVG) como data: URI. Tiene prioridad sobre `icon`.
            $table->text('icon_data')->nullable()->after('icon');
        });
    }

    public function down(): void
    {
        Schema::table('dashboards', function (Blueprint $table) {
            $table->dropColumn('icon_data');
        });
    }
};
