<?php

namespace App\Support\Motoris;

use App\Models\User;
use App\Support\Akses\Akses;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

// Beranda (home): ringkasan nasional data motoris untuk kartu KPI (lewat SummaryDepo), funnel outlet, dan
// tren harian. Funnel outlet (NOO → AC → EC → RO, OSS sebagai peringatan terpisah) memakai definisi yang
// sama dengan App\Support\Menu\Motoris\SumberOutletStatus — status seumur hidup outlet, bukan dibatasi
// tanggal, kecuali NOO yang memang punya tanggal daftar. Dibatasi cakupan akun lewat depo (sama dengan
// SummaryDepo / SumberOutletStatus), masing-masing menghitung ulang cakupannya sendiri (lihat pola yang sama
// di SummaryDepo::depoCakupan).
class Dashboard
{
    public function __construct(public readonly string $dari, public readonly string $sampai, private readonly ?User $user) {}

    /**
     * Funnel outlet: NOO (outlet baru didaftarkan dalam rentang tanggal dashboard), AC (pernah dikunjungi,
     * belum pernah effective call), EC (pernah effective call), RO (effective call di ≥2 tanggal berbeda).
     * OSS (kunjungan terakhir tidak tercatat stok produk sendiri) adalah peringatan kualitas outlet AC,
     * ditampilkan terpisah dari funnel utama.
     *
     * @return array<int, array{key: string, label: string, jumlah: int}>
     */
    public function funnelOutlet(): array
    {
        $depo = $this->depoCakupan();
        $ec = DB::table('trx_penjualan')->whereNull('dibatalkan_at')->where('is_effective_call', true)->whereNotNull('outlet_id');

        $jumlahEc = $this->outlet($depo)->whereIn('o.id', (clone $ec)->select('outlet_id'))->count();

        $jumlahAc = $this->outlet($depo)
            ->whereIn('o.id', DB::table('trx_kunjungan')->whereNotNull('outlet_id')->select('outlet_id'))
            ->whereNotIn('o.id', (clone $ec)->select('outlet_id'))
            ->count();

        $jumlahRo = $this->outlet($depo)
            ->whereIn('o.id', (clone $ec)->groupBy('outlet_id')->havingRaw('COUNT(DISTINCT tanggal) >= 2')->select('outlet_id'))
            ->count();

        // Kunjungan terakhir tiap outlet; OSS bila kunjungan itu tidak punya stok produk sendiri > 0 (sama dengan SumberOutletStatus mode 'oss').
        $terakhir = DB::table('trx_kunjungan')->whereNotNull('outlet_id')->groupBy('outlet_id')->selectRaw('outlet_id, MAX(id) AS id');
        $jumlahOss = $this->outlet($depo)
            ->joinSub($terakhir, 'kt', 'kt.outlet_id', '=', 'o.id')
            ->whereNotExists(fn ($w) => $w->from('trx_stok_outlet as s')->whereColumn('s.kunjungan_id', 'kt.id')
                ->whereNotNull('s.produk_id')->where('s.qty', '>', 0)->selectRaw('1'))
            ->count();

        $jumlahNoo = DB::table('trx_outlet_baru as b')
            ->when($depo !== null, fn ($q) => $q->whereIn('b.depo_id', $depo ?: [0]))
            ->whereBetween(DB::raw('DATE(b.recorded_at)'), [$this->dari, $this->sampai])
            ->count();

        return [
            ['key' => 'noo', 'label' => 'Outlet Baru (NOO)', 'jumlah' => $jumlahNoo],
            ['key' => 'ac', 'label' => 'Dikunjungi, Belum EC (AC)', 'jumlah' => $jumlahAc],
            ['key' => 'ec', 'label' => 'Effective Call (EC)', 'jumlah' => $jumlahEc],
            ['key' => 'ro', 'label' => 'Repeat Order (RO)', 'jumlah' => $jumlahRo],
            ['key' => 'oss', 'label' => 'Stok Kosong Saat Kunjungan Terakhir (OSS)', 'jumlah' => $jumlahOss],
        ];
    }

    /**
     * Total sales (Rp) dan jumlah EC per tanggal dalam rentang, dibatasi cakupan depo. Tanggal tanpa transaksi
     * tetap muncul dengan nilai 0, supaya grafik tidak putus.
     *
     * @return Collection<int, array{tanggal: string, sales: int, ec: int}>
     */
    public function trenHarian(): Collection
    {
        $depo = $this->depoCakupan();

        $baris = DB::table('trx_penjualan as p')
            ->join('mk_karyawan as k', 'k.id', '=', 'p.karyawan_id')
            ->whereBetween('p.tanggal', [$this->dari, $this->sampai])
            ->whereNull('p.dibatalkan_at')
            ->when($depo !== null, fn ($q) => $q->whereIn('k.depo_id', $depo ?: [0]))
            ->groupBy('p.tanggal')
            ->selectRaw('p.tanggal, COALESCE(SUM(p.total), 0) AS sales, SUM(CASE WHEN p.is_effective_call THEN 1 ELSE 0 END) AS ec')
            ->get()->keyBy('tanggal');

        $hasil = collect();

        for ($t = CarbonImmutable::parse($this->dari); $t->lte(CarbonImmutable::parse($this->sampai)); $t = $t->addDay()) {
            $tgl = $t->toDateString();
            $b = $baris->get($tgl);
            $hasil->push(['tanggal' => $tgl, 'sales' => (int) ($b->sales ?? 0), 'ec' => (int) ($b->ec ?? 0)]);
        }

        return $hasil;
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
