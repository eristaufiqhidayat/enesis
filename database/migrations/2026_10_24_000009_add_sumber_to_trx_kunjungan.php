<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Kunjungan outlet dari aplikasi lama (lap_checkin_outlet) dimigrasi ke trx_kunjungan.
// foto_url = link foto di server aplikasi lama; kategori_lama/keterangan_lama = kolom category
// dan description apa adanya (arti kodenya tidak diketahui, tidak dipetakan ke alasan tidak beli).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trx_kunjungan', function (Blueprint $table) {
            $table->string('sumber', 10)->default('motoris')->index();
            $table->unsignedBigInteger('sumber_id')->nullable()->unique();
            $table->string('foto_url')->nullable();
            $table->bigInteger('kategori_lama')->nullable();
            $table->string('keterangan_lama')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('trx_kunjungan', function (Blueprint $table) {
            $table->dropUnique('trx_kunjungan_sumber_id_unique');
            $table->dropIndex('trx_kunjungan_sumber_index');
            $table->dropColumn(['sumber', 'sumber_id', 'foto_url', 'kategori_lama', 'keterangan_lama']);
        });
    }
};
