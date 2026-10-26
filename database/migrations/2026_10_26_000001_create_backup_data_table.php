<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Riwayat backup data per tabel (menu Master Data & Pengaturan › Backup Data). File hasil disimpan di
// storage/app/private/backup dan dihapus otomatis setelah kedaluwarsa (enesis:backup-bersihkan).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_data', function (Blueprint $table) {
            $table->id();
            $table->string('format', 5);                 // csv | sql
            $table->json('tabel');                       // daftar nama tabel
            $table->json('opsi');                        // header, pemisah, struktur, drop, per_tabel, rahasia, dari, sampai
            $table->string('status', 15)->default('menunggu')->index(); // menunggu | proses | selesai | gagal | kedaluwarsa
            $table->unsignedInteger('tabel_selesai')->default(0);
            $table->string('tabel_sekarang')->nullable();
            $table->unsignedBigInteger('baris')->default(0);
            $table->string('nama_file')->nullable();
            $table->unsignedBigInteger('ukuran')->nullable(); // byte
            $table->text('pesan')->nullable();
            $table->unsignedBigInteger('dibuat_oleh')->nullable();
            $table->timestamp('mulai_at')->nullable();
            $table->timestamp('selesai_at')->nullable();
            $table->timestamp('kedaluwarsa_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_data');
    }
};
