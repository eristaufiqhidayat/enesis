<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Absen = checkin depo (keputusan 28 Okt 2026, seperti Marketama). Data absen dari HP sebelum aturan ini yang belum
// punya checkin depo dilengkapi: jam masuk/pulang dan titik absen menjadi checkin/checkout depo. Absen yang sudah
// pulang menutup checkin depo yang belum checkout. Tidak ada data yang dihapus; down() tidak mengubah apa-apa.
return new class extends Migration
{
    public function up(): void
    {
        DB::table('trx_absensi as a')
            ->leftJoin('trx_checkin_depo as c', fn ($j) => $j->on('c.karyawan_id', '=', 'a.karyawan_id')->on('c.tanggal', '=', 'a.tanggal'))
            ->whereNull('c.id')->whereNotNull('a.depo_id')->whereNotNull('a.masuk_at')
            ->orderBy('a.id')
            ->select(['a.id', 'a.karyawan_id', 'a.depo_id', 'a.tanggal', 'a.masuk_at', 'a.pulang_at', 'a.masuk_lat', 'a.masuk_lng',
                'a.recorded_at', 'a.received_at', 'a.device_id', 'a.app_version', 'a.sumber'])
            ->chunkById(500, function ($baris) {
                DB::table('trx_checkin_depo')->insert($baris->map(fn ($a) => [
                    'karyawan_id' => $a->karyawan_id,
                    'depo_id' => $a->depo_id,
                    'tanggal' => $a->tanggal,
                    'checkin_at' => $a->masuk_at,
                    'checkout_at' => $a->pulang_at,
                    'lat' => $a->masuk_lat,
                    'lng' => $a->masuk_lng,
                    'recorded_at' => $a->recorded_at,
                    'received_at' => $a->received_at,
                    'device_id' => $a->device_id,
                    'app_version' => $a->app_version,
                    'sumber' => $a->sumber ?? 'motoris',
                ])->all());
            }, 'a.id', 'id');

        DB::table('trx_checkin_depo as c')
            ->join('trx_absensi as a', fn ($j) => $j->on('a.karyawan_id', '=', 'c.karyawan_id')->on('a.tanggal', '=', 'c.tanggal'))
            ->whereNull('c.checkout_at')->whereNotNull('a.pulang_at')
            ->orderBy('c.id')->select(['c.id', 'a.pulang_at'])
            ->chunkById(500, function ($baris) {
                foreach ($baris as $b) {
                    DB::table('trx_checkin_depo')->where('id', $b->id)->update(['checkout_at' => $b->pulang_at]);
                }
            }, 'c.id', 'id');
    }

    public function down(): void
    {
        // Data pelengkap tetap disimpan.
    }
};
