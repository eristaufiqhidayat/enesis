<?php

namespace App\Support\Menu;

use App\Models\User;
use App\Support\Akses\Akses;
use App\Support\Mobile\Pengaturan;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Monitoring Harian Sales › Sales Checkin Outlet Pagi, sama dengan Marketama: sales motoris aktif yang
 * tidak punya checkin outlet (trx_kunjungan, data HP + riwayat lama hasil migrasi) dengan jam checkin
 * >= mobile.checkin_pagi_mulai dan < mobile.checkin_pagi_selesai (WIB) pada tanggal yang dipilih.
 * Kolom Checkin Pertama = jam kunjungan pertama hari itu (di luar rentang) atau "Belum checkin".
 * Hari Minggu dan libur tetap ditampilkan seperti di Marketama.
 */
class SumberCheckinPagi implements SumberBertanggal
{
    public const BELUM = 'Belum checkin';

    public const LUAR_RENTANG = '(di luar rentang waktu)';

    public function tabelUtama(): string
    {
        return 'trx_kunjungan';
    }

    public function query(?User $user): Builder
    {
        return $this->queryTanggal($user, Pengaturan::sekarang()->toDateString(), ['depo' => null, 'koordinator' => null]);
    }

    public function queryTanggal(?User $user, string $tanggal, array $filter): Builder
    {
        $pertama = DB::table('trx_kunjungan')->where('tanggal', $tanggal)
            ->groupBy('karyawan_id')->selectRaw('karyawan_id, MIN(checkin_at) AS pertama');

        return $this->salesAktif($user, $filter)
            ->leftJoin('mk_depo as d', 'd.id', '=', 'k.depo_id')
            ->leftJoin('mk_karyawan as ko', 'ko.id', '=', 'k.koordinator_id')
            ->leftJoinSub($pertama, 'p', 'p.karyawan_id', '=', 'k.id')
            ->whereNotExists($this->checkinDalamRentang($tanggal))
            ->orderBy('d.nama')->orderBy('k.nama')
            ->select(['k.id', 'd.nama as depo', 'ko.nama as koordinator', 'k.nama as nama_sales', 'p.pertama as checkin_pertama_hari_ini']);
    }

    public function kpi(?User $user, string $tanggal, array $filter): array
    {
        $daftar = $this->queryTanggal($user, $tanggal, $filter);
        [$mulai, $selesai] = $this->jam();

        return [
            ['label' => 'Sales aktif', 'value' => $this->salesAktif($user, $filter)->count()],
            ['label' => "Checkin {$mulai}–{$selesai}", 'value' => $this->salesAktif($user, $filter)->whereExists($this->checkinDalamRentang($tanggal))->count()],
            ['label' => 'Checkin di luar rentang', 'value' => (clone $daftar)->whereNotNull('p.pertama')->count()],
            ['label' => 'Belum checkin', 'value' => (clone $daftar)->whereNull('p.pertama')->count()],
        ];
    }

    public function keterangan(): ?string
    {
        [$mulai, $selesai] = $this->jam();

        return "Menampilkan sales motoris aktif yang tidak memiliki checkin outlet antara jam {$mulai} dan {$selesai} pada tanggal yang dipilih "
            .'(data aplikasi motoris dan riwayat aplikasi lama). Kolom Checkin Pertama menunjukkan waktu checkin pertama hari itu, bila ada, di luar rentang tersebut.';
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
        $baris->checkin_pertama_hari_ini = $baris->checkin_pertama_hari_ini
            ? JamLokal::format($baris->checkin_pertama_hari_ini, 'H:i').' '.self::LUAR_RENTANG
            : self::BELUM;

        return $baris;
    }

    public function kolomCari(): array
    {
        return ['k.nama', 'd.nama', 'ko.nama'];
    }

    // ------------------------------------------------------------------

    /** Sales motoris aktif dalam cakupan akun, disaring depo / koordinator (alias k). */
    private function salesAktif(?User $user, array $filter): Builder
    {
        $q = DB::table('mk_karyawan as k')->where('k.kategori', 'sales')->where('k.aktif', true)
            ->when($filter['depo'] ?? null, fn ($w, $depo) => $w->where('k.depo_id', $depo))
            ->when($filter['koordinator'] ?? null, fn ($w, $koor) => $w->where('k.koordinator_id', $koor));

        return Akses::batasiKaryawan($q, $user);
    }

    /** Subquery: ada kunjungan sales k dengan checkin_at (UTC) di rentang jam pagi WIB tanggal itu. */
    private function checkinDalamRentang(string $tanggal): \Closure
    {
        [$mulai, $selesai] = $this->jam();
        $zona = Pengaturan::zona();
        $utc = fn (string $jam) => CarbonImmutable::parse("{$tanggal} {$jam}", $zona)->utc()->format('Y-m-d H:i:s');
        [$dari, $sampai] = [$utc($mulai), $utc($selesai)];

        return fn ($w) => $w->select(DB::raw(1))->from('trx_kunjungan as t')->whereColumn('t.karyawan_id', 'k.id')
            ->where('t.checkin_at', '>=', $dari)->where('t.checkin_at', '<', $sampai);
    }

    /** @return array{0: string, 1: string} jam mulai & selesai (HH:MM) dari config mobile */
    private function jam(): array
    {
        $rapi = fn ($v, $bawaan) => preg_match('/^(\d{1,2})[:.](\d{2})$/', trim((string) $v), $m) ? sprintf('%02d:%s', $m[1], $m[2]) : $bawaan;

        return [$rapi(config('mobile.checkin_pagi_mulai'), '09:00'), $rapi(config('mobile.checkin_pagi_selesai'), '11:00')];
    }
}
