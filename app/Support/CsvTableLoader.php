<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class CsvTableLoader
{
    /**
     * Kosongkan tabel lalu isi dari file CSV (baris pertama = nama kolom).
     * Kolom CSV yang tidak ada di tabel diabaikan. Mengembalikan jumlah baris.
     */
    public static function load(string $table, string $path, int $chunk = 500): int
    {
        if (! is_file($path)) {
            throw new RuntimeException("File tidak ditemukan: {$path}");
        }

        $columns = Schema::getColumnListing($table);
        $handle = fopen($path, 'r');
        $header = fgetcsv($handle, escape: '\\');
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
        $keep = array_values(array_filter(array_keys($header), fn ($i) => in_array($header[$i], $columns, true)));

        DB::table($table)->truncate();

        $batch = [];
        $count = 0;

        while (($row = fgetcsv($handle, escape: '\\')) !== false) {
            $record = [];
            foreach ($keep as $i) {
                $value = $row[$i] ?? null;
                $record[$header[$i]] = $value === '' ? null : $value;
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
}
