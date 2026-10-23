<?php

use App\Support\Marketama\SambungTransaksi;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Kolom ID master (mk_*) di tabel transaksi hasil tarikan awal (lap_*), diisi oleh
// "php artisan enesis:sambung-transaksi". Teks nama asli tetap disimpan.
return new class extends Migration
{
    private const DIINDEKS = ['karyawan_id', 'depo_id', 'outlet_id'];

    public function up(): void
    {
        Schema::create('mk_peta_nama', function (Blueprint $table) {
            $table->string('jenis', 20);
            $table->string('nilai');
            $table->string('depo')->default('');
            $table->unsignedBigInteger('ref_id');
            $table->index(['jenis', 'nilai']);
        });

        foreach (SambungTransaksi::PETA as $tabel => $kolom) {
            if (! Schema::hasTable($tabel)) {
                continue;
            }

            Schema::table($tabel, function (Blueprint $table) use ($tabel, $kolom) {
                foreach (array_keys($kolom) as $nama) {
                    if (Schema::hasColumn($tabel, $nama)) {
                        continue;
                    }

                    $col = $table->unsignedBigInteger($nama)->nullable();

                    if (in_array($nama, self::DIINDEKS, true)) {
                        $col->index();
                    }
                }
            });
        }
    }

    public function down(): void
    {
        foreach (SambungTransaksi::PETA as $tabel => $kolom) {
            if (! Schema::hasTable($tabel)) {
                continue;
            }

            Schema::table($tabel, function (Blueprint $table) use ($tabel, $kolom) {
                foreach (array_keys($kolom) as $nama) {
                    if (Schema::hasColumn($tabel, $nama)) {
                        if (in_array($nama, self::DIINDEKS, true)) {
                            $table->dropIndex([$nama]);
                        }

                        $table->dropColumn($nama);
                    }
                }
            });
        }

        Schema::dropIfExists('mk_peta_nama');
    }
};
