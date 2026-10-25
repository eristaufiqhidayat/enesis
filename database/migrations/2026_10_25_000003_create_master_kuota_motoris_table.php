<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Kuota sales motoris per depo yang diisi admin di Enesis (menu Master Data › Kuota Motoris).
// Satu baris berlaku mulai bulan tertentu sampai ada baris yang lebih baru untuk depo yang sama.
// Bila depo belum pernah diisi, laporan memakai kuota dari Master Depo Marketama (mk_depo.data).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_kuota_motoris', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('depo_id')->index();
            $table->string('berlaku_mulai', 7); // Y-m
            $table->unsignedInteger('kuota');
            $table->unsignedBigInteger('diubah_oleh')->nullable();
            $table->timestamps();
            $table->unique(['depo_id', 'berlaku_mulai']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_kuota_motoris');
    }
};
