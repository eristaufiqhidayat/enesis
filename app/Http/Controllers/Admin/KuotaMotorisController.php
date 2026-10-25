<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Akses\Akses;
use App\Support\Mobile\Pengaturan;
use App\Support\Motoris\KuotaMotoris;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

// Master Data › Kuota Motoris: admin mengisi kuota sales motoris per depo, berlaku mulai bulan tertentu.
// Dipakai tab Coverage dan Performance di Motoris Report.
class KuotaMotorisController extends Controller
{
    public const MENU = 'kuota_motoris';

    public function index(Request $request): View
    {
        $bulan = self::bulan($request);
        $depo = KuotaMotoris::untukBulan($bulan, self::depoCakupan($request));

        $riwayat = DB::table('master_kuota_motoris as m')
            ->leftJoin('mk_depo as d', 'd.id', '=', 'm.depo_id')
            ->leftJoin('users as u', 'u.id', '=', 'm.diubah_oleh')
            ->whereIn('m.depo_id', $depo->pluck('id')->all() ?: [0])
            ->orderByDesc('m.updated_at')->limit(20)
            ->get(['m.berlaku_mulai', 'm.kuota', 'm.updated_at', 'd.nama as depo', 'u.username as oleh']);

        return view('motoris.kuota', [
            'menu' => self::MENU,
            'judul' => config('marketama.pages.'.self::MENU.'.title') ?? 'Kuota Motoris',
            'bulan' => $bulan,
            'depo' => $depo,
            'riwayat' => $riwayat,
            'bolehUbah' => Akses::bisa($request->user(), self::MENU, 'ubah'),
            'zona' => Pengaturan::zona(),
        ]);
    }

    public function simpan(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'bulan' => ['required', 'date_format:Y-m'],
            'kuota' => ['array'],
            'kuota.*' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ], ['kuota.*.integer' => 'Kuota harus angka bulat.', 'kuota.*.min' => 'Kuota tidak boleh negatif.']);

        $bulan = $data['bulan'];
        $sekarang = KuotaMotoris::untukBulan($bulan, self::depoCakupan($request))->keyBy('id');
        $berubah = 0;

        foreach ($data['kuota'] ?? [] as $depoId => $nilai) {
            $depo = $sekarang[(int) $depoId] ?? null;

            if (! $depo) {
                continue; // depo di luar cakupan akun ini atau tidak dikenal
            }

            if ($nilai === null || $nilai === '') {
                // Dikosongkan: hapus isian bulan ini saja, kuota kembali ke bulan sebelumnya / Marketama.
                if ($depo->berlaku_mulai === $bulan && KuotaMotoris::hapus($depo->id, $bulan)) {
                    Akses::catat($request->user(), 'kuota_motoris', $depo->id, 'hapus', "{$depo->nama} mulai {$bulan}");
                    $berubah++;
                }

                continue;
            }

            $nilai = (int) $nilai;

            if ($depo->kuota === $nilai && $depo->sumber === 'enesis') {
                continue;
            }

            if (KuotaMotoris::simpan($depo->id, $bulan, $nilai, $request->user()?->id)) {
                Akses::catat($request->user(), 'kuota_motoris', $depo->id, 'ubah', "{$depo->nama}: {$nilai} mulai {$bulan}");
                $berubah++;
            }
        }

        return redirect()->route('kuota-motoris.index', ['bulan' => $bulan])
            ->with('sukses', $berubah ? "Kuota {$berubah} depo disimpan, berlaku mulai ".self::namaBulan($bulan).'.' : 'Tidak ada kuota yang berubah.');
    }

    public static function bulan(Request $request): string
    {
        $nilai = (string) $request->query('bulan', $request->input('bulan'));

        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $nilai) ? $nilai : Pengaturan::sekarang()->format('Y-m');
    }

    public static function namaBulan(string $bulan): string
    {
        return CarbonImmutable::parse("{$bulan}-01")->locale('id')->translatedFormat('F Y');
    }

    /**
     * Depo yang boleh dilihat akun: null = semua (cakupan "semua"); selain itu depo karyawan dalam cakupannya.
     *
     * @return array<int>|null
     */
    public static function depoCakupan(Request $request): ?array
    {
        if (Akses::cakupan($request->user()) === 'semua') {
            return null;
        }

        return Akses::batasiKaryawan(DB::table('mk_karyawan as k')->whereNotNull('k.depo_id'), $request->user())
            ->distinct()->pluck('k.depo_id')->map(fn ($id) => (int) $id)->all();
    }
}
