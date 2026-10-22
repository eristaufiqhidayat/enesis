<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Riwayat lokasi GPS koordinator untuk halaman Tracking History Koordinator Location.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracking_koordinator', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('koordinator_id')->unique();
            $table->string('nama')->nullable();
            $table->string('telepon')->nullable();
            $table->string('depo')->nullable();
        });

        Schema::create('tracking_koordinator_lokasi', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('koordinator_id');
            $table->date('tanggal');
            $table->dateTime('waktu');
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->decimal('akurasi', 8, 2)->nullable();
            $table->string('source_id')->nullable();
            $table->index(['koordinator_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracking_koordinator_lokasi');
        Schema::dropIfExists('tracking_koordinator');
    }
};
