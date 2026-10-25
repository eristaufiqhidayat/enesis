<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Nilai pengaturan sederhana yang diisi admin di Enesis (kunci → nilai), mis. target Rekap Pencapaian SKU.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengaturan_admin', function (Blueprint $table) {
            $table->string('kunci', 100)->primary();
            $table->string('nilai')->nullable();
            $table->unsignedBigInteger('diubah_oleh')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengaturan_admin');
    }
};
