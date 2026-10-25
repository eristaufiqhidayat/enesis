<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Riwayat penjualan, stok outlet/kompetitor dan canvassing aplikasi lama dimigrasi ke tabel motoris
// (enesis:migrasi-transaksi penjualan stok_outlet stok_kompetitor canvassing absensi_koordinator).
// "sumber" = motoris | lama, "sumber_id" = source_id baris lama supaya aman diulang. Juga kolom ID master
// untuk lap_stok_kompetitor & lap_absensi_koordinator (diisi enesis:sambung-transaksi).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trx_penjualan', function (Blueprint $table) {
            $table->string('sumber', 10)->default('motoris')->index();
            $table->unsignedBigInteger('sumber_id')->nullable()->unique();
        });

        // Stok produk sendiri & kompetitor berasal dari dua tabel lama: source_id unik per sumber.
        Schema::table('trx_stok_outlet', function (Blueprint $table) {
            $table->string('sumber', 12)->default('motoris')->index(); // motoris | lama | lama_komp
            $table->unsignedBigInteger('sumber_id')->nullable();
            $table->unique(['sumber', 'sumber_id']);
        });

        Schema::table('trx_canvassing', function (Blueprint $table) {
            $table->string('sumber', 10)->default('motoris')->index();
        });

        Schema::table('trx_canvassing_item', function (Blueprint $table) {
            $table->unsignedBigInteger('sumber_id')->nullable()->unique(); // source_id lap_barang_canvassing
        });

        $kolom = [
            'lap_stok_kompetitor' => ['karyawan_id', 'koordinator_id', 'area_id', 'outlet_id'],
            'lap_absensi_koordinator' => ['karyawan_id', 'depo_id'],
        ];

        foreach ($kolom as $tabel => $daftar) {
            if (! Schema::hasTable($tabel)) {
                continue;
            }

            Schema::table($tabel, function (Blueprint $table) use ($tabel, $daftar) {
                foreach ($daftar as $nama) {
                    if (! Schema::hasColumn($tabel, $nama)) {
                        $col = $table->unsignedBigInteger($nama)->nullable();
                        in_array($nama, ['karyawan_id', 'outlet_id', 'depo_id'], true) && $col->index();
                    }
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('trx_penjualan', function (Blueprint $table) {
            $table->dropUnique('trx_penjualan_sumber_id_unique');
            $table->dropIndex('trx_penjualan_sumber_index');
            $table->dropColumn(['sumber', 'sumber_id']);
        });
        Schema::table('trx_stok_outlet', function (Blueprint $table) {
            $table->dropUnique('trx_stok_outlet_sumber_sumber_id_unique');
            $table->dropIndex('trx_stok_outlet_sumber_index');
            $table->dropColumn(['sumber', 'sumber_id']);
        });
        Schema::table('trx_canvassing', function (Blueprint $table) {
            $table->dropIndex('trx_canvassing_sumber_index');
            $table->dropColumn('sumber');
        });
        Schema::table('trx_canvassing_item', function (Blueprint $table) {
            $table->dropUnique('trx_canvassing_item_sumber_id_unique');
            $table->dropColumn('sumber_id');
        });
    }
};
