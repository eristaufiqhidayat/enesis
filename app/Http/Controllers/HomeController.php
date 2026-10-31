<?php

namespace App\Http\Controllers;

use App\Support\Akses\Akses;
use App\Support\Mobile\Pengaturan;
use App\Support\Motoris\Dashboard;
use App\Support\Motoris\SummaryDepo;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

// Menu Utama › Beranda: dashboard interaktif ringkasan nasional data motoris (KPI, funnel outlet, tren
// penjualan harian, depo teratas), menggantikan halaman placeholder generik slug "home" (lihat
// MenuController) yang sebelumnya tidak punya tabel (config('marketama.pages.home.tables') === []).
class HomeController extends Controller
{
    public const MENU = 'home';

    private const CACHE_TTL = 300; // detik, sama dengan Dashboard::CACHE_TTL.

    public function index(Request $request): Response
    {
        [$dari, $sampai] = self::rentang($request);
        $summary = new SummaryDepo($dari, $sampai, $request->user());
        $dashboard = new Dashboard($dari, $sampai, $request->user());

        return Inertia::render('Home/Dashboard', [
            'slug' => self::MENU,
            'judul' => config('marketama.pages.'.self::MENU.'.title') ?? 'Beranda',
            'periode' => self::periode($summary),
            'filter' => ['date_from' => $dari, 'date_to' => $sampai],
            'target' => $summary->target,
            // Berat (query semua depo/outlet/tren); ditunda supaya halaman tampil dulu (lihat Home/Dashboard.vue).
            // kpi & topDepo digabung jadi satu prop (ringkasanDepo) supaya SummaryDepo::kartu() — yang query
            // semua depo/sales — cuma dihitung sekali, bukan sekali per prop.
            'ringkasanDepo' => Inertia::defer(fn () => self::ringkasanDepo($summary, $request), 'ringkasan'),
            'funnel' => Inertia::defer(fn () => $dashboard->funnelOutlet(), 'ringkasan'),
            'tren' => Inertia::defer(fn () => $dashboard->trenHarian(), 'ringkasan'),
        ]);
    }

    /**
     * kpi (KPI nasional) + topDepo (8 depo EC Achievement tertinggi), dari satu kartu() yang sama — lihat catatan
     * di atas. Hasilnya singkat di-cache per rentang tanggal + cakupan akun, sama seperti Dashboard::funnelOutlet.
     *
     * @return array{kpi: array<string, int|float>, topDepo: array<int, array<string, mixed>>}
     */
    private static function ringkasanDepo(SummaryDepo $summary, Request $request): array
    {
        $cakupan = Akses::cakupan($request->user()) === 'semua' ? 'semua' : 'user_'.($request->user()?->id ?? 0);
        $kunci = "beranda:depo:{$summary->dari}:{$summary->sampai}:{$cakupan}";

        return Cache::remember($kunci, self::CACHE_TTL, function () use ($summary) {
            $kartu = $summary->kartu();

            // topDepo diubah ke array murni (bukan Collection berisi stdClass): aman di-cache lewat PHP
            // serialize() apa pun cache driver-nya, dan Inertia men-JSON-kan array persis sama dengan Collection.
            return [
                'kpi' => self::kpi($kartu, $summary->target),
                'topDepo' => $kartu->take(8)->values()->map(fn ($d) => (array) $d)->all(),
            ];
        });
    }

    /**
     * KPI nasional: total dari kartu per depo (lihat SummaryDepo::kartu), lalu AVG dan achievement dihitung
     * ulang dari total gabungan — rumus yang sama dengan SummaryDepo::ringkas, hanya pada skala nasional.
     *
     * @param  array{call: float, ec: float, ipt: float}  $target
     * @return array<string, int|float>
     */
    private static function kpi(Collection $kartu, array $target): array
    {
        $bagi = fn (float|int $a, float|int $b, int $d = 1): float => $b > 0 ? round($a / $b, $d) : 0.0;

        $hariKerja = (int) $kartu->sum('hari_kerja');
        $call = (int) $kartu->sum('call');
        $ec = (int) $kartu->sum('ec');
        $sales = (int) $kartu->sum('sales');
        $sku = (int) $kartu->sum('sku');
        $point = (int) $kartu->sum('point');

        $avgCall = $bagi($call, $hariKerja);
        $avgEc = $bagi($ec, $hariKerja);
        $avgIpt = $bagi($sku, $ec);

        return [
            'depo_aktif' => $kartu->count(),
            'motoris_aktif' => (int) $kartu->sum('total_motoris'),
            'sk_aktif' => (int) $kartu->sum('total_sk'),
            'hari_kerja' => $hariKerja,
            'call' => $call,
            'ec' => $ec,
            'sales' => $sales,
            'sku' => $sku,
            'point' => $point,
            'avg_call' => $avgCall,
            'avg_ec' => $avgEc,
            'avg_sales' => (int) round($bagi($sales, $hariKerja, 2)),
            'avg_ipt' => $avgIpt,
            'call_achievement' => $bagi($avgCall * 100, $target['call']),
            'ec_achievement' => $bagi($avgEc * 100, $target['ec']),
            'ipt_achievement' => $bagi($avgIpt * 100, $target['ipt']),
        ];
    }

    private static function periode(SummaryDepo $summary): array
    {
        $tanggal = fn (string $t) => Carbon::parse($t)->locale('id')->translatedFormat('d M Y');

        return [
            'dari' => $tanggal($summary->dari), 'sampai' => $tanggal($summary->sampai),
            'sama' => $summary->dari === $summary->sampai,
        ];
    }

    /** Bawaan 7 hari terakhir (beda dengan SummaryDepoController::rentang yang bawaannya hari ini saja), supaya tren harian punya isi tanpa filter. */
    public static function rentang(Request $request): array
    {
        $hariIni = Pengaturan::sekarang()->toDateString();
        $bawaanDari = Carbon::parse($hariIni)->subDays(6)->toDateString();
        $valid = fn (?string $v): bool => (bool) $v && (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $v);
        $dari = $valid($request->query('date_from')) ? $request->query('date_from') : $bawaanDari;
        $sampai = $valid($request->query('date_to')) ? $request->query('date_to') : $hariIni;

        return $sampai < $dari ? [$sampai, $dari] : [$dari, $sampai];
    }
}
