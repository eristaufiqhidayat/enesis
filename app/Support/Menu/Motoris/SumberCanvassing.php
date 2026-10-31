<?php

namespace App\Support\Menu\Motoris;

use App\Models\User;
use App\Support\Akses\Akses;
use App\Support\Menu\JamLokal;
use App\Support\Menu\SumberTabel;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Barang Canvassing / Lap. Barang Canvassing: per produk muatan canvassing yang sudah diterima sales (trx_canvassing +
 * trx_canvassing_item, aplikasi HP + riwayat lama). Terjual = jumlah item penjualan (tidak batal) canvassing itu.
 * Subkelas: SumberPengembalianBarang (yang sudah dikembalikan) dan SumberBarangHabisTerjual (+ sisa & status).
 */
class SumberCanvassing implements SumberTabel
{
    /** semua | kembali | habis */
    protected string $mode = 'semua';

    /** false = ikutkan muatan yang belum diterima sales (drawer detail di Barang Canvassing). */
    protected bool $hanyaDiterima = true;

    public function termasukBelumDiterima(): static
    {
        $this->hanyaDiterima = false;

        return $this;
    }

    public function tabelUtama(): string
    {
        return 'trx_canvassing_item';
    }

    /**
     * Join dasar item canvassing → muatan → sales (k) → koordinatornya (ko) → depo/area, dibatasi hanya
     * muatan yang penerimanya Sales Motoris dari Sales Koordinator (dan sudah diterima, kecuali $hanyaDiterima
     * dimatikan); dibatasi pula cakupan role (alias k = sales). Dipakai bersama oleh tampilan per produk (di sini)
     * dan per muatan.
     */
    public static function dasar(?User $user, string $mode = 'semua', bool $hanyaDiterima = true): Builder
    {
        $q = DB::table('trx_canvassing_item as ci')
            ->join('trx_canvassing as c', 'c.id', '=', 'ci.canvassing_id')
            ->join('mk_karyawan as k', 'k.id', '=', 'c.karyawan_id')
            ->join('mk_karyawan as ko', 'ko.id', '=', 'k.koordinator_id')
            ->leftJoin('mk_depo as d', 'd.id', '=', 'c.depo_id')
            ->leftJoin('mk_area as a', 'a.id', '=', 'k.area_aktif_id')
            // hanya canvassing dari Sales Koordinator ke Sales Motoris
            ->where('k.kategori', 'sales')->where('ko.kategori', 'koordinator')
            ->whereNull('c.dibatalkan_at') // muatan yang dibatalkan dari web tidak ikut tampil di mana pun
            ->when($hanyaDiterima, fn ($w) => $w->whereNotNull('c.diterima_at'))
            ->when($mode === 'kembali', fn ($w) => $w->whereNotNull('c.dikembalikan_at'));

        return Akses::batasiKaryawan($q, $user);
    }

    public function query(?User $user): Builder
    {
        $terjual = DB::table('trx_penjualan_item as ji')->join('trx_penjualan as jp', 'jp.id', '=', 'ji.penjualan_id')
            ->whereNull('jp.dibatalkan_at')->whereNotNull('jp.canvassing_id')
            ->groupBy('jp.canvassing_id', 'ji.produk_id')->selectRaw('jp.canvassing_id, ji.produk_id, SUM(ji.qty) AS qty');

        return self::dasar($user, $this->mode, $this->hanyaDiterima)
            ->leftJoin('mk_produk as pr', 'pr.id', '=', 'ci.produk_id')
            ->leftJoinSub($terjual, 'j', fn ($j) => $j->on('j.canvassing_id', '=', 'c.id')->on('j.produk_id', '=', 'ci.produk_id'))
            ->orderByDesc($this->mode === 'kembali' ? 'c.dikembalikan_at' : 'c.diterima_at')->orderBy('c.nomor')->orderBy('ci.id')
            ->select([
                'ci.id as source_id', 'ci.produk_id', 'c.nomor as canvassing_no', 'd.nama as warehouse_name', 'a.nama as area_name',
                'k.nama as salesperson_name', 'ko.nama as koordinator_name', 'c.diterima_at', 'c.dikembalikan_at',
                'pr.nama as product_name', 'pr.kode as product_sku', 'pr.data as produk_data',
                DB::raw('COALESCE(ci.qty_diterima, ci.qty_bawa) AS total_qty'), DB::raw('COALESCE(j.qty, 0) AS sale_qty'),
                'ci.qty_bawa as bawa_qty', 'ci.qty_kembali as return_qty', 'c.sumber',
            ]);
    }

    public function ubahBaris(object $baris): object
    {
        $produk = json_decode((string) $baris->produk_data, true) ?: [];
        $baris->category_name = $produk['kategori'] ?? null;
        $baris->brand_name = $produk['merk'] ?? null;
        $baris->unit_name = $produk['satuan'] ?? null;
        $baris->product_unit = $baris->unit_name;
        $baris->receive_date = JamLokal::format($baris->diterima_at, 'Y-m-d');
        $baris->receive_datetime = JamLokal::format($baris->diterima_at, 'Y-m-d H:i:s');
        $baris->return_date = JamLokal::format($baris->dikembalikan_at, 'Y-m-d');
        $baris->return_datetime = JamLokal::format($baris->dikembalikan_at, 'Y-m-d H:i:s');
        $baris->canvas_return_no = $baris->dikembalikan_at ? $baris->canvassing_no : null;
        $baris->returned = $baris->dikembalikan_at ? 'Sudah' : 'Belum';

        $total = (float) $baris->total_qty;
        $jual = (float) $baris->sale_qty;
        $sisa = max($total - $jual, 0);
        $baris->sisa_qty = SumberPenjualan::angka($sisa);
        $baris->status_terjual = $sisa <= 0 ? 'Habis Terjual' : 'Belum Habis';
        $baris->pct_terjual = $total > 0 ? SumberPenjualan::angka(round($jual / $total * 100, 1)).'%' : '0%';
        $baris->qty_bawa_mentah = (float) $baris->bawa_qty; // angka asli untuk mode ubah (angka() memakai koma desimal)
        $baris->bawa_qty = SumberPenjualan::angka($baris->bawa_qty);
        $baris->total_qty = SumberPenjualan::angka($total);
        $baris->sale_qty = SumberPenjualan::angka($jual);
        $baris->return_qty = $baris->return_qty === null ? null : SumberPenjualan::angka($baris->return_qty);
        $baris->sumber = SumberPenjualan::label($baris->sumber);
        unset($baris->produk_data, $baris->diterima_at, $baris->dikembalikan_at);

        return $baris;
    }

    public function kolomCari(): array
    {
        return ['c.nomor', 'k.nama', 'ko.nama', 'd.nama', 'pr.nama', 'pr.kode'];
    }
}
