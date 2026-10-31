<?php

namespace App\Support\Motoris;

use App\Models\User;
use App\Support\Akses\Akses;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

// Beranda (home): ringkasan nasional data motoris untuk kartu KPI (lewat SummaryDepo), funnel outlet, dan
// tren harian. Funnel outlet (NOO → AC → EC → RO, OSS sebagai peringatan terpisah) memakai definisi yang
// sama dengan App\Support\Menu\Motoris\SumberOutletStatus — status seumur hidup outlet, bukan dibatasi
// tanggal, kecuali NOO yang memang punya tanggal daftar. Dibatasi cakupan akun lewat depo (sama dengan
// SummaryDepo / SumberOutletStatus), masing-masing menghitung ulang cakupannya sendiri (lihat pola yang sama
// di SummaryDepo::depoCakupan). funnelOutlet() dan trenHarian() scan trx_penjualan/trx_kunjungan yang di
// produksi bisa sampai jutaan baris (lihat CLAUDE.md § Data loading) dan Beranda dibuka jauh lebih sering
// daripada menu lain, jadi hasilnya di-cache singkat (lihat CACHE_TTL) per rentang tanggal + cakupan akun.
class Dashboard
{
    private const CACHE_TTL = 300; // detik; Beranda tidak perlu detik-per-detik real-time.

    public function __construct(public readonly string $dari, public readonly string $sampai, private readonly ?User $user) {}

    /**
     * Funnel outlet: NOO (outlet baru didaftarkan dalam rentang tanggal dashboard), AC (pernah dikunjungi,
     * belum pernah effective call), EC (pernah effective call), RO (effective call di ≥2 tanggal berbeda).
     * OSS (outlet AC yang kunjungan terakhirnya tidak tercatat stok produk sendiri) adalah peringatan kualitas,
     * ditampilkan terpisah dari funnel utama — sama dengan cakupan mode 'oss' di SumberOutletStatus (AC saja,
     * bukan semua outlet).
     *
     * Satu query agregat (outlet LEFT JOIN subquery kunjungan-terakhir + subquery EC + subquery stok), bukan
     * 4 query COUNT terpisah yang masing-masing scan ulang trx_penjualan/trx_kunjungan secara penuh.
     *
     * @return array<int, array{key: string, label: string, jumlah: int}>
     */
    public function funnelOutlet(): array
    {
        return Cache::remember($this->kunci('funnel'), self::CACHE_TTL, function () {
            $depo = $this->depoCakupan();

            $kunjunganTerakhir = DB::table('trx_kunjungan')->whereNotNull('outlet_id')
                ->groupBy('outlet_id')->selectRaw('outlet_id, MAX(id) AS kunjungan_id');

            $ec = DB::table('trx_penjualan')->whereNull('dibatalkan_at')->where('is_effective_call', true)->whereNotNull('outlet_id')
                ->groupBy('outlet_id')->selectRaw('outlet_id, COUNT(DISTINCT tanggal) AS hari_ec');

            $adaStokSendiri = DB::table('trx_stok_outlet')->whereNotNull('produk_id')->where('qty', '>', 0)
                ->groupBy('kunjungan_id')->selectRaw('kunjungan_id, 1 AS ada');

            $baris = $this->outlet($depo)
                ->leftJoinSub($kunjunganTerakhir, 'kt', 'kt.outlet_id', '=', 'o.id')
                ->leftJoinSub($ec, 'e', 'e.outlet_id', '=', 'o.id')
                ->leftJoinSub($adaStokSendiri, 's', 's.kunjungan_id', '=', 'kt.kunjungan_id')
                ->selectRaw('
                    COALESCE(SUM(CASE WHEN e.outlet_id IS NOT NULL THEN 1 ELSE 0 END), 0) AS ec,
                    COALESCE(SUM(CASE WHEN kt.outlet_id IS NOT NULL AND e.outlet_id IS NULL THEN 1 ELSE 0 END), 0) AS ac,
                    COALESCE(SUM(CASE WHEN e.hari_ec >= 2 THEN 1 ELSE 0 END), 0) AS ro,
                    COALESCE(SUM(CASE WHEN kt.outlet_id IS NOT NULL AND e.outlet_id IS NULL AND s.kunjungan_id IS NULL THEN 1 ELSE 0 END), 0) AS oss
                ')->first();

            $jumlahNoo = DB::table('trx_outlet_baru as b')
                ->when($depo !== null, fn ($q) => $q->whereIn('b.depo_id', $depo ?: [0]))
                ->whereBetween(DB::raw('DATE(b.recorded_at)'), [$this->dari, $this->sampai])
                ->count();

            return [
                ['key' => 'noo', 'label' => 'Outlet Baru (NOO)', 'jumlah' => $jumlahNoo],
                ['key' => 'ac', 'label' => 'Dikunjungi, Belum EC (AC)', 'jumlah' => (int) $baris->ac],
                ['key' => 'ec', 'label' => 'Effective Call (EC)', 'jumlah' => (int) $baris->ec],
                ['key' => 'ro', 'label' => 'Repeat Order (RO)', 'jumlah' => (int) $baris->ro],
                ['key' => 'oss', 'label' => 'Stok Kosong Saat Kunjungan Terakhir (OSS)', 'jumlah' => (int) $baris->oss],
            ];
        });
    }

    /**
     * Total sales (Rp) dan jumlah EC per tanggal dalam rentang, dibatasi cakupan depo. Tanggal tanpa transaksi
     * tetap muncul dengan nilai 0, supaya grafik tidak putus.
     *
     * @return array<int, array{tanggal: string, sales: int, ec: int}>
     */
    public function trenHarian(): array
    {
        return Cache::remember($this->kunci('tren'), self::CACHE_TTL, function () {
            $depo = $this->depoCakupan();

            $baris = DB::table('trx_penjualan as p')
                ->join('mk_karyawan as k', 'k.id', '=', 'p.karyawan_id')
                ->whereBetween('p.tanggal', [$this->dari, $this->sampai])
                ->whereNull('p.dibatalkan_at')
                ->when($depo !== null, fn ($q) => $q->whereIn('k.depo_id', $depo ?: [0]))
                ->groupBy('p.tanggal')
                ->selectRaw('p.tanggal, COALESCE(SUM(p.total), 0) AS sales, SUM(CASE WHEN p.is_effective_call THEN 1 ELSE 0 END) AS ec')
                ->get()->keyBy('tanggal');

            // Array murni (bukan Collection/stdClass): aman di-cache lewat PHP serialize() apa pun cache
            // driver-nya, dan Inertia men-JSON-kan array persis sama dengan Collection.
            $hasil = [];

            for ($t = CarbonImmutable::parse($this->dari); $t->lte(CarbonImmutable::parse($this->sampai)); $t = $t->addDay()) {
                $tgl = $t->toDateString();
                $b = $baris->get($tgl);
                $hasil[] = ['tanggal' => $tgl, 'sales' => (int) ($b->sales ?? 0), 'ec' => (int) ($b->ec ?? 0)];
            }

            return $hasil;
        });
    }

    /** Kunci cache: dipisah per rentang tanggal + cakupan akun (akun dengan cakupan "semua" berbagi satu entri). */
    private function kunci(string $bagian): string
    {
        $cakupan = Akses::cakupan($this->user) === 'semua' ? 'semua' : 'user_'.($this->user?->id ?? 0);

        return "beranda:{$bagian}:{$this->dari}:{$this->sampai}:{$cakupan}";
    }

    private function outlet(?array $depo): Builder
    {
        return DB::table('mk_outlet as o')
            ->leftJoin('mk_area as a', 'a.id', '=', 'o.area_id')
            ->when($depo !== null, fn ($q) => $q->whereIn('a.depo_id', $depo ?: [0]));
    }

    /** @return array<int>|null null = semua depo (sama dengan SummaryDepo::depoCakupan / SumberOutletStatus::depoCakupan) */
    private function depoCakupan(): ?array
    {
        if (Akses::cakupan($this->user) === 'semua') {
            return null;
        }

        return Akses::batasiKaryawan(DB::table('mk_karyawan as k')->whereNotNull('k.depo_id'), $this->user)
            ->distinct()->pluck('k.depo_id')->map(fn ($id) => (int) $id)->all();
    }
}
