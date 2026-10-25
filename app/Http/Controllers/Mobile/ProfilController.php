<?php

namespace App\Http\Controllers\Mobile;

use App\Models\User;
use App\Support\Mobile\Pengaturan;
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

        $produk = DB::table('mk_produk')
            ->when($sejak, fn ($q) => $q->where('ditarik_pada', '>', $sejak))
            ->orderBy('id')->get()
            ->map(function ($p) {
                $data = json_decode((string) $p->data, true) ?: [];
                $poin = Pengaturan::poinProduk($p);

                return [
                    'id' => (int) $p->id,
                    'code' => $p->kode,
                    'name' => $p->nama,
                    'brand' => $data['merk'] ?? null,
                    'category' => $data['kategori'] ?? null,
                    'unit' => $data['satuan'] ?? null,
                    'price' => $p->harga_jual !== null ? (int) round((float) $p->harga_jual) : null,
                    'is_focus' => $poin['fokus'],
                    'max_points_per_invoice' => $poin['maks_poin'],
                    'updated_at' => $this->waktu($p->ditarik_pada),
                ];
            });

        $tipe = Schema::hasTable('tipe_outlet')
            ? DB::table('tipe_outlet')->whereNotNull('tipe_outlet')->orderBy('tipe_outlet')->pluck('tipe_outlet')->map(fn ($t) => trim($t))->filter()->unique()->values()->all()
            : [];

        return $this->ok([
            'products' => $produk,
            'competitor_products' => Schema::hasTable('produk_kompetitor')
                ? DB::table('produk_kompetitor')->orderBy('nama_produk')->get(['id', 'nama_produk', 'satuan'])
                    ->map(fn ($k) => ['id' => (int) $k->id, 'name' => $k->nama_produk, 'unit' => $k->satuan])
                : [],
            'outlet_types' => $tipe ?: config('mobile.tipe_outlet_default'),
            'no_sale_reasons' => collect(config('mobile.alasan_tidak_beli'))->map(fn ($label, $kode) => ['code' => $kode, 'label' => $label])->values(),
        ], ['server_time' => $this->waktu(now()), 'full' => $sejak === null]);
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
