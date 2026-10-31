<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Data KTP karyawan sales (motoris) & kategori lain: diisi manual atau otomatis lewat pembacaan foto KTP
// (App\Support\Karyawan\BacaKtp, endpoint "{menu}/baca-ktp"). Foto KTP sendiri tidak disimpan, hanya
// dipakai sebentar untuk OCR lalu dibuang — yang disimpan cuma hasil bacaannya (lihat KaryawanController).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mk_karyawan', function (Blueprint $table) {
            $table->string('nik', 16)->nullable()->unique()->after('nama');
            $table->string('tempat_lahir', 100)->nullable()->after('nik');
            $table->date('tanggal_lahir')->nullable()->after('tempat_lahir');
            $table->string('alamat', 500)->nullable()->after('tanggal_lahir');
        });
    }

    public function down(): void
    {
        Schema::table('mk_karyawan', function (Blueprint $table) {
            $table->dropColumn(['nik', 'tempat_lahir', 'tanggal_lahir', 'alamat']);
        });
    }
};
