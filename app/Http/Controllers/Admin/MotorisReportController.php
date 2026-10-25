<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Akses\Akses;
use App\Support\Motoris\LaporanMotoris;
use Illuminate\Http\Request;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

// Menu Utama › Motoris Report: tab Coverage, Performance, Absensi dihitung dari tabel motoris (data HP
// dan riwayat aplikasi lama hasil migrasi), bukan lagi dari tabel laporan Marketama.
class MotorisReportController extends Controller
{
    public const MENU = 'motoris_report';

    public const TAB = ['coverage' => 'Coverage', 'performance' => 'Performance', 'absensi' => 'Absensi'];

    public function index(Request $request): Response
    {
        $tab = array_key_exists($request->query('tab'), self::TAB) ? $request->query('tab') : 'coverage';
        $bulan = KuotaMotorisController::bulan($request);
        $laporan = new LaporanMotoris($bulan, $request->user());
        $filter = [
            'depo' => $request->integer('depo') ?: null,
            'koordinator' => $request->integer('koordinator') ?: null,
            'q' => trim((string) $request->query('q')),
        ];

        return Inertia::render('Motoris/Report', [
            'slug' => self::MENU,
            'judul' => config('marketama.pages.'.self::MENU.'.title') ?? 'Motoris Report',
            'tab' => $tab,
            'tabs' => collect(self::TAB)->map(fn ($label, $kode) => ['key' => $kode, 'label' => $label])->values(),
            'bulan' => $bulan,
            'periode' => [
                'dari' => self::tanggal($laporan->dari),
                'sampai' => self::tanggal($laporan->sampai),
                'hkStandar' => $laporan->hkStandar,
            ],
            'filter' => $filter,
            'bolehKuota' => Akses::bisa($request->user(), KuotaMotorisController::MENU),
            'daftarDepo' => $tab === 'absensi' ? $this->daftarDepo($request) : [],
            'daftarKoordinator' => $tab === 'absensi'
                ? Akses::batasiKaryawan(DB::table('mk_karyawan as k')->where('k.kategori', 'koordinator')->where('k.aktif', true), $request->user())
                    ->orderBy('k.nama')->pluck('k.nama', 'k.id')
                : [],
            // Hitungan tab paling berat; ditunda supaya halaman tampil dulu (lihat Motoris/Report.vue).
            'laporan' => Inertia::defer(fn () => $this->muatLaporan($tab, $laporan, $filter), 'laporan'),
        ]);
    }

    /**
     * Baris dan total untuk tab aktif. Coverage & Performance: daftar baris per kota + total. Absensi: paginator.
     *
     * @return array{baris: mixed, total: ?object}
     */
    private function muatLaporan(string $tab, LaporanMotoris $laporan, array $filter): array
    {
        $data = match ($tab) {
            'coverage' => $laporan->coverage(),
            'performance' => $laporan->performance(),
            'absensi' => ['baris' => $laporan->absensi($filter)],
        };

        return [
            'baris' => $data['baris'] instanceof AbstractPaginator ? $data['baris'] : $data['baris']->values(),
            'total' => $data['total'] ?? null,
        ];
    }

    private static function tanggal(string $tanggal): string
    {
        return Carbon::parse($tanggal)->locale('id')->translatedFormat('d M Y');
    }

    private function daftarDepo(Request $request)
    {
        $cakupan = KuotaMotorisController::depoCakupan($request);

        return DB::table('mk_depo')->when($cakupan !== null, fn ($q) => $q->whereIn('id', $cakupan ?: [0]))
            ->orderBy('nama')->pluck('nama', 'id');
    }
}
