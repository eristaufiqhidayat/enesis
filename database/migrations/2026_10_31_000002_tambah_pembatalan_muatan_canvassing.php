<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Soft delete muatan canvassing yang masih Disiapkan: dibatalkan_at (pola trx_penjualan.dibatalkan_at) dan
// dibatalkan_oleh = users.id akun web yang membatalkan. Barisnya tetap ada supaya nomor dan jejaknya tidak hilang.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trx_canvassing', function (Blueprint $table) {
            $table->timestamp('dibatalkan_at')->nullable();
            $table->unsignedBigInteger('dibatalkan_oleh')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('trx_canvassing', function (Blueprint $table) {
            $table->dropColumn(['dibatalkan_at', 'dibatalkan_oleh']);
        });
    }
};
