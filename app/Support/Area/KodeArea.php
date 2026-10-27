<?php

namespace App\Support\Area;

use Illuminate\Support\Facades\DB;

// Kode area baru berpola AREA-YYYYMMDD-NNNN (urut per hari), dipakai Design Polygon Area dan Generate Polygon Kelurahan.
// Panggil di dalam transaksi; kolom area_polygon.code unik, jadi tabrakan tetap tertolak database.
class KodeArea
{
    /** @return list<string> $jumlah kode berurutan */
    public static function berikutnya(int $jumlah = 1): array
    {
        $prefix = 'AREA-'.now()->format('Ymd').'-';
        // Nomor diambil sebagai angka, bukan max() string, supaya urutan tetap benar setelah lewat 9999.
        $mulai = (int) DB::table('area_polygon')->where('code', 'like', $prefix.'%')->lockForUpdate()->pluck('code')
            ->map(fn ($kode) => (int) substr($kode, strlen($prefix)))->max() + 1;

        return array_map(
            fn ($i) => $prefix.str_pad((string) ($mulai + $i), 4, '0', STR_PAD_LEFT),
            range(0, max(0, $jumlah - 1)),
        );
    }
}
