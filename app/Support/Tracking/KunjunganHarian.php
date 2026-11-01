<?php

namespace App\Support\Tracking;

use App\Support\Menu\JamLokal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

// Kunjungan outlet (trx_kunjungan: checkin HP dan riwayat lama hasil migrasi) satu motoris pada satu tanggal,
// untuk daftar di drawer Riwayat Lacak Motoris. Jam disimpan UTC dan ditampilkan dalam zona aplikasi.
class KunjunganHarian
{
    /** @return Collection<int, object> urut jam checkin */
    public static function untuk(int $karyawanId, string $tanggal): Collection
    {
        // Lencana "Ditandai" (tanda outlet, spec 053): outlet dengan tanda aktif, lepas dari motoris/tanggal kunjungan.
        $ditandai = DB::table('tanda_outlet as t')->whereColumn('t.outlet_id', 'kj.outlet_id')->whereNull('t.dicabut_at')->selectRaw('COUNT(*)');

        $kunjungan = DB::table('trx_kunjungan as kj')
            ->leftJoin('mk_outlet as o', 'o.id', '=', 'kj.outlet_id')
            ->leftJoin('trx_outlet_baru as ob', 'ob.id', '=', 'kj.outlet_baru_id')
            ->where('kj.karyawan_id', $karyawanId)->where('kj.tanggal', substr($tanggal, 0, 10))
            ->orderBy('kj.checkin_at')->orderBy('kj.id')
            ->select(['kj.id', 'kj.outlet_id', 'kj.checkin_at', 'kj.checkout_at', 'kj.durasi_detik', 'kj.lat', 'kj.lng', 'kj.jarak_m',
                'kj.di_luar_rute', 'kj.mock_location', 'kj.tidak_beli_kategori', 'kj.tidak_beli_keterangan', 'kj.keterangan_lama'])
            ->selectRaw('COALESCE(o.nama, ob.nama) as outlet')
            ->addSelect('o.kode as kode_outlet')
            ->selectSub($ditandai, 'outlet_ditandai')
            ->get();

        // Penjualan aktif (tidak dibatalkan) per kunjungan, dijumlahkan bila satu kunjungan punya beberapa transaksi.
        $penjualan = DB::table('trx_penjualan')->whereIn('kunjungan_id', $kunjungan->pluck('id')->all() ?: [0])->whereNull('dibatalkan_at')
            ->groupBy('kunjungan_id')
            ->selectRaw('kunjungan_id, COUNT(*) AS transaksi, SUM(total) AS total, SUM(jumlah_sku) AS sku, SUM(total_qty) AS qty')
            ->get()->keyBy('kunjungan_id');

        return $kunjungan->map(fn ($k, $i) => (object) [
            'id' => (int) $k->id,
            // Null untuk outlet baru dari HP yang belum disetujui (belum ada baris mk_outlet, belum bisa ditandai).
            'outlet_id' => $k->outlet_id !== null ? (int) $k->outlet_id : null,
            'outlet' => $k->outlet ?? 'Outlet tidak diketahui',
            // Kode master outlet; null untuk outlet baru dari HP yang belum disetujui.
            'kode_outlet' => $k->kode_outlet ?: null,
            'outlet_ditandai' => (bool) $k->outlet_ditandai,
            'jam' => JamLokal::format($k->checkin_at, 'H:i'),
            'keluar' => JamLokal::format($k->checkout_at, 'H:i'),
            'durasi_menit' => $k->durasi_detik !== null ? (int) round($k->durasi_detik / 60) : null,
            'lat' => $k->lat !== null ? (float) $k->lat : null,
            'lng' => $k->lng !== null ? (float) $k->lng : null,
            'jarak_m' => $k->jarak_m !== null ? (int) $k->jarak_m : null,
            'di_luar_rute' => (bool) $k->di_luar_rute,
            'lokasi_palsu' => (bool) $k->mock_location,
            'beli' => isset($penjualan[$k->id]),
            'penjualan' => isset($penjualan[$k->id]) ? [
                'transaksi' => (int) $penjualan[$k->id]->transaksi,
                'total' => (int) round((float) $penjualan[$k->id]->total),
                'sku' => (int) $penjualan[$k->id]->sku,
                'qty' => (float) $penjualan[$k->id]->qty,
            ] : null,
            'keterangan' => self::alasan($k),
            'perjalanan' => $i > 0 ? self::perjalanan($kunjungan[$i - 1], $k) : null,
        ]);
    }

    /**
     * Alasan tidak beli yang terbaca: label kode HP (config mobile.alasan_tidak_beli; kode tak dikenal apa adanya)
     * digabung dengan teks bebas dari HP; kunjungan lama hasil migrasi menyimpannya di keterangan_lama.
     */
    private static function alasan(object $k): ?string
    {
        $label = $k->tidak_beli_kategori ? config("mobile.alasan_tidak_beli.{$k->tidak_beli_kategori}", $k->tidak_beli_kategori) : null;
        $teks = collect([$label, $k->tidak_beli_keterangan])->filter()->implode(' — ');

        return $teks !== '' ? $teks : ($k->keterangan_lama ?: null);
    }

    /**
     * Perjalanan dari kunjungan sebelumnya ke kunjungan ini: menit dari checkout sebelumnya sampai checkin ini dan
     * jarak garis lurus antar koordinat (perkiraan, bukan jarak jalan). Null bila waktunya tidak bisa dihitung:
     * kunjungan sebelumnya belum checkout, atau checkin ini lebih awal dari checkout itu (data tumpang tindih).
     *
     * @return array{menit: int, jarak_m: ?int}|null
     */
    private static function perjalanan(object $sebelum, object $ini): ?array
    {
        if ($sebelum->checkout_at === null) {
            return null;
        }

        $detik = CarbonImmutable::parse($sebelum->checkout_at, 'UTC')->diffInSeconds(CarbonImmutable::parse($ini->checkin_at, 'UTC'), false);

        if ($detik < 0) {
            return null;
        }

        $adaKoordinat = $sebelum->lat !== null && $sebelum->lng !== null && $ini->lat !== null && $ini->lng !== null;

        return [
            'menit' => (int) round($detik / 60),
            'jarak_m' => $adaKoordinat ? self::jarakMeter((float) $sebelum->lat, (float) $sebelum->lng, (float) $ini->lat, (float) $ini->lng) : null,
        ];
    }

    /** Jarak garis lurus (haversine) dua koordinat dalam meter. */
    private static function jarakMeter(float $lat1, float $lng1, float $lat2, float $lng2): int
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return (int) round(6371000 * 2 * asin(min(1, sqrt($a))));
    }
}
