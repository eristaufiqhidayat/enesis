<?php

namespace App\Support\Menu\Motoris;

use App\Models\User;
use App\Support\Akses\Akses;
use App\Support\Menu\SumberBerstatus;
use App\Support\Mobile\Pengaturan;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Tracking Last Seen Sales / Koordinator: posisi GPS terakhir tiap karyawan aktif — dari aplikasi HP (trx_tracking,
 * UTC) atau, bila lebih baru / belum pernah pakai HP, dari riwayat aplikasi lama (tracking_*_lokasi, jam WIB).
 */
abstract class SumberLastSeen implements SumberBerstatus
{
    /** sales | koordinator */
    abstract protected function kategori(): string;

    public function tabelUtama(): string
    {
        return 'trx_tracking';
    }

    /** Tabel riwayat aplikasi lama dan kolom id karyawannya. */
    private function tabelLama(): array
    {
        return $this->kategori() === 'koordinator' ? ['tracking_koordinator_lokasi', 'koordinator_id'] : ['tracking_sales_lokasi', 'sales_id'];
    }

    public function query(?User $user): Builder
    {
        $lama = $this->tabelLama();
        $hp = fn (string $kolom) => DB::table('trx_tracking as t')->whereColumn('t.karyawan_id', 'k.id')
            ->orderByDesc('t.recorded_at')->limit(1)->select("t.{$kolom}");
        $lm = fn (string $kolom) => DB::table("{$lama[0]} as l")->whereColumn("l.{$lama[1]}", 'k.id')
            ->orderByDesc('l.waktu')->limit(1)->select("l.{$kolom}");

        $q = DB::table('mk_karyawan as k')
            ->leftJoin('mk_depo as d', 'd.id', '=', 'k.depo_id')
            ->leftJoin('mk_karyawan as ko', 'ko.id', '=', 'k.koordinator_id')
            ->where('k.kategori', $this->kategori())->where('k.aktif', true)
            ->select(['k.id', 'k.nama', 'k.no_hp as telepon', 'd.nama as depo', 'ko.nama as koordinator'])
            ->selectSub($hp('recorded_at'), 'hp_waktu')->selectSub($hp('lat'), 'hp_lat')->selectSub($hp('lng'), 'hp_lng')
            ->selectSub($hp('baterai'), 'baterai')->selectSub($hp('mock_location'), 'mock')
            ->selectSub($lm('waktu'), 'lama_waktu')->selectSub($lm('lat'), 'lama_lat')->selectSub($lm('lng'), 'lama_lng')
            // Yang pernah terlihat di HP dulu (terbaru di atas), lalu urut nama.
            ->orderByRaw('CASE WHEN EXISTS (SELECT 1 FROM trx_tracking tx WHERE tx.karyawan_id = k.id) THEN 0 ELSE 1 END')
            ->orderByDesc(DB::table('trx_tracking as ty')->whereColumn('ty.karyawan_id', 'k.id')->selectRaw('MAX(ty.recorded_at)'))
            ->orderBy('k.nama');

        // Lencana "Ditandai" (Lacak Motoris): tanda motoris aktif dalam jendela hari terakhir, jumlah dan tanggal terbarunya.
        if ($this->kategori() === 'sales') {
            $awal = Pengaturan::sekarang()->subDays((int) config('marketama.tanda_motoris_hari') - 1)->toDateString();
            $tanda = fn (string $agregat) => DB::table('tanda_motoris as tm')->whereColumn('tm.karyawan_id', 'k.id')
                ->whereNull('tm.dicabut_at')->where('tm.tanggal', '>=', $awal)->selectRaw($agregat);
            $q->selectSub($tanda('COUNT(*)'), 'ditandai')->selectSub($tanda('MAX(tm.tanggal)'), 'ditandai_tanggal');
        }

        return Akses::batasiKaryawan($q, $user);
    }

    public function ubahBaris(object $baris): object
    {
        $zona = Pengaturan::zona();
        $hp = $baris->hp_waktu ? CarbonImmutable::parse((string) $baris->hp_waktu, 'UTC')->setTimezone($zona) : null;
        $lama = $baris->lama_waktu ? CarbonImmutable::parse((string) $baris->lama_waktu, $zona) : null;
        $pakaiHp = $hp && (! $lama || $hp->gte($lama));
        $waktu = $pakaiHp ? $hp : $lama;

        $baris->terakhir_terlihat = $waktu?->format('Y-m-d H:i:s') ?? 'Belum ada data';
        $baris->sejak = $waktu ? $waktu->locale('id')->diffForHumans() : null;
        $titik = fn ($n) => $n === null ? null : (float) $n;
        $baris->latitude = $titik($pakaiHp ? $baris->hp_lat : ($lama ? $baris->lama_lat : null));
        $baris->longitude = $titik($pakaiHp ? $baris->hp_lng : ($lama ? $baris->lama_lng : null));
        $baris->baterai = $pakaiHp && $baris->baterai !== null ? $baris->baterai.'%' : null;
        $baris->lokasi_palsu = $pakaiHp && $baris->mock ? 'Ya' : null;
        $baris->sumber = $waktu ? ($pakaiHp ? 'Aplikasi HP' : 'Aplikasi lama') : null;
        $baris->status = self::STATUS[$this->statusKunci($waktu)];

        if (property_exists($baris, 'ditandai')) {
            $baris->ditandai = (int) $baris->ditandai;
            $baris->ditandai_tanggal = $baris->ditandai_tanggal ? substr((string) $baris->ditandai_tanggal, 0, 10) : null;
        }

        unset($baris->hp_waktu, $baris->hp_lat, $baris->hp_lng, $baris->lama_waktu, $baris->lama_lat, $baris->lama_lng, $baris->mock);

        return $baris;
    }

    /** Status kebaruan (kunci => label): batas yang sama dengan status di Peta Sebaran Sales. */
    public const STATUS = ['hari_ini' => 'Hari ini', 'minggu' => '1–7 hari lalu', 'lama' => 'Lebih dari 7 hari', 'belum' => 'Belum ada data'];

    private function statusKunci(?CarbonImmutable $waktu): string
    {
        $hariIni = Pengaturan::sekarang()->startOfDay();

        return match (true) {
            ! $waktu => 'belum',
            $waktu->gte($hariIni) => 'hari_ini',
            $waktu->gte($hariIni->subDays(7)) => 'minggu',
            default => 'lama',
        };
    }

    public function pilihanStatus(): array
    {
        return self::STATUS;
    }

    public function batasiStatus(Builder $query, string $status): void
    {
        $hariIni = Pengaturan::sekarang()->startOfDay();

        switch ($status) {
            case 'hari_ini':
                $this->terlihatSejak($query, $hariIni, true);
                break;
            case 'minggu':
                $this->terlihatSejak($query, $hariIni->subDays(7), true);
                $this->terlihatSejak($query, $hariIni, false);
                break;
            case 'lama':
                $this->terlihatSejak($query, null, true);
                $this->terlihatSejak($query, $hariIni->subDays(7), false);
                break;
            case 'belum':
                $this->terlihatSejak($query, null, false);
                break;
        }
    }

    /**
     * Saring karyawan yang (tidak) punya titik GPS sejak $batas (null = kapan pun). Waktu efektif adalah yang lebih
     * baru antara titik HP (UTC) dan titik lama (zona aplikasi), jadi "terlihat sejak X" berarti salah satunya >= X
     * dan "tidak terlihat sejak X" berarti keduanya < X — tanpa konversi zona waktu di SQL.
     */
    private function terlihatSejak(Builder $query, ?CarbonImmutable $batas, bool $terlihat): void
    {
        [$tabelLama, $kolomId] = $this->tabelLama();
        $hp = fn (Builder $b) => $b->selectRaw('1')->from('trx_tracking as tx')->whereColumn('tx.karyawan_id', 'k.id')
            ->when($batas, fn ($w) => $w->where('tx.recorded_at', '>=', $batas->utc()->format('Y-m-d H:i:s')));
        $lama = fn (Builder $b) => $b->selectRaw('1')->from("{$tabelLama} as lx")->whereColumn("lx.{$kolomId}", 'k.id')
            ->when($batas, fn ($w) => $w->where('lx.waktu', '>=', $batas->format('Y-m-d H:i:s')));

        $terlihat
            ? $query->where(fn ($w) => $w->whereExists($hp)->orWhereExists($lama))
            : $query->whereNotExists($hp)->whereNotExists($lama);
    }

    public function kolomCari(): array
    {
        return ['k.nama', 'd.nama', 'ko.nama', 'k.no_hp'];
    }
}
