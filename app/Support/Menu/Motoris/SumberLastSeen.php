<?php

namespace App\Support\Menu\Motoris;

use App\Models\User;
use App\Support\Akses\Akses;
use App\Support\Menu\SumberTabel;
use App\Support\Mobile\Pengaturan;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Tracking Last Seen Sales / Koordinator: posisi GPS terakhir tiap karyawan aktif — dari aplikasi HP (trx_tracking,
 * UTC) atau, bila lebih baru / belum pernah pakai HP, dari riwayat aplikasi lama (tracking_*_lokasi, jam WIB).
 */
abstract class SumberLastSeen implements SumberTabel
{
    /** sales | koordinator */
    abstract protected function kategori(): string;

    public function tabelUtama(): string
    {
        return 'trx_tracking';
    }

    public function query(?User $user): Builder
    {
        $koordinator = $this->kategori() === 'koordinator';
        $lama = $koordinator ? ['tracking_koordinator_lokasi', 'koordinator_id'] : ['tracking_sales_lokasi', 'sales_id'];
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
        $baris->sejak = $waktu ? $waktu->locale('id')->diffForHumans(Pengaturan::sekarang()) : null;
        $titik = fn ($n) => $n === null ? null : (float) $n;
        $baris->latitude = $titik($pakaiHp ? $baris->hp_lat : ($lama ? $baris->lama_lat : null));
        $baris->longitude = $titik($pakaiHp ? $baris->hp_lng : ($lama ? $baris->lama_lng : null));
        $baris->baterai = $pakaiHp && $baris->baterai !== null ? $baris->baterai.'%' : null;
        $baris->lokasi_palsu = $pakaiHp && $baris->mock ? 'Ya' : null;
        $baris->sumber = $waktu ? ($pakaiHp ? 'Aplikasi HP' : 'Aplikasi lama') : null;
        unset($baris->hp_waktu, $baris->hp_lat, $baris->hp_lng, $baris->lama_waktu, $baris->lama_lat, $baris->lama_lng, $baris->mock);

        return $baris;
    }

    public function kolomCari(): array
    {
        return ['k.nama', 'd.nama', 'ko.nama', 'k.no_hp'];
    }
}
