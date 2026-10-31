<?php

namespace App\Support\Menu\Motoris;

use App\Models\User;
use App\Support\Akses\Akses;
use App\Support\Menu\JamLokal;
use App\Support\Menu\SumberBerstatus;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Rekap Motoris Ditandai: semua tanda motoris (tanda_motoris, diberikan koordinator dari drawer Riwayat Lacak Motoris),
 * termasuk yang sudah dicabut sebagai jejak. Dibatasi cakupan role (alias k = motoris); bisa disaring menurut status.
 */
class SumberTandaMotoris implements SumberBerstatus
{
    private const STATUS = ['aktif' => 'Aktif', 'dicabut' => 'Dicabut'];

    public function tabelUtama(): string
    {
        return 'tanda_motoris';
    }

    public function query(?User $user): Builder
    {
        $q = DB::table('tanda_motoris as tm')
            ->join('mk_karyawan as k', 'k.id', '=', 'tm.karyawan_id')
            ->leftJoin('mk_depo as d', 'd.id', '=', 'k.depo_id')
            ->leftJoin('mk_karyawan as ko', 'ko.id', '=', 'k.koordinator_id')
            ->select([
                'tm.id', 'tm.karyawan_id', 'tm.tanggal', 'k.nama as motoris', 'd.nama as depo', 'ko.nama as koordinator',
                'tm.kategori', 'tm.catatan', 'tm.dibuat_nama as ditandai_oleh', 'tm.created_at', 'tm.dicabut_at',
            ])
            ->orderByDesc('tm.tanggal')->orderByDesc('tm.id');

        return Akses::batasiKaryawan($q, $user);
    }

    public function ubahBaris(object $baris): object
    {
        $baris->tanggal = substr((string) $baris->tanggal, 0, 10);
        $baris->kategori = config("marketama.tanda_motoris.{$baris->kategori}", $baris->kategori);
        $baris->ditandai_pada = JamLokal::format($baris->created_at, 'Y-m-d H:i');
        $baris->status = $baris->dicabut_at === null ? self::STATUS['aktif'] : self::STATUS['dicabut'];
        unset($baris->created_at, $baris->dicabut_at);

        return $baris;
    }

    public function kolomCari(): array
    {
        return ['k.nama', 'd.nama', 'ko.nama', 'tm.catatan', 'tm.dibuat_nama'];
    }

    public function pilihanStatus(): array
    {
        return self::STATUS;
    }

    public function batasiStatus(Builder $query, string $status): void
    {
        match ($status) {
            'aktif' => $query->whereNull('tm.dicabut_at'),
            'dicabut' => $query->whereNotNull('tm.dicabut_at'),
            default => null,
        };
    }
}
