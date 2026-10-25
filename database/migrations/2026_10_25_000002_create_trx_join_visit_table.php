<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Join visit: koordinator mendampingi sales ke outlet (aplikasi HP koordinator). Baris dari
// aplikasi lama (lap_join_visit) dipindah ke sini oleh enesis:migrasi-transaksi (sumber = "lama").
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trx_join_visit', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('koordinator_id')->index(); // mk_karyawan
            $table->unsignedBigInteger('sales_id')->index();       // mk_karyawan
            $table->unsignedBigInteger('depo_id')->nullable()->index();
            $table->unsignedBigInteger('outlet_id')->nullable()->index(); // mk_outlet
            $table->unsignedBigInteger('area_id')->nullable();
            $table->date('tanggal')->index();
            $table->timestamp('mulai_at')->nullable();
            $table->decimal('mulai_lat', 10, 7)->nullable();
            $table->decimal('mulai_lng', 11, 7)->nullable();
            $table->unsignedInteger('jarak_m')->nullable(); // jarak koordinator ke outlet saat mulai
            $table->boolean('mock_location')->default(false);
            $table->timestamp('selesai_at')->nullable();
            $table->decimal('selesai_lat', 10, 7)->nullable();
            $table->decimal('selesai_lng', 11, 7)->nullable();
            $table->unsignedInteger('durasi_detik')->nullable();
            $table->text('catatan')->nullable();
            $table->uuid('foto_id')->nullable();         // mobile_uploads
            $table->string('foto_lama')->nullable();     // nama/URL foto dari aplikasi lama
            $table->string('sumber', 10)->default('motoris')->index(); // motoris | lama
            $table->unsignedBigInteger('sumber_id')->nullable()->unique(); // source_id lap_join_visit
            $table->uuid('client_ref')->nullable()->unique();
            $table->uuid('selesai_client_ref')->nullable();
            $table->timestamp('recorded_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->boolean('clock_skew')->default(false);
            $table->string('device_id')->nullable();
            $table->string('app_version', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trx_join_visit');
    }
};
