<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Batas wilayah resmi (kota/kabupaten, kecamatan, kelurahan/desa) per kode Kemendagri, untuk
// halaman Generate Polygon Kelurahan. area_polygon ditambah geometri utuh + kode kelurahan sumbernya.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batas_wilayah', function (Blueprint $table) {
            $table->id();
            // Kode Kemendagri tanpa titik, sama dengan kolom kode di tabel kota_kabupaten/kecamatan/kelurahan.
            $table->string('kode', 13)->unique();
            $table->string('tingkat', 10); // kota | kecamatan | kelurahan
            $table->string('nama');
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            // GeoJSON MultiPolygon, koordinat [lng, lat].
            $table->longText('geojson');
            $table->unsignedInteger('jumlah_titik')->default(0);
            $table->decimal('luas_km2', 12, 4)->nullable();
            $table->string('sumber', 30);
            $table->timestamps();
            $table->index(['tingkat', 'kode']);
        });

        Schema::table('area_polygon', function (Blueprint $table) {
            // GeoJSON MultiPolygon lengkap (semua pulau + lubang); kolom polygon tetap satu cincin terluas.
            $table->longText('geometri')->nullable();
            $table->string('kode_kelurahan', 13)->nullable()->index();
            $table->string('sumber_polygon', 30)->nullable();
            $table->index('village_id');
        });
    }

    public function down(): void
    {
        Schema::table('area_polygon', function (Blueprint $table) {
            $table->dropIndex(['village_id']);
            $table->dropIndex(['kode_kelurahan']);
            $table->dropColumn(['geometri', 'kode_kelurahan', 'sumber_polygon']);
        });
        Schema::dropIfExists('batas_wilayah');
    }
};
