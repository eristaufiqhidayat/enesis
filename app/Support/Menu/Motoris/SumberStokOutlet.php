<?php

namespace App\Support\Menu\Motoris;

use App\Models\User;
use App\Support\Akses\Akses;
use App\Support\Menu\JamLokal;
use App\Support\Menu\SumberTabel;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Stok Outlet / Lap. Stok Outlet: stok produk sendiri yang dicatat sales saat kunjungan (trx_stok_outlet, aplikasi HP
 * + riwayat aplikasi lama). Subkelas SumberStokKompetitor menampilkan stok produk kompetitor.
 */
class SumberStokOutlet implements SumberTabel
{
    protected bool $kompetitor = false;

    /** Kategori kunjungan aplikasi lama (lap_checkin_outlet.category). */
    private const KATEGORI_LAMA = [1 => 'Menjual Produk', 2 => 'Update Stok', 3 => 'Toko Tutup'];

    public function tabelUtama(): string
    {
        return 'trx_stok_outlet';
    }

    public function query(?User $user): Builder
    {
        $beli = DB::table('trx_penjualan as jb')->whereColumn('jb.kunjungan_id', 'kj.id')->whereNull('jb.dibatalkan_at')->selectRaw('1');
        $q = DB::table('trx_stok_outlet as s')
            ->join('trx_kunjungan as kj', 'kj.id', '=', 's.kunjungan_id')
            ->join('mk_karyawan as k', 'k.id', '=', 'kj.karyawan_id')
            ->leftJoin('mk_karyawan as ko', 'ko.id', '=', 'k.koordinator_id')
            ->leftJoin('mk_outlet as o', 'o.id', '=', 'kj.outlet_id')
            ->leftJoin('mk_depo as d', 'd.id', '=', 'kj.depo_id')
            ->leftJoin('mk_produk as pr', 'pr.id', '=', 's.produk_id')
            ->when($this->kompetitor, fn ($w) => $w->whereNull('s.produk_id'), fn ($w) => $w->whereNotNull('s.produk_id'))
            ->orderByDesc('kj.tanggal')->orderByDesc('kj.checkin_at')->orderBy('s.id')
            ->select([
                's.id as source_id', 's.qty as quantity', 's.created_at as last_update', 'k.nama as salesperson_name', 'ko.nama as koordinator_name',
                'kj.tanggal as date_checkin', 'kj.checkin_at as datetime_checkin', 'kj.checkout_at as datetime_checkout', 'kj.durasi_detik as duration',
                'kj.kategori_lama', 'kj.tidak_beli_kategori',
                'o.nama as outlet_name', 'o.kode as outlet_code', 'o.alamat as outlet_address', 'o.area as area_name', 'd.nama as warehouse_name',
                'pr.kode as product_code', DB::raw($this->kompetitor ? 's.produk_kompetitor AS product_name' : 'pr.nama AS product_name'),
                'pr.data as produk_data', 's.sumber',
            ])
            ->selectSub($beli->limit(1), 'beli');

        return Akses::batasiKaryawan($q, $user);
    }

    public function ubahBaris(object $baris): object
    {
        $produk = json_decode((string) $baris->produk_data, true) ?: [];
        $baris->unit_name = $produk['satuan'] ?? null;
        $baris->category_name = $produk['kategori'] ?? null;
        $baris->brand_name = $produk['merk'] ?? null;
        $baris->quantity = SumberPenjualan::angka($baris->quantity);
        $baris->last_update = JamLokal::format($baris->last_update, 'Y-m-d H:i:s');
        $baris->date_checkin = substr((string) $baris->date_checkin, 0, 10);
        $baris->datetime_checkin = JamLokal::format($baris->datetime_checkin, 'H:i:s');
        $baris->datetime_checkout = JamLokal::format($baris->datetime_checkout, 'H:i:s');
        $baris->category = match (true) {
            $baris->kategori_lama !== null && isset(self::KATEGORI_LAMA[(int) $baris->kategori_lama]) => self::KATEGORI_LAMA[(int) $baris->kategori_lama],
            (bool) $baris->beli => 'Menjual Produk',
            $baris->tidak_beli_kategori === 'toko_tutup' => 'Toko Tutup',
            default => 'Update Stok',
        };
        $baris->sumber = SumberPenjualan::label($baris->sumber);
        unset($baris->produk_data, $baris->kategori_lama, $baris->tidak_beli_kategori, $baris->beli);

        return $baris;
    }

    public function kolomCari(): array
    {
        return ['k.nama', 'ko.nama', 'o.kode', 'o.nama', 'd.nama', $this->kompetitor ? 's.produk_kompetitor' : 'pr.nama', 'pr.kode'];
    }
}
