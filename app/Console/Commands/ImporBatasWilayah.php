<?php

namespace App\Console\Commands;

use App\Support\Wilayah\BatasWilayah;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class ImporBatasWilayah extends Command
{
    protected $signature = 'wilayah:impor-batas
                            {--kota=* : Kode kota/kabupaten 4 digit (mis. 3171)}
                            {--provinsi=* : Kode provinsi 2 digit; semua kota di tabel kota_kabupaten provinsi itu}
                            {--semua : Semua kota di tabel kota_kabupaten}
                            {--dir= : Folder "db" hasil git clone cahyadsn/wilayah_boundaries (tanpa internet)}
                            {--paksa : Ambil ulang walaupun sudah ada}';

    protected $description = 'Isi tabel batas_wilayah (batas resmi kota, kecamatan, kelurahan) untuk Generate Polygon Kelurahan';

    public function handle(BatasWilayah $batas): int
    {
        if ($this->option('dir')) {
            config(['batas_wilayah.direktori' => $this->option('dir')]);
        }

        $kota = collect($this->option('kota'))->map(fn ($k) => preg_replace('/\D/', '', $k));
        $provinsi = collect($this->option('provinsi'))->map(fn ($p) => preg_replace('/\D/', '', $p))->filter();

        if ($this->option('semua') || $provinsi->isNotEmpty()) {
            $kota = $kota->merge(
                DB::table('kota_kabupaten')->whereNotNull('kode')
                    ->when(! $this->option('semua'), fn ($q) => $q->where(function ($q) use ($provinsi) {
                        $provinsi->each(fn ($p) => $q->orWhere('kode', 'like', $p.'%'));
                    }))
                    ->pluck('kode')->map(fn ($k) => (string) $k)
            );
        }

        $kota = $kota->filter(fn ($k) => strlen($k) === 4)->unique()->sort()->values();
        if ($kota->isEmpty()) {
            $this->error('Sebutkan --kota=3171, --provinsi=31, atau --semua.');

            return self::FAILURE;
        }

        $gagal = 0;
        $bar = $this->output->createProgressBar($kota->count());
        foreach ($kota as $kode) {
            try {
                $batas->pastikanKota($kode, (bool) $this->option('paksa'));
            } catch (Throwable $e) {
                $gagal++;
                $this->newLine();
                $this->warn("{$kode}: {$e->getMessage()}");
            }
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();

        $this->table(['Tingkat', 'Jumlah batas'], DB::table('batas_wilayah')
            ->selectRaw('tingkat, count(*) as jumlah')->groupBy('tingkat')->orderBy('tingkat')->get()
            ->map(fn ($r) => [$r->tingkat, number_format($r->jumlah, 0, ',', '.')])->all());

        return $gagal ? self::FAILURE : self::SUCCESS;
    }
}
