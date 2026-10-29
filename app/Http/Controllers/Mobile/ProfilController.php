<?php

namespace App\Http\Controllers\Mobile;

use App\Models\User;
use App\Support\Mobile\Pengaturan;
use App\Support\Mobile\WilayahKerja;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

// Profil (/me), paket data master (/master), aturan hari ini (/config), dan unggah foto (/uploads).
class ProfilController extends MobileController
{
    public static function profil(User $user): array
    {
        $karyawan = $user->karyawan_id ? DB::table('mk_karyawan')->where('id', $user->karyawan_id)->first() : null;
        $depo = $karyawan?->depo_id ? DB::table('mk_depo')->where('id', $karyawan->depo_id)->first(['id', 'nama']) : null;
        $koordinator = $karyawan?->koordinator_id ? DB::table('mk_karyawan')->where('id', $karyawan->koordinator_id)->first(['id', 'nama']) : null;

        return [
            'id' => $user->id,
            'username' => $user->username,
            'name' => $karyawan->nama ?? $user->name,
            'role' => $user->kategori,
            'employee_id' => $karyawan ? (int) $karyawan->id : null,
            'depot' => $depo ? ['id' => (int) $depo->id, 'name' => $depo->nama] : null,
            'coordinator' => $koordinator ? ['id' => (int) $koordinator->id, 'name' => $koordinator->nama] : null,
            'must_change_password' => (bool) $user->wajib_ganti_password,
            // Menu aplikasi per role: sales motoris atau koordinator (tim, join visit).
            'permissions' => match ($user->kategori) {
                'sales' => ['attendance', 'depot_visit', 'canvassing', 'route', 'visit', 'sale', 'new_outlet', 'tracking'],
                'koordinator' => ['attendance', 'team', 'join_visit', 'tracking'],
                default => [],
            },
        ];
    }

    public function me(Request $request): JsonResponse
    {
        return $this->ok(self::profil($request->user()));
    }

    public function master(Request $request): JsonResponse
    {
        $request->validate(['since' => ['nullable', 'date']]);
        $sejak = $request->filled('since') ? CarbonImmutable::parse($request->input('since'))->utc() : null;

        // Produk + nama merk/kategori/satuan dari tabel master; tanpa master (data lama) dari JSON "data".
        // Sinkron sebagian (since): produk berubah bila produknya atau master merk/kategori/satuannya berubah.
        $master = Schema::hasTable('mk_merk_produk');
        $produk = DB::table('mk_produk as p')
            ->when($master, fn ($q) => $q->leftJoin('mk_merk_produk as m', 'm.id', '=', 'p.merk_id')
                ->leftJoin('mk_kategori_produk as k', 'k.id', '=', 'p.kategori_id')
                ->leftJoin('mk_satuan_produk as s', 's.id', '=', 'p.satuan_id')
                ->select(['p.*', 'm.nama as merk_nama', 'k.nama as kategori_nama', 's.nama as satuan_nama',
                    'm.diubah_pada as merk_diubah', 'k.diubah_pada as kategori_diubah', 's.diubah_pada as satuan_diubah']),
                fn ($q) => $q->select('p.*'))
            ->when($sejak, fn ($q) => $q->where(function ($w) use ($sejak, $master) {
                $w->where('p.ditarik_pada', '>', $sejak);

                if ($master) {
                    foreach (['p.diubah_pada', 'm.diubah_pada', 'k.diubah_pada', 's.diubah_pada'] as $kolom) {
                        $w->orWhere($kolom, '>', $sejak);
                    }
                }
            }))
            ->orderBy('p.id')->get()
            ->map(function ($p) {
                $data = json_decode((string) $p->data, true) ?: [];
                $poin = Pengaturan::poinProduk($p);
                $diubah = collect([$p->ditarik_pada, $p->diubah_pada ?? null, $p->merk_diubah ?? null, $p->kategori_diubah ?? null, $p->satuan_diubah ?? null])
                    ->filter()->max();

                return [
                    'id' => (int) $p->id,
                    'code' => $p->kode,
                    'name' => $p->nama,
                    'brand' => ($p->merk_nama ?? null) ?? $data['merk'] ?? null,
                    'category' => ($p->kategori_nama ?? null) ?? $data['kategori'] ?? null,
                    'unit' => ($p->satuan_nama ?? null) ?? $data['satuan'] ?? null,
                    'price' => $p->harga_jual !== null ? (int) round((float) $p->harga_jual) : null,
                    'is_focus' => $poin['fokus'],
                    'max_points_per_invoice' => $poin['maks_poin'],
                    'updated_at' => $this->waktu($diubah),
                ];
            });

        $tipe = Schema::hasTable('tipe_outlet')
            ? DB::table('tipe_outlet')->whereNotNull('tipe_outlet')->orderBy('tipe_outlet')->pluck('tipe_outlet')->map(fn ($t) => trim($t))->filter()->unique()->values()->all()
            : [];

        return $this->ok([
            'products' => $produk,
            'competitor_products' => $this->produkKompetitor(),
            'outlet_types' => $tipe ?: config('mobile.tipe_outlet_default'),
            'no_sale_reasons' => collect(config('mobile.alasan_tidak_beli'))->map(fn ($label, $kode) => ['code' => $kode, 'label' => $label])->values(),
        ], ['server_time' => $this->waktu(now()), 'full' => $sejak === null]);
    }

    /** Produk kompetitor dari master menu Produk Kompetitor (ID asli Marketama); tabel lama sebagai cadangan. */
    private function produkKompetitor(): array
    {
        if (Schema::hasTable('mk_produk_kompetitor') && DB::table('mk_produk_kompetitor')->exists()) {
            return DB::table('mk_produk_kompetitor as k')->leftJoin('mk_satuan_produk as s', 's.id', '=', 'k.satuan_id')
                ->orderBy('k.nama')->get(['k.id', 'k.nama', 's.nama as satuan'])
                ->map(fn ($k) => ['id' => (int) $k->id, 'name' => $k->nama, 'unit' => $k->satuan])->all();
        }

        return Schema::hasTable('produk_kompetitor')
            ? DB::table('produk_kompetitor')->orderBy('nama_produk')->get(['id', 'nama_produk', 'satuan'])
                ->map(fn ($k) => ['id' => (int) $k->id, 'name' => $k->nama_produk, 'unit' => $k->satuan])->all()
            : [];
    }

    public function config(Request $request): JsonResponse
    {
        $sekarang = $this->sekarang();
        $karyawan = $this->karyawan($request);
        $depo = Pengaturan::depo($karyawan->depo_id ? (int) $karyawan->depo_id : null);
        $jam = Pengaturan::jamAktif($sekarang, $request->user()->kategori);
        $libur = Pengaturan::libur($sekarang->toDateString(), $sekarang->addDays(60)->toDateString());
        $hariIni = $sekarang->toDateString();

        return $this->ok([
            'server_time' => $sekarang->toIso8601String(),
            'timezone' => Pengaturan::zona(),
            'today' => [
                'date' => $hariIni,
                'is_working_day' => $jam['hari_kerja'] && ! isset($libur[$hariIni]),
                'holiday_name' => $libur[$hariIni] ?? null,
                'active_from' => $jam['mulai'],
                'active_until' => $jam['selesai'],
                'check_in_deadline' => $jam['batas_masuk'],
            ],
            'depot' => $depo ? [
                'id' => $depo['id'],
                'name' => $depo['nama'],
                'lat' => $depo['lat'],
                'lng' => $depo['lng'],
                'radius_m' => config('mobile.radius_depo_m'),
            ] : null,
            // Area kerja untuk absen (Master Area, bukan radius depo): sales → areanya sendiri;
            // koordinator → gabungan area aktif tim. Kosong = belum punya area kerja (absen ditolak).
            'work_areas' => WilayahKerja::untukAkun($request->user()->kategori, (int) $karyawan->id),
            'outlet_radius_m' => config('mobile.radius_outlet_m'),
            'duplicate_outlet_radius_m' => config('mobile.radius_duplikat_m'),
            'gps_interval_s' => $depo['interval_gps_detik'] ?? 180,
            'min_transaction_value' => $depo['minimal_transaksi'] ?? config('mobile.minimal_transaksi_default'),
            'daily_targets' => $depo['target'] ?? config('mobile.target_default'),
            'upcoming_holidays' => collect($libur)->map(fn ($nama, $tgl) => ['date' => $tgl, 'name' => $nama])->values(),
            'min_app_version' => config('mobile.versi_minimum'),
            'upload_max_kb' => config('mobile.unggah_maks_kb'),
        ]);
    }

    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:'.config('mobile.unggah_maks_kb')],
            'type' => ['required', 'in:selfie,toko,rak,lampiran'],
        ], ['file.max' => 'Ukuran file maksimal '.config('mobile.unggah_maks_kb').' KB.']);

        $file = $request->file('file');
        $id = (string) Str::uuid();
        $disk = config('mobile.unggah_disk');
        $folder = 'mobile/'.$request->user()->id.'/'.now()->format('Y/m');
        $path = $file->storeAs($folder, $id.'.'.($file->guessExtension() ?: 'jpg'), $disk);

        DB::table('mobile_uploads')->insert([
            'id' => $id,
            'user_id' => $request->user()->id,
            'jenis' => $request->input('type'),
            'disk' => $disk,
            'path' => $path,
            'mime' => (string) $file->getMimeType(),
            'ukuran' => (int) $file->getSize(),
            'created_at' => now(),
        ]);

        return $this->dibuat(['upload_id' => $id, 'size' => (int) $file->getSize()]);
    }
}
