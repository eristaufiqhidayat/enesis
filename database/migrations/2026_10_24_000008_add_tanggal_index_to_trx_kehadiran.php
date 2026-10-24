<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Menu Absensi Sales & Checkin Depo menyaring per rentang tanggal (termasuk riwayat hasil migrasi).
return new class extends Migration
{
    public function up(): void
    {
        foreach (['trx_absensi', 'trx_checkin_depo'] as $tabel) {
            Schema::table($tabel, fn (Blueprint $table) => $table->index('tanggal'));
        }
    }

    public function down(): void
    {
        foreach (['trx_absensi', 'trx_checkin_depo'] as $tabel) {
            Schema::table($tabel, fn (Blueprint $table) => $table->dropIndex($tabel.'_tanggal_index'));
        }
    }
};
