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

// Summary All Depo dari tabel motoris, rumus mengikuti Marketama:
// - Hari kerja sales = tanggal dengan absen ATAU kunjungan (trx_absensi ∪ trx_kunjungan).
// - Hari kerja depo = Σ hari kerja semua sales depo (termasuk sales tidak aktif yang masih bekerja di rentang).
// - AVG Call/EC/Sales/Point per day = total ÷ hari kerja (depo atau sales). AVG IPT = SKU ÷ EC.
// - AVG Hari Kerja (kartu) = hari kerja depo ÷ sales motoris aktif.
// - Achievement = AVG ÷ target nasional (Call, EC, IPT; satu nilai untuk semua depo).
// - Kunjungan SK dari trx_join_visit; AVG Visit SK/Day = visit ÷ hari kerja koordinator (Senin–Jumat tanpa libur).
// Depo sales = depo karyawan (mk_karyawan.depo_id); depo join visit = depo kunjungan, cadangan depo koordinator.
class SummaryDepo
{
    public const TARGET_BAWAAN = ['call' => 45, 'ec' => 35, 'ipt' => 3];

    /** Hari kerja koordinator untuk rata-rata visit SK: Senin (1) – Jumat (5), sesuai Marketama. */
    public const HARI_KOORDINATOR = [1, 2, 3, 4, 5];

    public readonly int $hkKoordinator;

    /** @var array{call: float, ec: float, ipt: float} */
    public readonly array $target;

    private ?Collection $sales = null;

    private ?array $distributor = null;

    public function __construct(public readonly string $dari, public readonly string $sampai, private readonly ?User $user = null)
    {
        $this->hkKoordinator = self::hariKerjaKoordinator($dari, $sampai);
        $this->target = self::target();
    }

    /** @return array{call: float, ec: float, ipt: float} */
    public static function target(): array
    {
        $tersimpan = DB::table('pengaturan_admin')->whereIn('kunci', array_map(fn ($k) => "target_nasional.{$k}", array_keys(self::TARGET_BAWAAN)))
            ->pluck('nilai', 'kunci');

        return collect(self::TARGET_BAWAAN)
            ->mapWithKeys(fn ($bawaan, $k) => [$k => (float) ($tersimpan["target_nasional.{$k}"] ?? $bawaan)])->all();
    }

    /** @param  array{call: float, ec: float, ipt: float}  $nilai */
    public static function simpanTarget(array $nilai, ?User $user): void
    {
        foreach (array_keys(self::TARGET_BAWAAN) as $k) {
            DB::table('pengaturan_admin')->updateOrInsert(
                ['kunci' => "target_nasional.{$k}"],
                ['nilai' => (string) $nilai[$k], 'diubah_oleh' => $user?->id, 'updated_at' => now(), 'created_at' => now()],
            );
        }
    }

    public static function hariKerjaKoordinator(string $dari, string $sampai): int
    {
        if ($sampai < $dari) {
            return 0;
        }

        $libur = Pengaturan::libur($dari, $sampai);
        $zona = Pengaturan::zona();
        $jumlah = 0;

        for ($hari = CarbonImmutable::parse($dari, $zona), $akhir = CarbonImmutable::parse($sampai, $zona); $hari->lte($akhir); $hari = $hari->addDay()) {
            if (! isset($libur[$hari->toDateString()]) && in_array($hari->dayOfWeekIso, self::HARI_KOORDINATOR, true)) {
                $jumlah++;
            }
        }

        return $jumlah;
    }

    /**
     * Kartu per depo (diurutkan EC Achievement tertinggi), dibatasi cakupan akun dan pencarian nama depo.
     *
     * @return Collection<int, object>
     */
    public function kartu(string $cari = ''): Collection
    {
        $sales = $this->sales()->groupBy('depo_id');
        $koordinator = $this->koordinatorAktif()->groupBy('depo_id');
        $visit = $this->visit();
        $visitDepo = $visit->groupBy('depo_id');

        $ids = collect([...$sales->keys(), ...$koordinator->keys(), ...$visitDepo->keys()])
            ->filter()->map(fn ($id) => (int) $id)->unique();
        $cakupan = $this->depoCakupan();
        $aktif = DB::table('mk_depo')->where('aktif', true)
            ->when($cakupan !== null, fn ($q) => $q->whereIn('id', $cakupan ?: [0]))->pluck('id')->map(fn ($id) => (int) $id);

        return DB::table('mk_depo')->whereIn('id', $ids->merge($aktif)->unique()->values()->all() ?: [0])
            ->when($cari !== '', fn ($q) => $q->whereRaw('LOWER(nama) LIKE ?', ['%'.mb_strtolower($cari).'%']))
            ->get(['id', 'nama'])
            ->map(function ($d) use ($sales, $koordinator, $visitDepo) {
                $baris = $sales[$d->id] ?? collect();
                $v = $visitDepo[$d->id] ?? collect();

                return (object) array_merge(
                    ['id' => (int) $d->id, 'nama' => $d->nama, 'distributor' => $this->distributor()[$d->id] ?? null,
                        'total_sk' => count($koordinator[$d->id] ?? []), 'total_motoris' => $baris->where('aktif', true)->count()],
                    (array) $this->ringkas($baris),
                    ['sk_visit' => $v->pluck('koordinator_id')->unique()->count(), 'visit_sk' => $v->count(),
                        'avg_visit_sk' => $this->bagi($v->count(), $this->hkKoordinator, 1)],
                );
            })
            ->map(function ($k) {
                $k->avg_hari_kerja = $this->bagi($k->hari_kerja, $k->total_motoris, 1);

                return $k;
            })
            ->sortBy([['ec_achievement', 'desc'], ['nama', 'asc']])->values();
    }

    /**
     * Detail satu depo: ringkasan depo + per koordinator (tim berdasarkan koordinator sales), tiap sales.
     *
     * @return array{depo: object, total: object, tim: Collection<int, object>}|null
     */
    public function detail(int $depoId): ?array
    {
        $depo = $this->depo($depoId);

        if (! $depo) {
            return null;
        }

        $sales = $this->sales()->where('depo_id', $depoId)->values();
        $visit = $this->visit()->where('depo_id', $depoId)->countBy('koordinator_id');
        $nama = DB::table('mk_karyawan')
            ->whereIn('id', $sales->pluck('koordinator_id')->filter()->merge($visit->keys())->unique()->values()->all() ?: [0])
            ->pluck('nama', 'id');

        $tim = $sales->groupBy(fn ($s) => $s->koordinator_id ?? 0)
            ->union($visit->keys()->mapWithKeys(fn ($id) => [(int) $id => collect()])->diffKeys($sales->groupBy(fn ($s) => $s->koordinator_id ?? 0)))
            ->map(fn (Collection $anggota, $koordinatorId) => (object) [
                'koordinator_id' => (int) $koordinatorId ?: null,
                'nama' => $koordinatorId ? ($nama[$koordinatorId] ?? 'Koordinator #'.$koordinatorId) : '(Tanpa Koordinator)',
                'avg_visit' => $this->bagi((int) ($visit[$koordinatorId] ?? 0), $this->hkKoordinator, 1),
                'sales' => $anggota->sortBy([['aktif', 'desc'], ['nama', 'asc']])->values(),
                'akumulasi' => $this->ringkas($anggota),
            ])
            ->sortBy(fn ($t) => [$t->koordinator_id === null ? 1 : 0, $t->nama])->values();

        return [
            'depo' => $depo,
            'total' => $this->ringkas($sales),
            'tim' => $tim,
        ];
    }

    /** Identitas satu depo dalam cakupan akun (ringan, tanpa query motoris); null jika di luar cakupan atau tidak ada. */
    public function depo(int $depoId): ?object
    {
        $cakupan = $this->depoCakupan();

        if ($cakupan !== null && ! in_array($depoId, $cakupan, true)) {
            return null;
        }

        $depo = DB::table('mk_depo')->where('id', $depoId)->first(['id', 'nama']);

        return $depo ? (object) ['id' => (int) $depo->id, 'nama' => $depo->nama, 'distributor' => $this->distributor()[$depo->id] ?? null] : null;
    }

    /**
     * Daftar transaksi penjualan depo (tidak dibatalkan) untuk modal "Detail EC & Penjualan", plus totalnya.
     *
     * @return array{baris: LengthAwarePaginator, total: object}
     */
    public function penjualan(int $depoId, string $cari = '', int $perHalaman = 25): array
    {
        $q = $this->karyawan('sales')->where('k.depo_id', $depoId)
            ->join('trx_penjualan as p', 'p.karyawan_id', '=', 'k.id')
            ->leftJoin('mk_outlet as o', 'o.id', '=', 'p.outlet_id')
            ->whereBetween('p.tanggal', [$this->dari, $this->sampai])
            ->whereNull('p.dibatalkan_at');

        $total = (clone $q)->selectRaw('SUM(CASE WHEN p.is_effective_call THEN 1 ELSE 0 END) AS ec, '
            .'COALESCE(SUM(p.total), 0) AS sales, COALESCE(SUM(p.jumlah_sku), 0) AS sku, COALESCE(SUM(p.poin), 0) AS point')->first();

        $baris = $q
            ->when($cari !== '', function ($w) use ($cari) {
                $kata = '%'.mb_strtolower($cari).'%';
                $w->where(fn ($x) => $x->whereRaw('LOWER(k.nama) LIKE ?', [$kata])
                    ->orWhereRaw('LOWER(o.nama) LIKE ?', [$kata])->orWhereRaw('LOWER(o.kode) LIKE ?', [$kata]));
            })
            ->orderBy('p.tanggal')->orderBy('p.id')
            ->select(['p.tanggal', 'o.kode as kode_outlet', 'o.nama as nama_outlet', 'k.nama as sales', 'p.is_effective_call',
                'p.total', 'p.jumlah_sku', 'p.poin'])
            ->paginate($perHalaman)->withQueryString();

        return ['baris' => $baris, 'total' => (object) array_map('intval', (array) $total)];
    }

    /** Nilai kartu yang bisa dipilih di grafik per depo (kunci => label), sama dengan pilihan Marketama. */
    public const METRIK_GRAFIK = [
        'ec' => 'Total EC', 'call' => 'Total Call', 'sales' => 'Total Sales (Rp)', 'hari_kerja' => 'Total Hari Kerja',
        'sku' => 'Total SKU Terjual', 'point' => 'Total Point', 'avg_ec' => 'AVG EC / Hari Kerja', 'avg_call' => 'AVG Call / Hari Kerja',
        'avg_sales' => 'AVG Sales / Hari Kerja', 'avg_ipt' => 'AVG IPT', 'avg_hari_kerja' => 'AVG Hari Kerja / Motoris',
        'avg_point' => 'AVG Point / Hari Kerja', 'visit_sk' => 'Total Visit SK', 'avg_visit_sk' => 'AVG Visit SK / Hari',
        'total_sk' => 'Total SK Aktif', 'total_motoris' => 'Total Sales Motoris Aktif',
    ];

    // ------------------------------------------------------------------

    /** Ringkasan sekelompok sales: total dan rata-rata per hari kerja, plus achievement terhadap target nasional. */
    private function ringkas(Collection $sales): object
    {
        $hk = (int) $sales->sum('hari_kerja');
        $ec = (int) $sales->sum('ec');
        $r = (object) [
            'hari_kerja' => $hk,
            'call' => (int) $sales->sum('call'),
            'ec' => $ec,
            'sales' => (int) $sales->sum('sales'),
            'sku' => (int) $sales->sum('sku'),
            'point' => (int) $sales->sum('point'),
        ];

        $r->avg_call = $this->bagi($r->call, $hk, 1);
        $r->avg_ec = $this->bagi($ec, $hk, 1);
        $r->avg_sales = (int) round($this->bagi($r->sales, $hk, 2));
        $r->avg_ipt = $this->bagi($r->sku, $ec, 1);
        $r->avg_point = $this->bagi($r->point, $hk, 1);
        $r->call_achievement = $this->bagi($r->avg_call * 100, $this->target['call'], 1);
        $r->ec_achievement = $this->bagi($r->avg_ec * 100, $this->target['ec'], 1);
        $r->ipt_achievement = $this->bagi($r->avg_ipt * 100, $this->target['ipt'], 1);

        return $r;
    }

    private function bagi(float|int $a, float|int $b, int $desimal): float
    {
        return $b > 0 ? round($a / $b, $desimal) : 0.0;
    }

    /**
     * Angka per sales motoris dalam cakupan (aktif, atau tidak aktif tapi bekerja di rentang).
     *
     * @return Collection<int, object>
     */
    private function sales(): Collection
    {
        if ($this->sales !== null) {
            return $this->sales;
        }

        $rentang = [$this->dari, $this->sampai];
        $hari = DB::query()->fromSub(
            DB::table('trx_absensi')->whereBetween('tanggal', $rentang)->select(['karyawan_id', 'tanggal'])
                ->union(DB::table('trx_kunjungan')->whereBetween('tanggal', $rentang)->select(['karyawan_id', 'tanggal'])),
            'h',
        )->groupBy('karyawan_id')->selectRaw('karyawan_id, COUNT(*) AS hari');
        $call = DB::table('trx_kunjungan')->whereBetween('tanggal', $rentang)->groupBy('karyawan_id')->selectRaw('karyawan_id, COUNT(*) AS n');
        $jual = DB::table('trx_penjualan')->whereBetween('tanggal', $rentang)->whereNull('dibatalkan_at')->groupBy('karyawan_id')
            ->selectRaw('karyawan_id, SUM(CASE WHEN is_effective_call THEN 1 ELSE 0 END) AS ec, SUM(total) AS nilai, SUM(jumlah_sku) AS sku, SUM(poin) AS poin');

        return $this->sales = $this->karyawan('sales')
            ->leftJoinSub($hari, 'h', 'h.karyawan_id', '=', 'k.id')
            ->leftJoinSub($call, 'c', 'c.karyawan_id', '=', 'k.id')
            ->leftJoinSub($jual, 'j', 'j.karyawan_id', '=', 'k.id')
            ->whereNotNull('k.depo_id')
            ->where(fn ($w) => $w->where('k.aktif', true)->orWhereNotNull('h.karyawan_id')->orWhereNotNull('j.karyawan_id'))
            ->get(['k.id', 'k.nama', 'k.aktif', 'k.depo_id', 'k.koordinator_id', 'h.hari', 'c.n as call', 'j.ec', 'j.nilai', 'j.sku', 'j.poin'])
            ->map(function ($s) {
                $hk = (int) $s->hari;
                $o = (object) [
                    'id' => (int) $s->id, 'nama' => $s->nama, 'aktif' => (bool) $s->aktif,
                    'depo_id' => (int) $s->depo_id, 'koordinator_id' => $s->koordinator_id ? (int) $s->koordinator_id : null,
                    'hari_kerja' => $hk, 'call' => (int) $s->call, 'ec' => (int) $s->ec,
                    'sales' => (int) $s->nilai, 'sku' => (int) $s->sku, 'point' => (int) $s->poin,
                ];
                $o->avg_call = $this->bagi($o->call, $hk, 1);
                $o->avg_ec = $this->bagi($o->ec, $hk, 1);
                $o->avg_sales = (int) round($this->bagi($o->sales, $hk, 2));
                $o->avg_ipt = $this->bagi($o->sku, $o->ec, 1);
                $o->avg_point = $this->bagi($o->point, $hk, 1);

                return $o;
            });
    }

    /** Koordinator aktif per depo dalam cakupan. */
    private function koordinatorAktif(): Collection
    {
        return $this->karyawan('koordinator')->where('k.aktif', true)->whereNotNull('k.depo_id')->get(['k.id', 'k.depo_id']);
    }

    /** Join visit koordinator dalam cakupan pada rentang: koordinator_id + depo_id. */
    private function visit(): Collection
    {
        return $this->karyawan('koordinator')
            ->join('trx_join_visit as v', 'v.koordinator_id', '=', 'k.id')
            ->whereBetween('v.tanggal', [$this->dari, $this->sampai])
            ->get(['v.koordinator_id', DB::raw('COALESCE(v.depo_id, k.depo_id) AS depo_id')])
            ->map(fn ($v) => (object) ['koordinator_id' => (int) $v->koordinator_id, 'depo_id' => $v->depo_id === null ? null : (int) $v->depo_id]);
    }

    private function karyawan(string $kategori): Builder
    {
        return Akses::batasiKaryawan(DB::table('mk_karyawan as k')->where('k.kategori', $kategori), $this->user);
    }

    /** @return array<int>|null null = semua depo */
    private function depoCakupan(): ?array
    {
        if (Akses::cakupan($this->user) === 'semua') {
            return null;
        }

        return Akses::batasiKaryawan(DB::table('mk_karyawan as k')->whereNotNull('k.depo_id'), $this->user)
            ->distinct()->pluck('k.depo_id')->map(fn ($id) => (int) $id)->all();
    }

    /** Nama distributor pertama per depo (sub-judul kartu). */
    private function distributor(): array
    {
        return $this->distributor ??= DB::table('mk_distributor')->whereNotNull('depo_id')->orderBy('id')->get(['depo_id', 'nama'])
            ->unique('depo_id')->pluck('nama', 'depo_id')->all();
    }
}
