<?php

namespace App\Support\Menu\Motoris;

use App\Models\User;
use App\Support\Akses\Akses;
use App\Support\Menu\SumberBertanggal;
use App\Support\Mobile\Pengaturan;
use App\Support\Motoris\Insentif;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Lap. Insentif Sales: perkiraan insentif per sales motoris untuk bulan yang memuat tanggal terpilih, dihitung dengan
 * aturan yang sama dengan aplikasi HP (me/performance): insentif EC dari Rumus Insentif Sales (total EC sebulan) +
 * nilai poin dari Konversi Poin Per Depo. Insentif absensi belum punya aturan di Enesis *(konfirmasi)*.
 */
class SumberInsentifSales implements SumberBertanggal
{
    public function tabelUtama(): string
    {
        return 'trx_penjualan';
    }

    public function query(?User $user): Builder
    {
        return $this->queryTanggal($user, Pengaturan::sekarang()->toDateString(), ['depo' => null, 'koordinator' => null]);
    }

    public function queryTanggal(?User $user, string $tanggal, array $filter): Builder
    {
        [$dari, $sampai] = $this->bulan($tanggal);
        $jual = DB::table('trx_penjualan')->whereBetween('tanggal', [$dari, $sampai])->whereNull('dibatalkan_at')->groupBy('karyawan_id')
            ->selectRaw('karyawan_id, SUM(CASE WHEN is_effective_call THEN 1 ELSE 0 END) AS ec, COALESCE(SUM(poin), 0) AS poin');

        $q = DB::table('mk_karyawan as k')
            ->leftJoinSub($jual, 'j', 'j.karyawan_id', '=', 'k.id')
            ->leftJoin('mk_depo as d', 'd.id', '=', 'k.depo_id')
            ->leftJoin('mk_karyawan as ko', 'ko.id', '=', 'k.koordinator_id')
            ->where('k.kategori', 'sales')
            ->where(fn ($w) => $w->where('k.aktif', true)->orWhereNotNull('j.karyawan_id'))
            ->when($filter['depo'] ?? null, fn ($w, $depo) => $w->where('k.depo_id', $depo))
            ->when($filter['koordinator'] ?? null, fn ($w, $ko) => $w->where('k.koordinator_id', $ko))
            ->orderByDesc(DB::raw('COALESCE(j.ec, 0)'))->orderBy('k.nama')
            ->select(['k.id', 'k.nama as nama_sales', 'k.no_hp as telepon', 'ko.nama as koordinator', 'd.nama as depo',
                DB::raw('COALESCE(j.ec, 0) AS total_ec'), DB::raw('COALESCE(j.poin, 0) AS total_poin')])
            ->selectRaw('? AS periode_awal', [$dari]);

        return Akses::batasiKaryawan($q, $user);
    }

    public function kpi(?User $user, string $tanggal, array $filter): array
    {
        [$dari] = $this->bulan($tanggal);
        $baris = $this->queryTanggal($user, $tanggal, $filter)->get();
        $ec = $baris->sum(fn ($b) => (int) Insentif::ec((int) $b->total_ec));

        return [
            ['label' => 'Periode', 'value' => CarbonImmutable::parse($dari)->locale('id')->translatedFormat('F Y')],
            ['label' => 'Sales', 'value' => $baris->count()],
            ['label' => 'Total EC', 'value' => (int) $baris->sum('total_ec')],
            ['label' => 'Insentif EC', 'value' => 'Rp '.number_format($ec, 0, ',', '.')],
        ];
    }

    public function keterangan(): ?string
    {
        return 'Perkiraan insentif bulan yang memuat tanggal terpilih, dari penjualan aplikasi motoris dan riwayat aplikasi lama. '
            .'Insentif EC memakai Rumus Insentif Sales (total EC sebulan); nilai poin memakai Konversi Poin Per Depo '
            .'(poin transaksi aplikasi lama tidak tercatat). Insentif absensi belum dihitung.';
    }

    public function pilihanFilter(?User $user): array
    {
        $depo = Akses::batasiKaryawan(DB::table('mk_karyawan as k')->where('k.kategori', 'sales')->whereNotNull('k.depo_id'), $user)
            ->distinct()->pluck('k.depo_id');

        return [
            'depo' => DB::table('mk_depo')->whereIn('id', $depo->all() ?: [0])->orderBy('nama')->pluck('nama', 'id')->all(),
            'koordinator' => Akses::batasiKaryawan(DB::table('mk_karyawan as k')->where('k.kategori', 'koordinator')->where('k.aktif', true), $user)
                ->orderBy('k.nama')->pluck('k.nama', 'k.id')->all(),
        ];
    }

    public function ubahBaris(object $baris): object
    {
        $awal = CarbonImmutable::parse((string) $baris->periode_awal);
        $ec = Insentif::ec((int) $baris->total_ec);
        $poin = Insentif::nilaiPoin($baris->depo, (int) $baris->total_poin);
        $rp = fn (?int $n) => $n === null ? '-' : 'Rp '.number_format($n, 0, ',', '.');

        $baris->no_payroll = 'INS-'.$awal->format('Ym').'-'.$baris->id;
        $baris->periode = $awal->locale('id')->translatedFormat('F Y');
        $baris->insentif_ec = $rp($ec);
        $baris->insentif_poin = $rp($poin);
        $baris->insentif_absensi = '-';
        $baris->total_insentif = $rp(($ec ?? 0) + ($poin ?? 0));
        unset($baris->periode_awal);

        return $baris;
    }

    public function kolomCari(): array
    {
        return ['k.nama', 'ko.nama', 'd.nama', 'k.no_hp'];
    }

    /** @return array{0: string, 1: string} */
    private function bulan(string $tanggal): array
    {
        $t = CarbonImmutable::parse($tanggal);

        return [$t->startOfMonth()->toDateString(), $t->endOfMonth()->toDateString()];
    }
}
