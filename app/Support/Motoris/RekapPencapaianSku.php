<?php

namespace App\Support\Motoris;

use App\Models\User;
use App\Support\Akses\Akses;
use App\Support\Mobile\Pengaturan;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

// Rekap Pencapaian SKU (tab Per Motoris & Per Depo) dari penjualan aplikasi motoris (trx_penjualan_item,
// transaksi tidak dibatalkan) + master karyawan/depo/produk. Rumus mengikuti Marketama:
// - Per Motoris: Avg/Day = qty ÷ HK standar; TOTAL tabel = Σ qty ÷ (jumlah motoris di tabel × HK).
// - Per Depo:    Avg/Day/Motoris = qty ÷ hari-motoris bertransaksi (pasangan motoris × tanggal dengan qty > 0);
//                TOTAL tabel = Σ qty ÷ Σ hari-motoris. Kolom Hari Kerja hanya informasi (HK standar).
// Kategori: Avg ≥ target → Tercapai, selain itu Belum. Target satu angka global (Setting Target).
class RekapPencapaianSku
{
    public const TARGET_BAWAAN = 5;

    public const KUNCI_TARGET = 'rekap_pencapaian_sku.target';

    public readonly int $hkStandar;

    public readonly float $target;

    /**
     * @param  array<int>  $depo  filter depo (kosong = semua dalam cakupan)
     * @param  array<int>  $produk  filter produk (kosong = semua)
     */
    public function __construct(
        public readonly string $dari,
        public readonly string $sampai,
        public readonly array $depo = [],
        public readonly array $produk = [],
        private readonly ?User $user = null,
    ) {
        $this->hkStandar = LaporanMotoris::hariKerja($dari, $sampai);
        $this->target = self::target();
    }

    public static function target(): float
    {
        $nilai = DB::table('pengaturan_admin')->where('kunci', self::KUNCI_TARGET)->value('nilai');

        return $nilai === null ? (float) self::TARGET_BAWAAN : (float) $nilai;
    }

    public static function simpanTarget(float $nilai, ?User $user): void
    {
        DB::table('pengaturan_admin')->updateOrInsert(
            ['kunci' => self::KUNCI_TARGET],
            ['nilai' => (string) $nilai, 'diubah_oleh' => $user?->id, 'updated_at' => now(), 'created_at' => now()],
        );
    }

    /**
     * Per sales motoris: sales aktif dalam cakupan, ditambah sales nonaktif yang masih menjual di rentang.
     *
     * @return array{tercapai: array{baris: Collection, total: object}, belum: array{baris: Collection, total: object}, jumlah: int}
     */
    public function perMotoris(): array
    {
        $qty = DB::query()->fromSub($this->qtyPerMotorisHari(), 'h')->groupBy('h.karyawan_id')->selectRaw('h.karyawan_id, SUM(h.qty) AS qty');

        $baris = $this->sales()
            ->leftJoinSub($qty, 'j', 'j.karyawan_id', '=', 'k.id')
            ->leftJoin('mk_depo as d', 'd.id', '=', 'k.depo_id')
            ->where(fn ($w) => $w->where('k.aktif', true)->orWhereNotNull('j.karyawan_id'))
            ->get(['k.id', 'k.nama', 'k.aktif', 'd.nama as depo', DB::raw('COALESCE(j.qty, 0) AS qty')])
            ->map(function ($b) {
                $qty = (float) $b->qty;
                $avg = $this->hkStandar > 0 ? round($qty / $this->hkStandar, 2) : 0.0;

                return (object) [
                    'id' => (int) $b->id,
                    'nama' => $b->nama,
                    'aktif' => (bool) $b->aktif,
                    'depo' => $b->depo,
                    'qty' => $qty,
                    'hari_kerja' => $this->hkStandar,
                    'avg' => $avg,
                    'tercapai' => $this->tercapai($avg),
                ];
            })
            ->sortBy([['qty', 'desc'], ['nama', 'asc']])->values();

        $total = function (Collection $grup) {
            $qty = (float) $grup->sum('qty');
            $pembagi = $grup->count() * $this->hkStandar;

            return (object) ['qty' => $qty, 'hari_kerja' => $this->hkStandar, 'avg' => $pembagi > 0 ? round($qty / $pembagi, 2) : 0.0];
        };

        return $this->bagi($baris, $total);
    }

    /**
     * Per depo: depo dalam cakupan yang punya sales motoris aktif atau penjualan di rentang.
     *
     * @return array{tercapai: array{baris: Collection, total: object}, belum: array{baris: Collection, total: object}, jumlah: int}
     */
    public function perDepo(): array
    {
        $harian = $this->qtyPerMotorisHari();
        $jual = DB::query()->fromSub($harian, 'h')->groupBy('h.depo_id')
            ->selectRaw('h.depo_id, SUM(h.qty) AS qty, SUM(CASE WHEN h.qty > 0 THEN 1 ELSE 0 END) AS hari_motoris')
            ->get()->keyBy('depo_id');
        $aktif = $this->sales()->where('k.aktif', true)->whereNotNull('k.depo_id')->distinct()->pluck('k.depo_id')
            ->map(fn ($id) => (int) $id)->all();
        $ids = array_values(array_unique([...$aktif, ...$jual->keys()->filter()->map(fn ($id) => (int) $id)->all()]));

        $baris = DB::table('mk_depo')->whereIn('id', $ids ?: [0])->get(['id', 'nama'])
            ->map(function ($d) use ($jual) {
                $qty = (float) ($jual[$d->id]->qty ?? 0);
                $hariMotoris = (int) ($jual[$d->id]->hari_motoris ?? 0);
                $avg = $hariMotoris > 0 ? round($qty / $hariMotoris, 2) : 0.0;

                return (object) [
                    'id' => (int) $d->id,
                    'nama' => $d->nama,
                    'qty' => $qty,
                    'hari_kerja' => $this->hkStandar,
                    'hari_motoris' => $hariMotoris,
                    'avg' => $avg,
                    'tercapai' => $this->tercapai($avg),
                ];
            })
            ->sortBy([['qty', 'desc'], ['nama', 'asc']])->values();

        $total = function (Collection $grup) {
            $qty = (float) $grup->sum('qty');
            $hariMotoris = (int) $grup->sum('hari_motoris');

            return (object) ['qty' => $qty, 'hari_kerja' => $this->hkStandar, 'hari_motoris' => $hariMotoris,
                'avg' => $hariMotoris > 0 ? round($qty / $hariMotoris, 2) : 0.0];
        };

        return $this->bagi($baris, $total);
    }

    // ------------------------------------------------------------------

    private function tercapai(float $avg): bool
    {
        return $avg >= $this->target;
    }

    private function bagi(Collection $baris, callable $total): array
    {
        [$tercapai, $belum] = $baris->partition(fn ($b) => $b->tercapai);

        return [
            'tercapai' => ['baris' => $tercapai->values(), 'total' => $total($tercapai)],
            'belum' => ['baris' => $belum->values(), 'total' => $total($belum)],
            'jumlah' => $baris->count(),
        ];
    }

    /** Qty terjual per sales motoris per tanggal (depo = depo karyawan), produk & depo sesuai filter. */
    private function qtyPerMotorisHari(): Builder
    {
        return $this->sales()
            ->join('trx_penjualan as p', 'p.karyawan_id', '=', 'k.id')
            ->join('trx_penjualan_item as i', 'i.penjualan_id', '=', 'p.id')
            ->whereBetween('p.tanggal', [$this->dari, $this->sampai])
            ->whereNull('p.dibatalkan_at')
            ->when($this->produk, fn ($q) => $q->whereIn('i.produk_id', $this->produk))
            ->groupBy('k.id', 'k.depo_id', 'p.tanggal')
            ->selectRaw('k.id AS karyawan_id, k.depo_id, p.tanggal, SUM(i.qty) AS qty');
    }

    /** Karyawan kategori sales dalam cakupan akun dan filter depo (alias k). */
    private function sales(): Builder
    {
        return Akses::batasiKaryawan(DB::table('mk_karyawan as k')->where('k.kategori', 'sales'), $this->user)
            ->when($this->depo, fn ($q) => $q->whereIn('k.depo_id', $this->depo));
    }

    /** Rentang bawaan: hari ini (WIB). */
    public static function hariIni(): string
    {
        return Pengaturan::sekarang()->toDateString();
    }
}
