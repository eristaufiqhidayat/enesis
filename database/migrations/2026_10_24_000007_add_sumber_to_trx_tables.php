<?php

use App\Support\Marketama\SambungTransaksi;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Data lama (lap_*) dimigrasi ke tabel motoris (trx_*). Kolom "sumber" membedakan baris dari
// aplikasi lama ("lama") dan dari aplikasi motoris ("motoris"); "sumber_id" = source_id baris
// lama supaya migrasi bisa diulang tanpa dobel. Juga kolom ID master untuk lap_checkin_checkout.
return new class extends Migration
{
    public const TRX = ['trx_absensi', 'trx_checkin_depo'];

    public function up(): void
    {
        foreach (self::TRX as $tabel) {
            Schema::table($tabel, function (Blueprint $table) {
                $table->string('sumber', 10)->default('motoris')->index();
                $table->unsignedBigInteger('sumber_id')->nullable()->unique();
            });
        }

        foreach (SambungTransaksi::PETA as $tabel => $kolom) {
            if (! Schema::hasTable($tabel)) {
                continue;
            }

            Schema::table($tabel, function (Blueprint $table) use ($tabel, $kolom) {
                foreach (array_keys($kolom) as $nama) {
                    if (! Schema::hasColumn($tabel, $nama)) {
                        $table->unsignedBigInteger($nama)->nullable()->index();
                    }
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::TRX as $tabel) {
            Schema::table($tabel, function (Blueprint $table) use ($tabel) {
                $table->dropUnique($tabel.'_sumber_id_unique');
                $table->dropIndex($tabel.'_sumber_index');
                $table->dropColumn(['sumber', 'sumber_id']);
            });
        }

        if (Schema::hasColumn('lap_checkin_checkout', 'karyawan_id')) {
            Schema::table('lap_checkin_checkout', function (Blueprint $table) {
                $table->dropIndex('lap_checkin_checkout_karyawan_id_index');
                $table->dropIndex('lap_checkin_checkout_depo_id_index');
                $table->dropColumn(['karyawan_id', 'depo_id']);
            });
        }
    }
};
