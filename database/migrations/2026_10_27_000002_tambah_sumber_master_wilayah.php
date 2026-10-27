<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Penanda asal baris master wilayah setelah dicocokkan dengan data resmi (php artisan wilayah:sinkron-resmi):
// sumber = resmi (kode ada di Kemendagri) | tidak_resmi (kode tidak ada) | tambahan (tanpa kode, dari Marketama).
return new class extends Migration
{
    private const TABEL = ['provinsi', 'kota_kabupaten', 'kecamatan', 'kelurahan'];

    public function up(): void
    {
        foreach (self::TABEL as $nama) {
            Schema::table($nama, function (Blueprint $table) {
                $table->string('kode_bps', 13)->nullable();
                $table->string('sumber', 20)->nullable();
                $table->timestamp('sinkron_at')->nullable();
                $table->index('kode');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABEL as $nama) {
            Schema::table($nama, function (Blueprint $table) {
                $table->dropIndex(['kode']);
                $table->dropColumn(['kode_bps', 'sumber', 'sinkron_at']);
            });
        }
    }
};
