<?php

use App\Support\Akses\RoleBawaan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Role & hak akses: setiap akun (users) punya satu role. Role menentukan menu yang terlihat,
// aksi per menu (lihat/tambah/ubah/hapus/ekspor), cakupan data, dan boleh login di web /
// aplikasi HP. riwayat_admin mencatat perubahan akun & role oleh admin.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 40)->unique(); // bawaan: super_admin | koordinator | sales | lainnya
            $table->string('nama', 80)->unique();
            $table->string('keterangan')->nullable();
            $table->boolean('bawaan')->default(false);
            $table->string('cakupan', 20)->default('semua'); // semua | depo | downline | diri
            $table->boolean('login_web')->default(true);
            $table->boolean('login_mobile')->default(false);
            $table->timestamps();
        });

        Schema::create('role_hak', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->index();
            $table->string('menu', 80); // slug menu sidebar
            $table->boolean('lihat')->default(false);
            $table->boolean('tambah')->default(false);
            $table->boolean('ubah')->default(false);
            $table->boolean('hapus')->default(false);
            $table->boolean('ekspor')->default(false);
            $table->unique(['role_id', 'menu']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id')->nullable()->index();
        });

        Schema::create('riwayat_admin', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('aktor_id')->nullable();
            $table->string('aktor_nama')->nullable();
            $table->string('objek', 20); // user | role
            $table->unsignedBigInteger('objek_id');
            $table->string('aksi', 40);
            $table->string('keterangan')->nullable();
            $table->timestamp('created_at')->nullable()->index();
            $table->index(['objek', 'objek_id']);
        });

        RoleBawaan::pasang();
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_admin');
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role_id']);
            $table->dropColumn('role_id');
        });
        Schema::dropIfExists('role_hak');
        Schema::dropIfExists('roles');
    }
};
