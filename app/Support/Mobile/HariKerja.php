<?php

namespace App\Support\Mobile;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

// Status satu hari kerja sales motoris: absen, checkin depo, muatan canvassing, kunjungan
// terbuka, sisa bawaan, dan area rute. Dipakai bersama oleh endpoint harian.
class HariKerja
{
    public function __construct(public readonly int $karyawanId, public readonly string $tanggal) {}

    public function absensi(): ?object
    {
        return DB::table('trx_absensi')->where('karyawan_id', $this->karyawanId)->where('tanggal', $this->tanggal)->first();
    }

    public function checkinDepo(): ?object
    {
        return DB::table('trx_checkin_depo')->where('karyawan_id', $this->karyawanId)->where('tanggal', $this->tanggal)->first();
    }

    /** Canvassing hari ini (yang terbaru bila lebih dari satu). */
    public function canvassing(): ?object
    {
        return DB::table('trx_canvassing')->where('karyawan_id', $this->karyawanId)->where('tanggal', $this->tanggal)
            ->orderByDesc('id')->first();
    }

    /** Canvassing hari ini yang sudah diterima sales (syarat kunjungan dan penjualan). */
    public function canvassingDiterima(): ?object
    {
        $c = $this->canvassing();

        return $c && $c->diterima_at ? $c : null;
    }

    public function kunjunganTerbuka(): ?object
    {
        return DB::table('trx_kunjungan')->where('karyawan_id', $this->karyawanId)->whereNull('checkout_at')
            ->orderByDesc('id')->first();
    }

    /**
     * Sisa bawaan per produk: diterima (atau dibawa) - terjual (transaksi tidak batal).
     *
     * @return Collection<int, array{produk_id: int, nama: ?string, dibawa: float, terjual: float, sisa: float}>
     */
    public function sisa(object $canvassing): Collection
    {
        $terjual = DB::table('trx_penjualan_item as i')
            ->join('trx_penjualan as p', 'p.id', '=', 'i.penjualan_id')
            ->where('p.canvassing_id', $canvassing->id)->whereNull('p.dibatalkan_at')
            ->groupBy('i.produk_id')->selectRaw('i.produk_id, SUM(i.qty) AS qty')
            ->pluck('qty', 'produk_id');

        return DB::table('trx_canvassing_item as c')
            ->leftJoin('mk_produk as p', 'p.id', '=', 'c.produk_id')
            ->where('c.canvassing_id', $canvassing->id)->orderBy('c.id')
            ->get(['c.*', 'p.nama', 'p.kode', 'p.harga_jual'])
            ->mapWithKeys(function ($i) use ($terjual) {
                $dibawa = (float) ($i->qty_diterima ?? $i->qty_bawa);
                $jual = (float) ($terjual[$i->produk_id] ?? 0);

                return [(int) $i->produk_id => [
                    'produk_id' => (int) $i->produk_id,
                    'kode' => $i->kode,
                    'nama' => $i->nama,
                    'harga' => $i->harga_jual !== null ? (int) round((float) $i->harga_jual) : null,
                    'disiapkan' => round((float) $i->qty_bawa, 2),
                    'dibawa' => round($dibawa, 2),
                    'terjual' => round($jual, 2),
                    'sisa' => round($dibawa - $jual, 2),
                ]];
            });
    }

    /**
     * Area rute sales: area aktif di mk_karyawan_area; bila tidak ada yang ditandai aktif, semua areanya.
     *
     * @return array<int>
     */
    public function areaRute(): array
    {
        return self::areaKaryawan($this->karyawanId);
    }

    /** Area rute sales: area yang ditandai aktif, atau semua areanya bila tidak ada yang aktif. */
    public static function areaKaryawan(int $karyawanId): array
    {
        $area = DB::table('mk_karyawan_area')->where('karyawan_id', $karyawanId)->get();
        $aktif = $area->filter(fn ($a) => (bool) $a->aktif);

        return ($aktif->isNotEmpty() ? $aktif : $area)->pluck('area_id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * Status kunjungan hari ini per outlet: belum | dikunjungi | beli | tidak_beli.
     *
     * @return array<int, array{status: string, visit_id: int}>
     */
    public function statusOutlet(): array
    {
        $kunjungan = DB::table('trx_kunjungan')->where('karyawan_id', $this->karyawanId)->where('tanggal', $this->tanggal)
            ->whereNotNull('outlet_id')->orderBy('id')->get();
        $beli = DB::table('trx_penjualan')->where('karyawan_id', $this->karyawanId)->where('tanggal', $this->tanggal)
            ->whereNull('dibatalkan_at')->pluck('kunjungan_id')->flip();
        $hasil = [];

        foreach ($kunjungan as $k) {
            $status = isset($beli[$k->id]) ? 'beli' : ($k->tidak_beli_kategori || $k->checkout_at ? 'tidak_beli' : 'dikunjungi');

            // Beli di salah satu kunjungan menang atas kunjungan lain di outlet yang sama.
            if (($hasil[$k->outlet_id]['status'] ?? null) !== 'beli') {
                $hasil[(int) $k->outlet_id] = ['status' => $status, 'visit_id' => (int) $k->id];
            }
        }

        return $hasil;
    }

    public function wajibAbsen(): object
    {
        return $this->absensi() ?? throw new ApiError('not_checked_in', 'Absen masuk dulu sebelum melanjutkan.', 409);
    }

    public function wajibCanvassing(): object
    {
        return $this->canvassingDiterima()
            ?? throw new ApiError('no_canvassing', 'Terima barang canvassing hari ini dulu sebelum kunjungan.', 409);
    }

    public function tanpaKunjunganTerbuka(): void
    {
        if ($k = $this->kunjunganTerbuka()) {
            throw ApiError::konflik('visit_open', 'Masih ada kunjungan yang belum checkout.', ['visit_id' => (int) $k->id]);
        }
    }
}
