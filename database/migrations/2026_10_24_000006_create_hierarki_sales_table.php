<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Koordinator sales yang diatur dari Enesis. Menimpa mk_karyawan.koordinator_id dan tetap
// dipasang ulang setiap kali marketama:tarik memperbarui data karyawan dari aplikasi lama.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hierarki_sales', function (Blueprint $table) {
            $table->unsignedBigInteger('karyawan_id')->primary();
            $table->unsignedBigInteger('koordinator_id')->nullable()->index(); // null = tanpa koordinator
            $table->unsignedBigInteger('diubah_oleh')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hierarki_sales');
    }
};
