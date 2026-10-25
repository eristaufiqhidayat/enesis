<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Index untuk enesis:migrasi-transaksi penjualan / stok / canvassing: tanpa index ini setiap potongan 1.000 baris
// membaca ulang seluruh tabel detail penjualan lama (sangat lambat di data asli).
return new class extends Migration
{
    private const INDEX = [
        'lap_penjualan_barang_detail' => [['trans_code']],
        'lap_pengembalian_barang' => [['canvassing_no']],
        'trx_kunjungan' => [['karyawan_id', 'checkin_at']],
        'trx_penjualan' => [['karyawan_id', 'tanggal']],
    ];

    public function up(): void
    {
        foreach (self::INDEX as $tabel => $daftar) {
            if (! Schema::hasTable($tabel)) {
                continue;
            }

            Schema::table($tabel, function (Blueprint $table) use ($tabel, $daftar) {
                foreach ($daftar as $kolom) {
                    if (! Schema::hasIndex($tabel, $kolom)) {
                        $table->index($kolom);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::INDEX as $tabel => $daftar) {
            if (! Schema::hasTable($tabel)) {
                continue;
            }

            Schema::table($tabel, function (Blueprint $table) use ($tabel, $daftar) {
                foreach ($daftar as $kolom) {
                    if (Schema::hasIndex($tabel, $kolom)) {
                        $table->dropIndex($kolom);
                    }
                }
            });
        }
    }
};
