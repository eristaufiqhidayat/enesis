<?php

namespace App\Support\Database;

use Illuminate\Support\Facades\DB;

// Potongan SQL yang berbeda antar database (server memakai PostgreSQL, test SQLite, sebagian instalasi MySQL).
class Sql
{
    /** Kolom sebagai teks, mis. untuk LIKE pada kolom kode bertipe bigint (PostgreSQL menolak bigint LIKE). */
    public static function teks(string $kolom): string
    {
        return self::mysql() ? "CAST({$kolom} AS CHAR)" : "CAST({$kolom} AS TEXT)";
    }

    /** Gabungkan nilai unik jadi satu teks "a, b, c" berurutan (GROUP_CONCAT / STRING_AGG). */
    public static function gabungUnik(string $kolom): string
    {
        return match (DB::connection()->getDriverName()) {
            'mysql', 'mariadb' => "GROUP_CONCAT(DISTINCT {$kolom} ORDER BY {$kolom} SEPARATOR ', ')",
            'pgsql' => "STRING_AGG(DISTINCT {$kolom}, ', ' ORDER BY {$kolom})",
            default => "GROUP_CONCAT(DISTINCT {$kolom})",
        };
    }

    private static function mysql(): bool
    {
        return in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);
    }
}
