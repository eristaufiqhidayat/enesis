<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Akses\Akses;
use App\Support\Mobile\Pengaturan;
use App\Support\Motoris\KuotaMotoris;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

// Master Data › Kuota Motoris: admin mengisi kuota sales motoris per depo, berlaku mulai bulan tertentu.
// Dipakai tab Coverage dan Performance di Motoris Report.
class KuotaMotorisController extends Controller
{
    public const MENU = 'kuota_motoris';

    public function index(Request $request): Response
    {
        $bulan = self::bulan($request);

        // Baris depo dan riwayat ditunda supaya halaman (judul, filter) tampil dulu (lihat Motoris/Kuota.vue).
        return Inertia::render('Motoris/Kuota', [
            'slug' => self::MENU,
            'judul' => config('marketama.pages.'.self::MENU.'.title') ?? 'Kuota Motoris',
            'bulan' => $bulan,
            'namaBulan' => self::namaBulan($bulan),
            'depo' => Inertia::defer(fn () => self::muatDepo($bulan, self::depoCakupan($request)), 'depo'),
            'riwayat' => Inertia::defer(fn () => self::muatRiwayat(self::depoCakupan($request)), 'riwayat'),
            'bolehUbah' => Akses::bisa($request->user(), self::MENU, 'ubah'),
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    private static function muatDepo(string $bulan, ?array $cakupan): array
    {
        return KuotaMotoris::untukBulan($bulan, $cakupan)->map(fn ($d) => [
            'id' => $d->id,
            'kota' => $d->kota,
            'nama' => $d->nama,
            'aktif' => (bool) $d->aktif,
            'kuota' => $d->kuota,
            'kuota_marketama' => $d->kuota_marketama,
            'sumber' => $d->sumber,
            // Isian admin yang berlaku dari bulan sebelumnya: tampilkan sejak kapan.
            'sejak' => $d->sumber === 'enesis' && $d->berlaku_mulai !== $bulan ? self::namaBulan($d->berlaku_mulai) : null,
        ])->values()->all();
    }

    /** @return array<int, array<string, mixed>> */
    private static function muatRiwayat(?array $cakupan): array
    {
        $zona = Pengaturan::zona();

        return DB::table('master_kuota_motoris as m')
            ->leftJoin('mk_depo as d', 'd.id', '=', 'm.depo_id')
            ->leftJoin('users as u', 'u.id', '=', 'm.diubah_oleh')
            // Depo dalam cakupan akun, sama dengan daftar depo di muatDepo.
            ->whereIn('m.depo_id', DB::table('mk_depo')->when($cakupan !== null, fn ($q) => $q->whereIn('id', $cakupan ?: [0]))->select('id'))
            ->orderByDesc('m.updated_at')->limit(20)
            ->get(['m.berlaku_mulai', 'm.kuota', 'm.updated_at', 'd.nama as depo', 'u.username as oleh'])
            ->map(fn ($r) => [
                'depo' => $r->depo,
                'berlaku' => self::namaBulan($r->berlaku_mulai),
                'kuota' => $r->kuota,
                'diubah' => ($r->updated_at ? Carbon::parse($r->updated_at)->timezone($zona)->format('d M Y H:i') : '-')
                    .($r->oleh ? ' · '.$r->oleh : ''),
            ])->values()->all();
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
