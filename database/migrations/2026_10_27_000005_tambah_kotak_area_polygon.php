<?php

use App\Support\Area\AreaMk;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Kotak batas (bounding box) tiap area, supaya Peta Sebaran Sales bisa mengambil hanya area yang terlihat di layar
// dari ±13 ribu area. Diisi untuk semua area sekarang, lalu oleh AreaMk::sinkron setiap area disimpan.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('area_polygon', function (Blueprint $table) {
            $table->decimal('min_lng', 10, 7)->nullable();
            $table->decimal('min_lat', 10, 7)->nullable();
            $table->decimal('max_lng', 10, 7)->nullable();
            $table->decimal('max_lat', 10, 7)->nullable();
            $table->index(['min_lat', 'max_lat']);
        });

        DB::table('area_polygon')->orderBy('id')->select('id')->chunk(2000, function ($rows) {
            AreaMk::perbaruiKotak($rows->pluck('id')->all());
        });
    }

    public function down(): void
    {
        Schema::table('area_polygon', function (Blueprint $table) {
            $table->dropIndex(['min_lat', 'max_lat']);
            $table->dropColumn(['min_lng', 'min_lat', 'max_lng', 'max_lat']);
        });
    }
};
