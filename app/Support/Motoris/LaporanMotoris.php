<?php

namespace App\Support\Motoris;

use App\Models\User;
use App\Support\Akses\Akses;
use App\Support\Mobile\Pengaturan;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

// Motoris Report dari tabel motoris (trx_absensi, trx_kunjungan, trx_penjualan) + master karyawan/depo:
// - Coverage & Performance per kota (City Group depo), ditambah baris total "N CITY" = jumlah kota yang tampil;
// - Absensi per sales motoris.
// Kuota dari master_kuota_motoris (isian admin) dengan cadangan Master Depo Marketama. HK standar = hari kerja
// sales (waktu_aktif_app, dikurangi libur) dari awal bulan sampai hari ini / akhir bulan; target call per hari
// dari target depo yang dipakai aplikasi HP (Pengaturan::depo).
class LaporanMotoris
{
    public readonly string $dari;

    public readonly string $sampai;

    public readonly int $hkStandar;

    private ?array $perDepo = null;

    public function __construct(public readonly string $bulan, private readonly ?User $user = null)
    {
        $zona = Pengaturan::zona();
        $awal = CarbonImmutable::parse("{$bulan}-01", $zona);
        $hariIni = CarbonImmutable::now($zona)->startOfDay();
        $akhir = $awal->endOfMonth()->startOfDay();

        $this->dari = $awal->toDateString();
        $this->sampai = ($akhir->gt($hariIni) ? $hariIni : $akhir)->toDateString();
        $this->hkStandar = self::hariKerja($this->dari, $this->sampai);
    }

    /** Jumlah hari kerja sales antara dua tanggal (termasuk), tanpa hari libur. */
    public static function hariKerja(string $dari, string $sampai): int
    {
        if ($sampai < $dari) {
            return 0;
        }

        $libur = Pengaturan::libur($dari, $sampai);
        $zona = Pengaturan::zona();
        $hari = CarbonImmutable::parse($dari, $zona);
        $akhir = CarbonImmutable::parse($sampai, $zona);
        $jumlah = 0;

        for (; $hari->lte($akhir); $hari = $hari->addDay()) {
            if (! isset($libur[$hari->toDateString()]) && Pengaturan::jamAktif($hari, 'sales')['hari_kerja']) {
                $jumlah++;
            }
        }

        return $jumlah;
    }

    /** @return array{baris: Collection<int, object>, total: object} */
    public function coverage(): array
    {
        $buat = function (string $kota, Collection $depo) {
            $kuota = (int) $depo->sum('kuota');
            $aktif = (int) $depo->sum('aktif');

            return (object) [
                'kota' => $kota,
                'kuota' => $kuota,
                'sudah_transaksi' => (int) $depo->sum('sudah_transaksi'),
                'aktif' => $aktif,
                'vacant' => max($kuota - $aktif, 0),
                'coverage' => $kuota > 0 ? round($aktif / $kuota * 100, 1) : null,
            ];
        };

        return $this->perKota($buat);
    }

    /** @return array{baris: Collection<int, object>, total: object} */
    public function performance(): array
    {
        $buat = function (string $kota, Collection $depo) {
            $hadir = (int) $depo->sum('hari_hadir');
            $orangHadir = (int) $depo->sum('orang_hadir');
            $transaksi = (int) $depo->sum('transaksi');

            return (object) [
                'kota' => $kota,
                'kuota' => (int) $depo->sum('kuota'),
                'sudah_transaksi' => (int) $depo->sum('sudah_transaksi'),
                'aktif' => (int) $depo->sum('aktif'),
                'hk_standar' => $this->hkStandar,
                'act_hk' => $orangHadir > 0 ? round($hadir / $orangHadir, 1) : 0,
                'target' => (int) $depo->sum('target_call'),
                'call' => (int) $depo->sum('call'),
                'ec' => (int) $depo->sum('ec'),
                'ipt' => $transaksi > 0 ? round($depo->sum('sku') / $transaksi, 1) : 0,
                'value' => (int) $depo->sum('value'),
            ];
        };

        return $this->perKota($buat);
    }

    /**
     * Kehadiran per sales motoris pada bulan ini: sales aktif, ditambah sales nonaktif yang masih sempat absen.
     *
     * @param  array{depo: ?int, koordinator: ?int, q: string}  $filter
     */
    public function absensi(array $filter, int $perHalaman = 50): LengthAwarePaginator
    {
        $hadir = DB::table('trx_absensi')->whereBetween('tanggal', [$this->dari, $this->sampai])
            ->groupBy('karyawan_id')->selectRaw('karyawan_id, COUNT(*) AS hadir');

        $q = $this->sales()
            ->leftJoinSub($hadir, 'a', 'a.karyawan_id', '=', 'k.id')
            ->leftJoin('mk_depo as d', 'd.id', '=', 'k.depo_id')
            ->leftJoin('mk_karyawan as ko', 'ko.id', '=', 'k.koordinator_id')
            ->where(fn ($w) => $w->where('k.aktif', true)->orWhereNotNull('a.karyawan_id'))
            ->when($filter['depo'], fn ($w) => $w->where('k.depo_id', $filter['depo']))
            ->when($filter['koordinator'], fn ($w) => $w->where('k.koordinator_id', $filter['koordinator']))
            ->when($filter['q'] !== '', fn ($w) => $w->whereRaw('LOWER(k.nama) LIKE ?', ['%'.mb_strtolower($filter['q']).'%']))
            ->orderBy('d.nama')->orderBy('k.nama')
            ->select(['k.id', 'k.nama', 'k.aktif', 'd.nama as depo', 'ko.nama as koordinator', DB::raw('COALESCE(a.hadir, 0) AS hadir')]);

        $hasil = $q->paginate($perHalaman)->withQueryString();
        $hasil->getCollection()->transform(function ($b) {
            $b->hadir = (int) $b->hadir;
            $b->hari_kerja = $this->hkStandar;
            $b->tidak_hadir = max($this->hkStandar - $b->hadir, 0);

            return $b;
        });

        return $hasil;
    }

    // ------------------------------------------------------------------

    /**
     * Kelompokkan angka per depo ke kota (City Group). Depo tanpa kota hanya tampil bila punya angka.
     *
     * @return array{baris: Collection<int, object>, total: object}
     */
    private function perKota(callable $buat): array
    {
        $tampil = collect($this->perDepo())
            ->filter(fn ($d) => $d->kota !== null || $d->kuota || $d->aktif || $d->call || $d->hari_hadir);
        $baris = $tampil
            ->groupBy(fn ($d) => $d->kota ?? 'Tanpa City Group')
            ->sortKeys(SORT_NATURAL | SORT_FLAG_CASE)
            ->map(fn ($grup, $kota) => $buat((string) $kota, $grup))
            ->values();

        return ['baris' => $baris, 'total' => $buat('TOTAL '.$baris->count().' CITY', $tampil)];
    }

    /** Angka mentah per depo dalam cakupan akun (dihitung sekali per laporan). */
    private function perDepo(): array
    {
        if ($this->perDepo !== null) {
            return $this->perDepo;
        }

        $cakupan = Akses::cakupan($this->user) === 'semua' ? null
            : (clone $this->sales())->whereNotNull('k.depo_id')->distinct()->pluck('k.depo_id')->map(fn ($id) => (int) $id)->all();

        $depo = KuotaMotoris::untukBulan($this->bulan, $cakupan)
            ->filter(fn ($d) => $d->aktif || $d->kuota)
            ->keyBy('id');

        $perDepo = fn (Builder $q, string $pilih) => $q->groupBy('k.depo_id')->selectRaw("k.depo_id, {$pilih}")->get()->keyBy('depo_id');
        $rentang = [$this->dari, $this->sampai];

        $aktif = $perDepo($this->sales()->where('k.aktif', true), 'COUNT(*) AS n');
        $absen = $perDepo($this->sales()->join('trx_absensi as t', 't.karyawan_id', '=', 'k.id')->whereBetween('t.tanggal', $rentang),
            'COUNT(*) AS hari, COUNT(DISTINCT t.karyawan_id) AS orang');
        $call = $perDepo($this->sales()->join('trx_kunjungan as t', 't.karyawan_id', '=', 'k.id')->whereBetween('t.tanggal', $rentang), 'COUNT(*) AS n');
        $jual = $perDepo($this->sales()->join('trx_penjualan as t', 't.karyawan_id', '=', 'k.id')
            ->whereBetween('t.tanggal', $rentang)->whereNull('t.dibatalkan_at'),
            'COUNT(*) AS transaksi, COUNT(DISTINCT t.karyawan_id) AS orang, SUM(CASE WHEN t.is_effective_call THEN 1 ELSE 0 END) AS ec, '
            .'SUM(t.jumlah_sku) AS sku, SUM(t.total) AS nilai');

        // Depo yang punya aktivitas tapi tidak ada di daftar (mis. depo nonaktif tanpa kuota) tetap dihitung.
        foreach ([$aktif, $absen, $call, $jual] as $sumber) {
            foreach ($sumber->keys() as $id) {
                if ($id !== null && ! isset($depo[$id]) && ($cakupan === null || in_array((int) $id, $cakupan, true))) {
                    $lain = KuotaMotoris::untukBulan($this->bulan, [(int) $id])->first();
                    $depo[$id] = $lain ?? (object) ['id' => (int) $id, 'nama' => 'Depo #'.$id, 'kota' => null, 'aktif' => false, 'kuota' => null];
                }
            }
        }

        return $this->perDepo = $depo->map(function ($d) use ($aktif, $absen, $call, $jual) {
            $n = (int) ($aktif[$d->id]->n ?? 0);
            $targetHarian = Pengaturan::depo($d->id)['target']['call'] ?? config('mobile.target_default.call');

            return (object) [
                'id' => $d->id,
                'nama' => $d->nama,
                'kota' => $d->kota,
                'kuota' => (int) ($d->kuota ?? 0),
                'aktif' => $n,
                'sudah_transaksi' => (int) ($jual[$d->id]->orang ?? 0),
                'hari_hadir' => (int) ($absen[$d->id]->hari ?? 0),
                'orang_hadir' => (int) ($absen[$d->id]->orang ?? 0),
                'target_call' => (int) $targetHarian * $this->hkStandar * $n,
                'call' => (int) ($call[$d->id]->n ?? 0),
                'ec' => (int) ($jual[$d->id]->ec ?? 0),
                'transaksi' => (int) ($jual[$d->id]->transaksi ?? 0),
                'sku' => (int) ($jual[$d->id]->sku ?? 0),
                'value' => (int) ($jual[$d->id]->nilai ?? 0),
            ];
        })->values()->all();
    }

    /** Karyawan kategori sales dalam cakupan akun (alias k). */
    private function sales(): Builder
    {
        return Akses::batasiKaryawan(DB::table('mk_karyawan as k')->where('k.kategori', 'sales'), $this->user);
    }
}
