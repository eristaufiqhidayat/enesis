<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Tanda motoris: catatan kecurigaan (penjualan fiktif, lokasi palsu, dll.) yang diberikan koordinator dari drawer Riwayat
// Lacak Motoris untuk satu motoris pada satu tanggal. Pencabutan soft (dicabut_at) supaya jejaknya tetap ada;
// dibuat_nama adalah salinan nama akun pemberi agar jejak tetap terbaca bila akunnya berubah.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tanda_motoris', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('karyawan_id');
            $table->date('tanggal');
            $table->string('kategori', 40);
            $table->text('catatan')->nullable();
            $table->unsignedBigInteger('dibuat_oleh');
            $table->string('dibuat_nama');
            $table->timestamp('dicabut_at')->nullable();
            $table->unsignedBigInteger('dicabut_oleh')->nullable();
            $table->timestamps();

            $table->index(['karyawan_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tanda_motoris');
    }
};
