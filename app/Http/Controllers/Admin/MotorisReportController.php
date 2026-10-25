<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Akses\Akses;
use App\Support\Motoris\LaporanMotoris;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

// Menu Utama › Motoris Report: tab Coverage, Performance, Absensi dihitung dari tabel motoris (data HP
// dan riwayat aplikasi lama hasil migrasi), bukan lagi dari tabel laporan Marketama.
class MotorisReportController extends Controller
{
    public const MENU = 'motoris_report';

    public const TAB = ['coverage' => 'Coverage', 'performance' => 'Performance', 'absensi' => 'Absensi'];

    public function index(Request $request): View
    {
        $tab = array_key_exists($request->query('tab'), self::TAB) ? $request->query('tab') : 'coverage';
        $bulan = KuotaMotorisController::bulan($request);
        $laporan = new LaporanMotoris($bulan, $request->user());
        $filter = [
            'depo' => $request->integer('depo') ?: null,
            'koordinator' => $request->integer('koordinator') ?: null,
            'q' => trim((string) $request->query('q')),
        ];

        $data = match ($tab) {
            'coverage' => $laporan->coverage(),
            'performance' => $laporan->performance(),
            'absensi' => ['baris' => $laporan->absensi($filter)],
        };

        return view('motoris.report', $data + [
            'menu' => self::MENU,
            'judul' => config('marketama.pages.'.self::MENU.'.title') ?? 'Motoris Report',
            'tab' => $tab,
            'bulan' => $bulan,
            'laporan' => $laporan,
            'filter' => $filter,
            'bolehKuota' => Akses::bisa($request->user(), KuotaMotorisController::MENU),
            'daftarDepo' => $tab === 'absensi' ? $this->daftarDepo($request) : collect(),
            'daftarKoordinator' => $tab === 'absensi'
                ? Akses::batasiKaryawan(DB::table('mk_karyawan as k')->where('k.kategori', 'koordinator')->where('k.aktif', true), $request->user())
                    ->orderBy('k.nama')->pluck('k.nama', 'k.id')
                : collect(),
        ]);
    }

    private function daftarDepo(Request $request)
    {
        $cakupan = KuotaMotorisController::depoCakupan($request);

        return DB::table('mk_depo')->when($cakupan !== null, fn ($q) => $q->whereIn('id', $cakupan ?: [0]))
            ->orderBy('nama')->pluck('nama', 'id');
    }
}
