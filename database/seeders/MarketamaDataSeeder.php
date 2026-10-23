<?php

namespace Database\Seeders;

use App\Support\CsvTableLoader;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

// Semua data hasil tarikan Marketama selain data referensi: transaksi 1–22 Okt 2026,
// karyawan, user, outlet, area, dan riwayat lokasi.
// Data ini berisi data pribadi, jadi tidak disimpan di repo. Ekstrak ZIP hasil tarikan ke
// folder MARKETAMA_DATA_PATH (default: storage/app/marketama, sudah di-.gitignore).
class MarketamaDataSeeder extends Seeder
{
    public function run(): void
    {
        $dir = rtrim(config('marketama_data.path'), '/');
        $files = array_merge(glob("{$dir}/*.csv") ?: [], glob("{$dir}/*.csv.gz") ?: []);

        if (! $files) {
            $this->command?->warn("Folder data Marketama kosong atau tidak ada: {$dir}");
            $this->command?->warn('Ekstrak marketama_csv_import_1-22okt2026.zip ke folder itu, lalu jalankan: php artisan db:seed --class=MarketamaDataSeeder');

            return;
        }

        sort($files);

        foreach ($files as $file) {
            $table = preg_replace('/\.csv(\.gz)?$/', '', basename($file));

            if (in_array($table, ReferenceDataSeeder::TABLES, true)) {
                continue;
            }

            if (! Schema::hasTable($table)) {
                $this->command?->warn("{$table}: tabel tidak ada, dilewati");

                continue;
            }

            $started = microtime(true);
            $rows = CsvTableLoader::load($table, $file);
            $this->command?->info(sprintf('%s: %s baris (%.1f detik)', $table, number_format($rows, 0, ',', '.'), microtime(true) - $started));
        }
    }
}
