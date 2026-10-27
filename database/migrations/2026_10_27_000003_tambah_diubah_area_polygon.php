<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Kolom "Diupdate Oleh / Pada" menu Master Area, kini dibaca langsung dari area_polygon.
// Diisi saat area dibuat/diubah di Design Area, Generate Polygon Kelurahan, dan Auto Grid.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('area_polygon', function (Blueprint $table) {
            $table->string('diubah_oleh')->nullable();
            $table->timestamp('diubah_pada')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('area_polygon', function (Blueprint $table) {
            $table->dropColumn(['diubah_oleh', 'diubah_pada']);
        });
    }
};
