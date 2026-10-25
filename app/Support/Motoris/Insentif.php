<?php

namespace App\Support\Motoris;

use App\Support\Marketama\HalamanParser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Perkiraan insentif sales dari aturan Master Data (dipakai aplikasi HP "me/performance" dan Lap. Insentif Sales):
// - insentif EC: rentang total EC sebulan di Rumus Insentif Sales;
// - nilai poin: poin × rupiah per poin depo di Konversi Poin Per Depo.
class Insentif
{
    public static function ec(int $ec): ?int
    {
        if (! Schema::hasTable('rumus_insentif_sales')) {
            return null;
        }

        foreach (DB::table('rumus_insentif_sales')->get() as $r) {
            $bawah = HalamanParser::angka((string) $r->tot_ec_lower_bound) ?? 0;
            $atas = HalamanParser::angka((string) $r->tot_ec_upper_bound) ?? PHP_INT_MAX;

            if ($ec >= $bawah && $ec <= $atas) {
                return (int) (HalamanParser::angka((string) $r->total_insentif) ?? 0);
            }
        }

        return 0;
    }

    public static function nilaiPoin(?string $namaDepo, int $poin): ?int
    {
        if (! $namaDepo || ! Schema::hasTable('sales_point_conversion_per_warehouse')) {
            return null;
        }

        $rp = DB::table('sales_point_conversion_per_warehouse')->get()
            ->first(fn ($r) => HalamanParser::kunciNama($r->depo) === HalamanParser::kunciNama($namaDepo))?->poin_insentif_rp;

        return $rp === null ? null : (int) $rp * $poin;
    }
}
