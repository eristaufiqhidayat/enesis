<?php

namespace App\Console\Commands;

use App\Support\Outlet\VerifikasiOutletBaru;
use App\Support\Outlet\WilayahOutlet as Wilayah;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

// Mengisi wilayah (provinsi s/d kelurahan + kode kelurahan) outlet baru dari HP yang belum punya wilayah:
// pendaftaran di trx_outlet_baru dan outlet hasil persetujuannya di mk_outlet (ID >= 100.000.000).
// Hasil terbaik bila batas kelurahan kota itu sudah dimuat: php artisan wilayah:impor-batas --provinsi=..
class WilayahOutlet extends Command
{
    protected $signature = 'enesis:wilayah-outlet {--ulang : hitung ulang juga yang sudah punya wilayah}';

    protected $description = 'Isi provinsi/kota/kecamatan/kelurahan outlet baru dari HP berdasarkan titik GPS';

    public function handle(Wilayah $wilayah): int
    {
        $baru = $master = 0;

        DB::table('trx_outlet_baru')
            ->when(! $this->option('ulang'), fn ($q) => $q->whereNull('provinsi')->whereNull('kode_kelurahan'))
            ->orderBy('id')->chunkById(200, function ($baris) use ($wilayah, &$baru) {
                foreach ($baris as $b) {
                    $hasil = $wilayah->cari((float) $b->lat, (float) $b->lng, $b->area_id ? (int) $b->area_id : null);

                    if (array_filter($hasil)) {
                        DB::table('trx_outlet_baru')->where('id', $b->id)->update($hasil);
                        $baru++;
                    }
                }
            });

        DB::table('mk_outlet')->where('id', '>=', VerifikasiOutletBaru::ID_AWAL)
            ->when(! $this->option('ulang'), fn ($q) => $q->whereNull('provinsi')->whereNull('kode_kelurahan'))
            ->orderBy('id')->chunkById(200, function ($baris) use ($wilayah, &$master) {
                foreach ($baris as $o) {
                    $hasil = $wilayah->cari(
                        $o->latitude === null ? null : (float) $o->latitude,
                        $o->longitude === null ? null : (float) $o->longitude,
                        $o->area_id ? (int) $o->area_id : null,
                    );

                    if (array_filter($hasil)) {
                        DB::table('mk_outlet')->where('id', $o->id)->update($hasil);
                        $master++;
                    }
                }
            });

        $this->info("Wilayah diisi: {$baru} pendaftaran outlet baru, {$master} outlet master hasil persetujuan.");

        return self::SUCCESS;
    }
}
