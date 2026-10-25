<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Verifikasi outlet baru dari HP oleh admin web (menu All Outlet, tab Outlet Baru dari HP).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trx_outlet_baru', function (Blueprint $table) {
            $table->index('status');
            $table->unsignedBigInteger('diverifikasi_oleh')->nullable(); // users.id
            $table->timestamp('diverifikasi_pada')->nullable();
            $table->string('alasan_tolak')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('trx_outlet_baru', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn(['diverifikasi_oleh', 'diverifikasi_pada', 'alasan_tolak']);
        });
    }
};
