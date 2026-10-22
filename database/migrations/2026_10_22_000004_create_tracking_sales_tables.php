<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Riwayat lokasi GPS sales untuk halaman Tracking History Sales Location.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracking_sales', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sales_id')->unique();
            $table->string('nama')->nullable();
            $table->string('telepon')->nullable();
            $table->unsignedBigInteger('koordinator_id')->nullable()->index();
            $table->string('koordinator_nama')->nullable();
            $table->string('depo')->nullable();
            $table->decimal('depo_lat', 10, 7)->nullable();
            $table->decimal('depo_lng', 10, 7)->nullable();
        });

        Schema::create('tracking_sales_lokasi', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sales_id');
            $table->date('tanggal');
            $table->dateTime('waktu');
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->string('source_id')->nullable();
            $table->index(['sales_id', 'tanggal']);
            $table->index('tanggal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracking_sales_lokasi');
        Schema::dropIfExists('tracking_sales');
    }
};
