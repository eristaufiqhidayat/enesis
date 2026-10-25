<?php

namespace App\Support\Menu\Motoris;

use App\Models\User;
use App\Support\Akses\Akses;
use App\Support\Menu\JamLokal;
use App\Support\Menu\SumberTabel;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Penjualan Barang / Lap. Penjualan Barang: transaksi trx_penjualan (aplikasi HP + riwayat aplikasi lama hasil
 * enesis:migrasi-transaksi penjualan), tidak termasuk yang dibatalkan. Kolom sama dengan laporan lama Marketama.
 */
class SumberPenjualan implements SumberTabel
{
    /** true = hanya transaksi effective call (Lap. Efective Call). */
    protected bool $hanyaEc = false;

    public function tabelUtama(): string
    {
        return 'trx_penjualan';
    }

    public function query(?User $user): Builder
    {
        return self::dasar($user, $this->hanyaEc)
            ->orderByDesc('p.tanggal')->orderByDesc('p.recorded_at')->orderByDesc('p.id')
            ->select([
                'p.id as source_id', 'p.kode as trans_code', 'k.nama as salesperson_name', 'ko.nama as koordinator_name',
                'p.tanggal as date_trans', 'p.recorded_at as datetime_trans', 'p.is_effective_call as effective_call_status',
                'p.jumlah_sku as total_item', 'p.total_qty', 'p.total as grand_total',
                'o.nama as outlet_name', 'o.kode as outlet_code', 'o.longitude as outlet_longitude', 'o.latitude as outlet_latitude',
                'o.alamat as outlet_address', 'o.area as area_name', 'd.nama as warehouse_name',
                'kj.checkin_at as datetime_checkin', 'kj.checkout_at as datetime_checkout', 'kj.durasi_detik as checkin_duration',
                'kj.lng as sales_longitude', 'kj.lat as sales_latitude', 'p.received_at as created_at', 'p.sumber',
            ]);
    }

    /** Join dasar penjualan → sales, koordinator, outlet, depo, kunjungan; dibatasi cakupan role (alias k = sales). */
    public static function dasar(?User $user, bool $hanyaEc = false): Builder
    {
        $q = DB::table('trx_penjualan as p')
            ->join('mk_karyawan as k', 'k.id', '=', 'p.karyawan_id')
            ->leftJoin('mk_karyawan as ko', 'ko.id', '=', 'k.koordinator_id')
            ->leftJoin('mk_outlet as o', 'o.id', '=', 'p.outlet_id')
            ->leftJoin('mk_depo as d', 'd.id', '=', 'p.depo_id')
            ->leftJoin('trx_kunjungan as kj', 'kj.id', '=', 'p.kunjungan_id')
            ->whereNull('p.dibatalkan_at')
            ->when($hanyaEc, fn ($w) => $w->where('p.is_effective_call', true));

        return Akses::batasiKaryawan($q, $user);
    }

    public function ubahBaris(object $baris): object
    {
        $baris->date_trans = substr((string) $baris->date_trans, 0, 10);
        $baris->datetime_trans = JamLokal::format($baris->datetime_trans, 'H:i:s');
        $baris->datetime_checkin = JamLokal::format($baris->datetime_checkin, 'H:i:s');
        $baris->datetime_checkout = JamLokal::format($baris->datetime_checkout, 'H:i:s');
        $baris->effective_call_status = $baris->effective_call_status ? 'Ya' : 'Tidak';
        $baris->total_qty = self::angka($baris->total_qty);
        $baris->grand_total = number_format((float) $baris->grand_total, 0, ',', '.');
        $baris->created_by_name = $baris->salesperson_name;
        $baris->created_at = JamLokal::format($baris->created_at, 'Y-m-d H:i:s');
        $baris->sumber = self::label($baris->sumber);

        return $baris;
    }

    public function kolomCari(): array
    {
        return ['p.kode', 'k.nama', 'ko.nama', 'o.kode', 'o.nama', 'o.area', 'd.nama'];
    }

    public static function angka(mixed $n): string
    {
        return rtrim(rtrim(number_format((float) $n, 2, ',', '.'), '0'), ',');
    }

    public static function label(?string $sumber): string
    {
        return str_starts_with((string) $sumber, 'lama') ? 'Aplikasi lama' : 'Aplikasi HP';
    }
}
