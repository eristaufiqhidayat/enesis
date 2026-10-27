<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Posisi terakhir per orang (Tracking Last Seen, Peta Sebaran Sales) mencari "ORDER BY waktu DESC LIMIT 1" per
// sales/koordinator di riwayat aplikasi lama; index (orang, waktu) membuatnya langsung, tanpa mengurutkan semua titik.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tracking_sales_lokasi', fn (Blueprint $t) => $t->index(['sales_id', 'waktu']));
        Schema::table('tracking_koordinator_lokasi', fn (Blueprint $t) => $t->index(['koordinator_id', 'waktu']));
    }

    public function down(): void
    {
        Schema::table('tracking_sales_lokasi', fn (Blueprint $t) => $t->dropIndex(['sales_id', 'waktu']));
        Schema::table('tracking_koordinator_lokasi', fn (Blueprint $t) => $t->dropIndex(['koordinator_id', 'waktu']));
    }
};
