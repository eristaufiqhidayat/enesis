<?php

namespace App\Support\Menu\Motoris;

use App\Models\User;
use App\Support\Menu\JamLokal;
use App\Support\Menu\SumberTabel;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Barang Canvassing: satu baris per muatan canvassing (trx_canvassing), dengan ringkasan jumlah SKU dan
 * total qty dibawa/diterima dari trx_canvassing_item. Sama cakupan datanya dengan SumberCanvassing (lihat
 * SumberCanvassing::dasar()) — hanya muatan dari Sales Koordinator ke Sales Motoris yang sudah diterima.
 * Klik baris untuk detail per produk memakai SumberCanvassing::query() yang sudah ada.
 */
class SumberCanvassingMuatan implements SumberTabel
{
    public function tabelUtama(): string
    {
        return 'trx_canvassing';
    }

    public function query(?User $user): Builder
    {
        return SumberCanvassing::dasar($user)
            ->groupBy('c.id', 'c.nomor', 'c.status', 'c.diterima_at', 'c.dikembalikan_at', 'd.nama', 'k.nama', 'ko.nama', 'c.sumber')
            ->orderByDesc('c.diterima_at')->orderBy('c.nomor')
            ->select([
                'c.id as source_id', 'c.nomor as canvassing_no', 'd.nama as warehouse_name',
                'k.nama as salesperson_name', 'ko.nama as koordinator_name', 'c.status',
                'c.diterima_at', 'c.dikembalikan_at', 'c.sumber',
                DB::raw('COUNT(ci.id) AS jumlah_sku'),
                DB::raw('SUM(ci.qty_bawa) AS total_qty_bawa'),
                DB::raw('SUM(COALESCE(ci.qty_diterima, ci.qty_bawa)) AS total_qty_diterima'),
            ]);
    }

    public function ubahBaris(object $baris): object
    {
        $baris->status = match ($baris->status) {
            'diterima' => 'Diterima',
            'dikembalikan' => 'Dikembalikan',
            'selesai' => 'Selesai',
            default => ucfirst((string) $baris->status),
        };
        $baris->receive_datetime = JamLokal::format($baris->diterima_at, 'Y-m-d H:i:s');
        $baris->return_datetime = JamLokal::format($baris->dikembalikan_at, 'Y-m-d H:i:s');
        $baris->jumlah_sku = (int) $baris->jumlah_sku;
        $baris->total_qty_bawa = SumberPenjualan::angka($baris->total_qty_bawa);
        $baris->total_qty_diterima = SumberPenjualan::angka($baris->total_qty_diterima);
        $baris->sumber = SumberPenjualan::label($baris->sumber);
        unset($baris->diterima_at, $baris->dikembalikan_at);

        return $baris;
    }

    public function kolomCari(): array
    {
        return ['c.nomor', 'k.nama', 'ko.nama', 'd.nama'];
    }
}
