<?php

namespace App\Http\Controllers\Mobile;

use App\Support\Mobile\ApiError;
use App\Support\Mobile\Lokasi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// Rute hari ini (outlet di area sales + status kunjungan), outlet terdekat, detail outlet,
// dan pendaftaran outlet baru / NOO (layar Rute & Outlet baru di mockup).
class OutletController extends MobileController
{
    public function rute(Request $request): JsonResponse
    {
        $request->validate([
            'cursor' => ['nullable', 'integer', 'min:0'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ] + $this->aturanLokasi(false));

        $hari = $this->hari($request);
        $area = $hari->areaRute();
        $status = $hari->statusOutlet();
        $limit = (int) $request->input('limit', 200);
        $dasar = DB::table('mk_outlet')->whereIn('area_id', $area ?: [0]);

        $outlet = (clone $dasar)->where('id', '>', (int) $request->input('cursor', 0))
            ->orderBy('id')->limit($limit + 1)->get();
        $lanjut = $outlet->count() > $limit ? $outlet->slice(0, $limit)->last()->id : null;
        $titik = $request->filled('lat') ? [(float) $request->lat, (float) $request->lng] : null;

        $data = $outlet->take($limit)->map(fn ($o) => $this->ringkas($o, $titik) + [
            'visit_status' => $status[$o->id]['status'] ?? 'belum',
            'visit_id' => $status[$o->id]['visit_id'] ?? null,
        ])->values();

        $semua = (clone $dasar)->pluck('id')->flip();
        $hitung = ['beli' => 0, 'tidak_beli' => 0, 'dikunjungi' => 0];

        foreach ($status as $id => $s) {
            if (isset($semua[$id])) {
                $hitung[$s['status']]++;
            }
        }

        return $this->ok($data, [
            'date' => $hari->tanggal,
            'area_ids' => $area,
            'total' => $semua->count(),
            'visited' => array_sum($hitung),
            'bought' => $hitung['beli'],
            'not_bought' => $hitung['tidak_beli'],
            'next_cursor' => $lanjut ? (int) $lanjut : null,
        ]);
    }

    public function terdekat(Request $request): JsonResponse
    {
        $request->validate($this->aturanLokasi() + [
            'radius' => ['nullable', 'integer', 'min:10', 'max:2000'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $lat = (float) $request->lat;
        $lng = (float) $request->lng;
        $radius = (int) $request->input('radius', 300);

        return $this->ok($this->sekitar($lat, $lng, $radius)->take((int) $request->input('limit', 50))->values(), ['radius_m' => $radius]);
    }

    public function detail(Request $request, int $id): JsonResponse
    {
        $outlet = DB::table('mk_outlet')->where('id', $id)->first() ?? throw ApiError::tidakAda('Outlet tidak ditemukan.');

        $jual = DB::table('trx_penjualan')->where('outlet_id', $id)->whereNull('dibatalkan_at')->orderByDesc('id')->first();
        $kunjunganStok = DB::table('trx_stok_outlet as s')->join('trx_kunjungan as k', 'k.id', '=', 's.kunjungan_id')
            ->where('k.outlet_id', $id)->max('k.id');

        return $this->ok($this->ringkas($outlet) + [
            'province' => $outlet->provinsi,
            'city' => $outlet->kota_kabupaten,
            'district' => $outlet->kecamatan,
            'village' => $outlet->kelurahan,
            'last_sale' => $jual ? [
                'sale_id' => (int) $jual->id,
                'code' => $jual->kode,
                'date' => substr((string) $jual->tanggal, 0, 10),
                'total' => (int) $jual->total,
                'items' => DB::table('trx_penjualan_item as i')->leftJoin('mk_produk as p', 'p.id', '=', 'i.produk_id')
                    ->where('i.penjualan_id', $jual->id)->get(['i.produk_id', 'p.nama', 'i.qty', 'i.harga'])
                    ->map(fn ($i) => ['product_id' => (int) $i->produk_id, 'name' => $i->nama, 'qty' => $this->qty($i->qty), 'price' => (int) $i->harga]),
            ] : null,
            'last_stock' => $kunjunganStok ? DB::table('trx_stok_outlet as s')->leftJoin('mk_produk as p', 'p.id', '=', 's.produk_id')
                ->where('s.kunjungan_id', $kunjunganStok)->get(['s.produk_id', 'p.nama', 's.produk_kompetitor', 's.qty'])
                ->map(fn ($s) => [
                    'product_id' => $s->produk_id ? (int) $s->produk_id : null,
                    'name' => $s->produk_id ? $s->nama : $s->produk_kompetitor,
                    'is_competitor' => ! $s->produk_id,
                    'qty' => $this->qty($s->qty),
                ]) : [],
        ]);
    }

    public function daftar(Request $request): JsonResponse
    {
        $request->validate($this->aturanUmum() + $this->aturanLokasi() + [
            'name' => ['required', 'string', 'max:150'],
            'owner_name' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\- ]{6,30}$/'],
            'type' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:500'],
            'area_id' => ['nullable', 'integer'],
            'photo_store_id' => array_merge(['required'], $this->aturanUpload($request)),
            'photo_shelf_id' => array_merge(['nullable'], $this->aturanUpload($request)),
            'confirm_not_duplicate' => ['nullable', 'boolean'],
        ], ['photo_store_id.required' => 'Foto toko wajib.']);

        $karyawan = $this->karyawan($request);
        $lat = (float) $request->lat;
        $lng = (float) $request->lng;
        $radius = (int) config('mobile.radius_duplikat_m');

        if (! $request->boolean('confirm_not_duplicate') && ($mirip = $this->sekitar($lat, $lng, $radius))->isNotEmpty()) {
            throw new ApiError('possible_duplicate', "Sudah ada outlet dalam radius $radius m. Pastikan bukan outlet yang sama.", 422, [
                'candidates' => $mirip->take(5)->values()->all(),
            ]);
        }

        $id = DB::table('trx_outlet_baru')->insertGetId([
            'karyawan_id' => $karyawan->id,
            'depo_id' => $karyawan->depo_id,
            'area_id' => $request->input('area_id') ?? ($this->hari($request)->areaRute()[0] ?? null),
            'nama' => trim($request->name),
            'pemilik' => $request->owner_name,
            'no_hp' => $request->phone,
            'tipe' => $request->type,
            'alamat' => $request->address,
            'lat' => $lat,
            'lng' => $lng,
            'foto_toko_id' => $request->photo_store_id,
            'foto_rak_id' => $request->photo_shelf_id,
            'status' => 'menunggu',
        ] + $this->kolomUmum($request));

        return $this->dibuat(['new_outlet_id' => $id, 'status' => 'menunggu']);
    }

    /** Outlet (master dan NOO yang masih menunggu; yang disetujui sudah ada di master) dalam radius, urut terdekat. */
    private function sekitar(float $lat, float $lng, int $radius)
    {
        [$latMin, $latMax, $lngMin, $lngMax] = Lokasi::kotak($lat, $lng, $radius);

        $master = DB::table('mk_outlet')->whereBetween('latitude', [$latMin, $latMax])->whereBetween('longitude', [$lngMin, $lngMax])
            ->limit(500)->get()->map(fn ($o) => $this->ringkas($o, [$lat, $lng]) + ['is_new' => false]);

        $baru = DB::table('trx_outlet_baru')->where('status', 'menunggu')
            ->whereBetween('lat', [$latMin, $latMax])->whereBetween('lng', [$lngMin, $lngMax])->limit(100)->get()
            ->map(fn ($o) => [
                'id' => (int) $o->id,
                'code' => null,
                'name' => $o->nama,
                'address' => $o->alamat,
                'lat' => $this->koordinat($o->lat),
                'lng' => $this->koordinat($o->lng),
                'area_id' => $o->area_id ? (int) $o->area_id : null,
                'distance_m' => Lokasi::jarak($lat, $lng, (float) $o->lat, (float) $o->lng),
                'is_new' => true,
            ]);

        return $master->concat($baru)->filter(fn ($o) => $o['distance_m'] !== null && $o['distance_m'] <= $radius)->sortBy('distance_m')->values();
    }

    private function ringkas(object $o, ?array $titik = null): array
    {
        $ada = Lokasi::sah($o->latitude, $o->longitude);

        return [
            'id' => (int) $o->id,
            'code' => $o->kode,
            'name' => $o->nama,
            'address' => $o->alamat,
            'lat' => $ada ? $this->koordinat($o->latitude) : null,
            'lng' => $ada ? $this->koordinat($o->longitude) : null,
            'area_id' => $o->area_id ? (int) $o->area_id : null,
            'distance_m' => $titik && $ada ? Lokasi::jarak($titik[0], $titik[1], (float) $o->latitude, (float) $o->longitude) : null,
        ];
    }
}
