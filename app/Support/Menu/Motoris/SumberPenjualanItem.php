<?php

namespace App\Support\Menu\Motoris;

use App\Models\User;
use App\Support\Menu\JamLokal;
use App\Support\Menu\SumberTabel;
use Illuminate\Database\Query\Builder;

/** Lap. Penjualan Barang Detail: per produk terjual (trx_penjualan_item), kolom sama dengan laporan lama. */
class SumberPenjualanItem implements SumberTabel
{
    protected bool $hanyaEc = false;

    public function tabelUtama(): string
    {
        return 'trx_penjualan_item';
    }

    public function query(?User $user): Builder
    {
        return SumberPenjualan::dasar($user, $this->hanyaEc)
            ->join('trx_penjualan_item as i', 'i.penjualan_id', '=', 'p.id')
            ->leftJoin('mk_produk as pr', 'pr.id', '=', 'i.produk_id')
            ->orderByDesc('p.tanggal')->orderByDesc('p.recorded_at')->orderByDesc('p.id')->orderBy('i.id')
            ->select([
                'i.id as source_id', 'p.kode as trans_code', 'p.is_effective_call as effective_call_status',
                'k.nama as salesperson_name', 'ko.nama as koordinator_name', 'p.tanggal as date_trans', 'p.recorded_at as datetime_trans',
                'pr.nama as product_name', 'pr.kode as product_sku', 'pr.data as produk_data',
                'i.qty as quantity', 'i.harga as price', 'i.diskon as discount', 'i.total', 'i.diskon as total_discount',
                'o.nama as outlet_name', 'o.kode as outlet_code', 'o.longitude as outlet_longitude', 'o.latitude as outlet_latitude',
                'o.area as area_name', 'd.nama as warehouse_name',
                'kj.checkin_at as datetime_checkin', 'kj.checkout_at as datetime_checkout', 'kj.durasi_detik as checkin_duration',
                'kj.lng as sales_longitude', 'kj.lat as sales_latitude', 'p.sumber',
            ]);
    }

    public function ubahBaris(object $baris): object
    {
        $produk = json_decode((string) $baris->produk_data, true) ?: [];
        $baris->category_name = $produk['kategori'] ?? null;
        $baris->brand_name = $produk['merk'] ?? null;
        $baris->unit_name = $produk['satuan'] ?? null;
        $baris->date_trans = substr((string) $baris->date_trans, 0, 10);
        $baris->datetime_trans = JamLokal::format($baris->datetime_trans, 'H:i:s');
        $baris->datetime_checkin = JamLokal::format($baris->datetime_checkin, 'H:i:s');
        $baris->datetime_checkout = JamLokal::format($baris->datetime_checkout, 'H:i:s');
        $baris->effective_call_status = $baris->effective_call_status ? 'Ya' : 'Tidak';
        $baris->quantity = SumberPenjualan::angka($baris->quantity);
        foreach (['price', 'discount', 'total', 'total_discount'] as $k) {
            $baris->{$k} = number_format((float) $baris->{$k}, 0, ',', '.');
        }
        $baris->sumber = SumberPenjualan::label($baris->sumber);
        unset($baris->produk_data);

        return $baris;
    }

    public function kolomCari(): array
    {
        return ['p.kode', 'k.nama', 'ko.nama', 'pr.nama', 'pr.kode', 'o.kode', 'o.nama', 'd.nama'];
    }
}
