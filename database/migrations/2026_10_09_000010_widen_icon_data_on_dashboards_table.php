<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TEXT admite 65.535 bytes, pero un ícono de 64 KB en base64 ocupa unos 87 KB: el UPDATE fallaba
 * con "Data too long". MEDIUMTEXT sobra para el tope validado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dashboards', function (Blueprint $table) {
            $table->mediumText('icon_data')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('dashboards', function (Blueprint $table) {
            $table->text('icon_data')->nullable()->change();
        });
    }
};
