<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class CsvTableLoader
{
    /**
     * Cari file CSV sebuah tabel di folder (boleh .csv atau .csv.gz).
     */
    public static function find(string $dir, string $table): ?string
    {
        foreach (["{$table}.csv", "{$table}.csv.gz"] as $name) {
            if (is_file("{$dir}/{$name}")) {
                return "{$dir}/{$name}";
            }
        }

        return null;
    }

    /**
     * Kosongkan tabel lalu isi dari file CSV (boleh .csv.gz; baris pertama = nama kolom).
     * Kolom CSV yang tidak ada di tabel diabaikan. Mengembalikan jumlah baris.
     */
    public static function load(string $table, string $path, int $chunk = 1000): int
    {
        if (! is_file($path)) {
            throw new RuntimeException("File tidak ditemukan: {$path}");
        }

        $columns = Schema::getColumnListing($table);
        $handle = fopen(str_ends_with($path, '.gz') ? "compress.zlib://{$path}" : $path, 'r');
        $header = fgetcsv($handle, escape: '\\');
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
        $keep = array_values(array_filter(array_keys($header), fn ($i) => in_array($header[$i], $columns, true)));
        $pengganti = self::nilaiPengganti($table, $header, $keep);

        // Batasi ukuran batch untuk tabel berkolom banyak (batas placeholder MySQL 65.535).
        $chunk = max(1, min($chunk, intdiv(60000, max(1, count($keep)))));

        DB::connection()->disableQueryLog();
        DB::table($table)->truncate();

        $batch = [];
        $count = 0;

        while (($row = fgetcsv($handle, escape: '\\')) !== false) {
            $record = [];
            foreach ($keep as $i) {
                $value = $row[$i] ?? null;
                $record[$header[$i]] = $value === '' ? ($pengganti[$header[$i]] ?? null) : $value;
            }
            $batch[] = $record;
            $count++;

            if (count($batch) >= $chunk) {
                DB::table($table)->insert($batch);
                $batch = [];
            }
        }

        if ($batch) {
            DB::table($table)->insert($batch);
        }

        fclose($handle);

        return $count;
    }

    /**
     * Nilai untuk sel kosong di kolom NOT NULL: default kolom di database (mis. 0 untuk kolom boolean/tinyint).
     * Kolom NOT NULL tanpa default: 0 untuk kolom angka/boolean, selain itu tetap NULL (akan gagal disimpan).
     * Kolom nullable tidak masuk daftar, jadi tetap NULL.
     *
     * @return array<string, string> nama kolom => nilai pengganti
     */
    private static function nilaiPengganti(string $table, array $header, array $keep): array
    {
        $skema = collect(Schema::getColumns($table))->keyBy('name');
        $pengganti = [];

        foreach ($keep as $i) {
            $kolom = $skema[$header[$i]] ?? null;

            if (! $kolom || $kolom['nullable']) {
                continue;
            }

            if ($kolom['default'] !== null) {
                // SQLite menyimpan default sebagai teks SQL, mis. "'0'": tanda kutipnya dibuang.
                $pengganti[$header[$i]] = trim((string) $kolom['default'], "'");
            } elseif (preg_match('/int|bool|numeric|decimal|float|double|real/i', $kolom['type_name'])) {
                // NOT NULL tanpa default pada kolom angka/boolean: 0.
                $pengganti[$header[$i]] = '0';
            }
        }

        return $pengganti;
    }
}
