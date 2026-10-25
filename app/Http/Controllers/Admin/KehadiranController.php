<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Akses\Akses;
use App\Support\Mobile\Pengaturan;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

// Absensi sales / koordinator, checkin/checkout depo, dan checkin outlet dari tabel motoris (trx_absensi,
// trx_checkin_depo, trx_kunjungan): berisi data dari aplikasi HP dan riwayat aplikasi lama hasil
// enesis:migrasi-transaksi. Sales dan koordinator sama-sama absen di trx_absensi; menu Absensi
// Koordinator hanya menampilkan koordinator.
class KehadiranController extends Controller
{
    public const SUMBER = ['semua' => 'Semua sumber', 'motoris' => 'Aplikasi motoris', 'lama' => 'Aplikasi lama'];

    /** Batas rentang tanggal sekali tampil (hari). */
    private const RENTANG_MAKS = 92;

    public function absensi(Request $request): View
    {
        $menu = (string) $request->route('menu');
        $filter = $this->filter($request);
        $status = in_array($request->query('status'), ['terlambat', 'belum_pulang'], true)
            ? $request->query('status')
            : ($menu === 'absensi_sales_terlambat' ? 'terlambat' : 'semua');

        $koordinator = in_array($menu, ['absensi_koordinator', 'lap_absensi_koordinator'], true);
        $dasar = $this->dasar($request, 'trx_absensi', $filter, $koordinator ? 'koordinator' : 'sales');
        $ringkas = [
            'total' => (clone $dasar)->count(),
            'terlambat' => (clone $dasar)->where('t.terlambat', true)->count(),
            'motoris' => (clone $dasar)->where('t.sumber', 'motoris')->count(),
        ];

        $baris = $dasar
            ->when($status === 'terlambat', fn ($q) => $q->where('t.terlambat', true))
            ->when($status === 'belum_pulang', fn ($q) => $q->whereNull('t.pulang_at'))
            ->addSelect(['t.masuk_at', 't.pulang_at', 't.terlambat', 't.masuk_lat as lat', 't.masuk_lng as lng', 't.masuk_foto_id as foto_id', 't.mock_location'])
            ->orderByDesc('t.tanggal')->orderBy('t.masuk_at')
            ->paginate(50)->withQueryString();

        return view('kehadiran.absensi', $this->dataView($menu, $filter, $baris) + [
            'status' => $status,
            'ringkas' => $ringkas,
            'labelOrang' => $koordinator ? 'Koordinator' : 'Sales',
        ]);
    }

    public function checkinDepo(Request $request): View
    {
        $menu = (string) $request->route('menu');
        $filter = $this->filter($request);
        $dasar = $this->dasar($request, 'trx_checkin_depo', $filter);
        $ringkas = [
            'total' => (clone $dasar)->count(),
            'belum_checkout' => (clone $dasar)->whereNull('t.checkout_at')->count(),
            'motoris' => (clone $dasar)->where('t.sumber', 'motoris')->count(),
        ];

        $baris = $dasar
            ->addSelect(['t.checkin_at as masuk_at', 't.checkout_at as pulang_at', 't.lat', 't.lng', 't.jarak_m'])
            ->orderByDesc('t.tanggal')->orderBy('t.checkin_at')
            ->paginate(50)->withQueryString();

        return view('kehadiran.checkin-depo', $this->dataView($menu, $filter, $baris) + ['ringkas' => $ringkas]);
    }

    public const STATUS_KUNJUNGAN = ['semua' => 'Semua', 'beli' => 'Beli', 'tidak_beli' => 'Tidak beli', 'di_luar_rute' => 'Di luar rute'];

    public function kunjungan(Request $request): View
    {
        $menu = (string) $request->route('menu');
        $filter = $this->filter($request);
        $status = array_key_exists($request->query('status'), self::STATUS_KUNJUNGAN) ? $request->query('status') : 'semua';
        $cariOutlet = trim((string) $request->query('outlet'));
        $beli = fn ($q) => $q->select(DB::raw(1))->from('trx_penjualan as p')->whereColumn('p.kunjungan_id', 't.id')->whereNull('p.dibatalkan_at');

        $dasar = $this->dasar($request, 'trx_kunjungan', $filter)
            ->leftJoin('mk_outlet as o', 'o.id', '=', 't.outlet_id')
            ->when($cariOutlet !== '', fn ($q) => $q->where(function ($w) use ($cariOutlet) {
                foreach (['o.nama', 'o.kode'] as $kolom) {
                    $w->orWhereRaw("LOWER({$kolom}) LIKE ?", ['%'.mb_strtolower($cariOutlet).'%']);
                }
            }));

        $ringkas = [
            'total' => (clone $dasar)->count(),
            'beli' => (clone $dasar)->whereExists($beli)->count(),
            'tidak_beli' => (clone $dasar)->whereNotNull('t.tidak_beli_kategori')->count(),
            'di_luar_rute' => (clone $dasar)->where('t.di_luar_rute', true)->count(),
        ];

        $baris = $dasar
            ->when($status === 'beli', fn ($q) => $q->whereExists($beli))
            ->when($status === 'tidak_beli', fn ($q) => $q->whereNotNull('t.tidak_beli_kategori'))
            ->when($status === 'di_luar_rute', fn ($q) => $q->where('t.di_luar_rute', true))
            ->addSelect([
                't.checkin_at as masuk_at', 't.checkout_at as pulang_at', 't.durasi_detik', 't.lat', 't.lng', 't.jarak_m',
                't.foto_id', 't.foto_url', 't.di_luar_rute', 't.mock_location', 't.tidak_beli_kategori', 't.tidak_beli_keterangan',
                't.kategori_lama', 't.keterangan_lama', 'o.kode as outlet_kode', 'o.nama as outlet',
            ])
            ->selectSub(DB::table('trx_penjualan as p')->whereColumn('p.kunjungan_id', 't.id')->whereNull('p.dibatalkan_at')->selectRaw('SUM(p.total)'), 'total_jual')
            ->orderByDesc('t.tanggal')->orderByDesc('t.checkin_at')
            ->paginate(50)->withQueryString();

        return view('kehadiran.kunjungan', $this->dataView($menu, $filter, $baris) + [
            'status' => $status,
            'cariOutlet' => $cariOutlet,
            'ringkas' => $ringkas,
            'alasan' => config('mobile.alasan_tidak_beli'),
        ]);
    }

    /** Foto unggahan HP (selfie absen, foto kunjungan, foto outlet baru / join visit), hanya untuk yang boleh melihat data karyawan tersebut. */
    public function foto(Request $request, string $id): StreamedResponse
    {
        $foto = DB::table('mobile_uploads as m')->join('users as u', 'u.id', '=', 'm.user_id')
            ->where('m.id', $id)->first(['m.disk', 'm.path', 'm.mime', 'u.karyawan_id']) ?? abort(404);

        $bolehMenu = collect(['absensi_sales', 'lap_absensi_sales', 'absensi_sales_terlambat', 'absensi_koordinator', 'lap_absensi_koordinator', 'checkin_outlet', 'lap_checkin_outlet', 'outlet_all', 'join_visit', 'lap_join_visit'])
            ->contains(fn ($slug) => Akses::bisa($request->user(), $slug));
        $dalamCakupan = $foto->karyawan_id && Akses::batasiKaryawan(
            DB::table('mk_karyawan as k')->where('k.id', $foto->karyawan_id), $request->user()
        )->exists();

        abort_unless($bolehMenu && $dalamCakupan, 403, 'Anda tidak punya hak untuk membuka foto ini.');
        abort_unless(Storage::disk($foto->disk)->exists($foto->path), 404);

        return Storage::disk($foto->disk)->response($foto->path, null, [
            'Content-Type' => $foto->mime,
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    // ------------------------------------------------------------------

    /** @return array{dari: string, sampai: string, depo: ?int, koordinator: ?int, q: string, sumber: string} */
    private function filter(Request $request): array
    {
        $zona = Pengaturan::zona();
        $hari = CarbonImmutable::now($zona)->startOfDay();
        $tgl = function (string $kunci, CarbonImmutable $bawaan) use ($request, $zona) {
            $nilai = (string) $request->query($kunci);

            return preg_match('/^\d{4}-\d{2}-\d{2}$/', $nilai) ? CarbonImmutable::parse($nilai, $zona) : $bawaan;
        };

        $sampai = $tgl('sampai', $hari);
        $dari = $tgl('dari', $sampai->subDays(6));

        if ($dari->gt($sampai)) {
            [$dari, $sampai] = [$sampai, $dari];
        }

        if ($dari->diffInDays($sampai) > self::RENTANG_MAKS) {
            $dari = $sampai->subDays(self::RENTANG_MAKS);
        }

        return [
            'dari' => $dari->toDateString(),
            'sampai' => $sampai->toDateString(),
            'depo' => $request->integer('depo') ?: null,
            'koordinator' => $request->integer('koordinator') ?: null,
            'q' => trim((string) $request->query('q')),
            'sumber' => array_key_exists($request->query('sumber'), self::SUMBER) ? $request->query('sumber') : 'semua',
        ];
    }

    /**
     * Query dasar satu tabel kehadiran + nama sales/depo/koordinator, sudah dibatasi filter & cakupan role.
     * $kategori 'sales' (termasuk karyawan yang tidak dikenali) atau 'koordinator'; null = semua.
     */
    private function dasar(Request $request, string $tabel, array $f, ?string $kategori = null): Builder
    {
        $q = DB::table($tabel.' as t')
            ->leftJoin('mk_karyawan as k', 'k.id', '=', 't.karyawan_id')
            ->leftJoin('mk_depo as d', 'd.id', '=', 't.depo_id')
            ->leftJoin('mk_karyawan as ko', 'ko.id', '=', 'k.koordinator_id')
            ->whereBetween('t.tanggal', [$f['dari'], $f['sampai']])
            ->when($f['depo'], fn ($w) => $w->where('t.depo_id', $f['depo']))
            ->when($kategori === 'koordinator', fn ($w) => $w->where('k.kategori', 'koordinator'))
            ->when($kategori === 'sales', fn ($w) => $w->where(fn ($k) => $k->whereNull('k.kategori')->orWhere('k.kategori', '<>', 'koordinator')))
            // Filter koordinator: di menu koordinator = orangnya sendiri, di menu sales = timnya.
            ->when($f['koordinator'], fn ($w) => $kategori === 'koordinator'
                ? $w->where('k.id', $f['koordinator'])
                : $w->where('k.koordinator_id', $f['koordinator']))
            ->when($f['sumber'] !== 'semua', fn ($w) => $w->where('t.sumber', $f['sumber']))
            ->when($f['q'] !== '', fn ($w) => $w->whereRaw('LOWER(k.nama) LIKE ?', ['%'.mb_strtolower($f['q']).'%']))
            ->select(['t.id', 't.tanggal', 't.karyawan_id', 't.sumber', 'k.nama as sales', 'd.nama as depo', 'ko.nama as koordinator']);

        return Akses::batasiKaryawan($q, $request->user());
    }

    private function dataView(string $menu, array $filter, $baris): array
    {
        return [
            'menu' => $menu,
            'judul' => config("marketama.pages.{$menu}.title") ?? 'Kehadiran',
            'filter' => $filter,
            'baris' => $baris,
            'zona' => Pengaturan::zona(),
            'daftarDepo' => DB::table('mk_depo')->orderBy('nama')->pluck('nama', 'id'),
            'daftarKoordinator' => Akses::batasiKaryawan(
                DB::table('mk_karyawan as k')->where('k.kategori', 'koordinator')->where('k.aktif', true), auth()->user()
            )->orderBy('k.nama')->pluck('k.nama', 'k.id'),
        ];
    }
}
