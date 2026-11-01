<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Tanda outlet: catatan kecurigaan (outlet fiktif, alamat tidak sesuai, dll.) yang diberikan koordinator dari drawer
// Riwayat Lacak Motoris atau menu All Outlet. Melekat ke outlet (mk_outlet), bukan per kunjungan/motoris, karena
// kecurigaan terhadap outlet berlaku siapa pun yang mengunjunginya. Pencabutan soft (dicabut_at) supaya jejaknya
// tetap ada; dibuat_nama adalah salinan nama akun pemberi agar jejak tetap terbaca bila akunnya berubah.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tanda_outlet', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('outlet_id');
            $table->string('kategori', 40);
            $table->text('catatan')->nullable();
            $table->unsignedBigInteger('dibuat_oleh');
            $table->string('dibuat_nama');
            $table->timestamp('dicabut_at')->nullable();
            $table->unsignedBigInteger('dicabut_oleh')->nullable();
            $table->timestamps();

            $table->index(['outlet_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tanda_outlet');
    }
};
