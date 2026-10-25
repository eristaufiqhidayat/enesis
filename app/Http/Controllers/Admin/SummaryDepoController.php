<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Akses\Akses;
use App\Support\Mobile\Pengaturan;
use App\Support\Motoris\SummaryDepo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

// Menu Utama › Summary All Depo: kartu ringkasan per depo + detail per koordinator & sales, dihitung dari
// tabel motoris (absen, kunjungan, penjualan, join visit). Parameter tanggal sama dengan Marketama.
class SummaryDepoController extends Controller
{
    public const MENU = 'summary_all_depo';

    public function index(Request $request): Response
    {
        [$dari, $sampai] = self::rentang($request);
        $summary = new SummaryDepo($dari, $sampai, $request->user());
        $cari = trim((string) $request->query('q'));

        return Inertia::render('Motoris/SummaryDepo', [
            'slug' => self::MENU,
            'judul' => config('marketama.pages.'.self::MENU.'.title') ?? 'Summary All Depo',
            'periode' => self::periode($summary),
            'kartu' => $summary->kartu($cari),
            'target' => $summary->target,
            'metrikGrafik' => collect(SummaryDepo::METRIK_GRAFIK)->map(fn ($label, $kunci) => ['key' => $kunci, 'label' => $label])->values(),
            'filter' => ['date_from' => $dari, 'date_to' => $sampai, 'q' => $cari],
            'bolehUbah' => Akses::bisa($request->user(), self::MENU, 'ubah'),
        ]);
    }

    /** Lihat Detail: ringkasan depo + per koordinator dan sales. */
    public function detail(Request $request, int $depo): Response
    {
        [$dari, $sampai] = self::rentang($request);
        $summary = new SummaryDepo($dari, $sampai, $request->user());
        $detail = $summary->detail($depo) ?? abort(404);

        return Inertia::render('Motoris/SummaryDepoDetail', $detail + [
            'slug' => self::MENU,
            'judul' => $detail['depo']->nama,
            'periode' => self::periode($summary),
            'filter' => ['date_from' => $dari, 'date_to' => $sampai],
        ]);
    }

    /** Detail EC & Penjualan satu depo (daftar transaksi, dipaginasi). */
    public function penjualan(Request $request, int $depo): Response
    {
        [$dari, $sampai] = self::rentang($request);
        $summary = new SummaryDepo($dari, $sampai, $request->user());
        $detail = $summary->detail($depo) ?? abort(404);
        $cari = trim((string) $request->query('q'));

        return Inertia::render('Motoris/SummaryDepoPenjualan', $summary->penjualan($depo, $cari) + [
            'slug' => self::MENU,
            'judul' => 'Detail EC & Penjualan — '.$detail['depo']->nama,
            'depo' => $detail['depo'],
            'periode' => self::periode($summary),
            'filter' => ['date_from' => $dari, 'date_to' => $sampai, 'q' => $cari],
        ]);
    }

    /** @return array{dari: string, sampai: string, sama: bool, hkKoordinator: int} */
    private static function periode(SummaryDepo $summary): array
    {
        $tanggal = fn (string $t) => Carbon::parse($t)->locale('id')->translatedFormat('d F Y');

        return [
            'dari' => $tanggal($summary->dari), 'sampai' => $tanggal($summary->sampai),
            'sama' => $summary->dari === $summary->sampai, 'hkKoordinator' => $summary->hkKoordinator,
        ];
    }

    public function simpanTarget(Request $request): RedirectResponse
    {
        $aturan = ['required', 'numeric', 'min:0.1', 'max:100000'];
        $data = $request->validate(['call' => $aturan, 'ec' => $aturan, 'ipt' => $aturan], [
            '*.min' => 'Target harus lebih dari 0.', '*.numeric' => 'Target harus angka.',
        ], ['call' => 'Target Call', 'ec' => 'Target EC', 'ipt' => 'Target IPT']);

        $lama = SummaryDepo::target();
        $baru = array_map(fn ($v) => round((float) $v, 2), array_intersect_key($data, $lama));

        if ($baru == $lama) {
            $pesan = 'Target tidak berubah.';
        } else {
            SummaryDepo::simpanTarget($baru, $request->user());
            Akses::catat($request->user(), 'target_nasional', 0, 'ubah',
                "Call {$lama['call']}→{$baru['call']}, EC {$lama['ec']}→{$baru['ec']}, IPT {$lama['ipt']}→{$baru['ipt']}");
            $pesan = "Target nasional disimpan: Call {$baru['call']}, EC {$baru['ec']}, IPT {$baru['ipt']}.";
        }

        return redirect()->route('summary-depo', $request->only(['date_from', 'date_to', 'q']))->with('sukses', $pesan);
    }

    /** @return array{0: string, 1: string} rentang tanggal dari query (bawaan hari ini WIB) */
    public static function rentang(Request $request): array
    {
        $hariIni = Pengaturan::sekarang()->toDateString();
        $tanggal = fn (string $k) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->query($k)) ? $request->query($k) : $hariIni;
        $dari = $tanggal('date_from');
        $sampai = $tanggal('date_to');

        return $sampai < $dari ? [$sampai, $dari] : [$dari, $sampai];
    }
}
