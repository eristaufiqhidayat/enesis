<?php

namespace App\Console\Commands;

use App\Support\CsvTableLoader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class ImportMarketama extends Command
{
    protected $signature = 'marketama:import
                            {dir : Folder berisi file CSV hasil tarikan (nama file = nama tabel)}
                            {--only=* : Hanya impor tabel tertentu}';

    protected $description = 'Impor data hasil tarikan Marketama (CSV per tabel) ke database';

    // Tabel di luar config/marketama.php (dibuat oleh migration tersendiri).
    private const EXTRA_TABLES = ['tracking_koordinator', 'tracking_koordinator_lokasi', 'tracking_sales', 'tracking_sales_lokasi', 'area_polygon', 'area_depo'];

    public function handle(): int
    {
        $dir = rtrim($this->argument('dir'), '/');
        $only = $this->option('only');

        if (! is_dir($dir)) {
            $this->error("Folder tidak ditemukan: {$dir}");

            return self::FAILURE;
        }

        foreach ([...array_keys(config('marketama.tables')), ...self::EXTRA_TABLES] as $table) {
            if ($only && ! in_array($table, $only, true)) {
                continue;
            }

            $path = CsvTableLoader::find($dir, $table);

            if (! $path || ! Schema::hasTable($table)) {
                $this->line("<fg=gray>{$table}: dilewati (file tidak ada)</>");

                continue;
            }

            $rows = CsvTableLoader::load($table, $path);
            $this->info("{$table}: {$rows} baris");
        }

        return self::SUCCESS;
    }
}
