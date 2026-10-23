<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Kolom login Enesis. Akun diisi dari data aplikasi lama (mk_user + mk_karyawan) lewat
// "php artisan enesis:sinkron-user". Login memakai username karena hampir semua user
// lama tidak punya email; password lama tidak bisa diambil sehingga wajib diganti.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->string('username')->nullable()->unique()->after('name');
            $table->string('kategori', 20)->nullable()->index(); // super_admin | koordinator | sales | lainnya
            $table->unsignedBigInteger('karyawan_id')->nullable()->index(); // mk_karyawan.id
            $table->boolean('aktif')->default(true);
            $table->boolean('wajib_ganti_password')->default(false);
            $table->boolean('perlu_keputusan')->default(false); // kasus yang harus diputuskan admin
            $table->string('catatan_migrasi')->nullable();
            $table->string('sumber_jenis', 20)->nullable(); // mk_user.jenis
            $table->unsignedBigInteger('sumber_id')->nullable(); // mk_user.id
            $table->timestamp('login_terakhir_pada')->nullable();
            $table->unique(['sumber_jenis', 'sumber_id']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['sumber_jenis', 'sumber_id']);
            $table->dropUnique(['username']);
            $table->dropIndex(['kategori']);
            $table->dropIndex(['karyawan_id']);
            $table->dropColumn(['username', 'kategori', 'karyawan_id', 'aktif', 'wajib_ganti_password', 'perlu_keputusan', 'catatan_migrasi', 'sumber_jenis', 'sumber_id', 'login_terakhir_pada']);
        });
    }
};
