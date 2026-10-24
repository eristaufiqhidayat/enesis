<?php

namespace App\Http\Controllers\Mobile;

use App\Support\Mobile\ApiError;
use App\Support\Mobile\HariKerja;
use App\Support\Mobile\Lokasi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

// Kunjungan outlet: checkin (jarak ke titik outlet), stok sendiri & kompetitor, tidak beli,
// dan checkout (layar Kunjungan di mockup).
class KunjunganController extends MobileController
{
    public function checkIn(Request $request): JsonResponse
    {
        $request->validate($this->aturanUmum() + $this->aturanLokasi() + [
            'outlet_id' => ['required_without:new_outlet_id', 'nullable', 'integer', Rule::exists('mk_outlet', 'id')],
            'new_outlet_id' => ['nullable', 'integer', Rule::exists('trx_outlet_baru', 'id')->where('karyawan_id', $request->user()->karyawan_id)],
            'photo_id' => array_merge(['nullable'], $this->aturanUpload($request)),
        ]);

        $karyawan = $this->karyawan($request);
        $hari = $this->hari($request);
        $hari->wajibAbsen();
        $canvassing = $hari->wajibCanvassing();
        $this->hariBelumDitutup($canvassing);
        $hari->tanpaKunjunganTerbuka();

        $lat = (float) $request->lat;
        $lng = (float) $request->lng;
        $outlet = $request->filled('outlet_id')
            ? DB::table('mk_outlet')->where('id', $request->outlet_id)->first(['id', 'area_id', 'latitude', 'longitude'])
            : null;
        $baru = $outlet ? null : DB::table('trx_outlet_baru')->where('id', $request->new_outlet_id)->first();
        [$tLat, $tLng] = $outlet ? [$outlet->latitude, $outlet->longitude] : [$baru->lat, $baru->lng];
        $jarak = Lokasi::sah($tLat, $tLng) ? Lokasi::jarak($lat, $lng, (float) $tLat, (float) $tLng) : null;
        $batas = (int) config('mobile.radius_outlet_m');

        if ($jarak !== null && $jarak > $batas) {
            throw new ApiError('too_far_from_outlet', "Anda $jarak m dari outlet, batas $batas m.", 422, ['distance_m' => $jarak, 'radius_m' => $batas]);
        }

        $luarRute = $outlet ? ! in_array((int) $outlet->area_id, $hari->areaRute(), true) : false;

        $id = DB::table('trx_kunjungan')->insertGetId([
            'karyawan_id' => $karyawan->id,
            'depo_id' => $karyawan->depo_id,
            'outlet_id' => $outlet?->id,
            'outlet_baru_id' => $baru?->id,
            'tanggal' => $hari->tanggal,
            'checkin_at' => now(),
            'lat' => $lat,
            'lng' => $lng,
            'jarak_m' => $jarak,
            'foto_id' => $request->photo_id,
            'di_luar_rute' => $luarRute,
            'mock_location' => $request->boolean('is_mock_location'),
        ] + $this->kolomUmum($request));

        return $this->dibuat(['visit_id' => $id, 'distance_to_outlet_m' => $jarak, 'is_off_route' => $luarRute]);
    }

    public function stok(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'items' => ['nullable', 'array'],
            'items.*.product_id' => ['required', 'integer', Rule::exists('mk_produk', 'id')],
            'items.*.qty' => ['required', 'numeric', 'min:0'],
            'competitors' => ['nullable', 'array'],
            'competitors.*.name' => ['required', 'string', 'max:150'],
            'competitors.*.qty' => ['required', 'numeric', 'min:0'],
        ]);

        $kunjungan = $this->terbuka($this->hari($request), $id);
        $sekarang = now();
        $baris = collect($request->input('items', []))->map(fn ($i) => [
            'kunjungan_id' => $kunjungan->id, 'produk_id' => $i['product_id'], 'produk_kompetitor' => null, 'qty' => $i['qty'], 'created_at' => $sekarang,
        ])->concat(collect($request->input('competitors', []))->map(fn ($k) => [
            'kunjungan_id' => $kunjungan->id, 'produk_id' => null, 'produk_kompetitor' => trim($k['name']), 'qty' => $k['qty'], 'created_at' => $sekarang,
        ]));

        // Kirim ulang mengganti isi sebelumnya, jadi aman diulang.
        DB::transaction(function () use ($kunjungan, $baris) {
            DB::table('trx_stok_outlet')->where('kunjungan_id', $kunjungan->id)->delete();
            DB::table('trx_stok_outlet')->insert($baris->all());
        });

        return $this->ok(['visit_id' => (int) $kunjungan->id, 'items' => $baris->count()]);
    }

    public function tidakBeli(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'reason_code' => ['required', Rule::in(array_keys(config('mobile.alasan_tidak_beli')))],
            'note' => ['nullable', 'string', 'max:255', Rule::requiredIf($request->input('reason_code') === 'lainnya')],
        ], ['note.required' => 'Keterangan wajib bila alasan Lainnya.']);

        $kunjungan = $this->terbuka($this->hari($request), $id);

        if (DB::table('trx_penjualan')->where('kunjungan_id', $kunjungan->id)->whereNull('dibatalkan_at')->exists()) {
            throw ApiError::konflik('visit_has_sale', 'Kunjungan ini sudah ada transaksi penjualan.');
        }

        DB::table('trx_kunjungan')->where('id', $kunjungan->id)->update([
            'tidak_beli_kategori' => $request->reason_code,
            'tidak_beli_keterangan' => $request->note,
        ]);

        return $this->ok(['visit_id' => (int) $kunjungan->id, 'reason_code' => $request->reason_code]);
    }

    public function checkOut(Request $request, int $id): JsonResponse
    {
        $request->validate($this->aturanLokasi(false));
        $kunjungan = $this->terbuka($this->hari($request), $id);
        $selesai = now();
        $durasi = max(0, (int) $selesai->diffInSeconds($kunjungan->checkin_at, true));

        DB::table('trx_kunjungan')->where('id', $kunjungan->id)->update(['checkout_at' => $selesai, 'durasi_detik' => $durasi]);

        return $this->ok(['visit_id' => (int) $kunjungan->id, 'duration_s' => $durasi, 'checked_out_at' => $this->waktu($selesai)]);
    }

    /** Kunjungan milik sales ini (lainnya 404) yang masih terbuka (sudah checkout => 409). */
    public static function terbuka(HariKerja $hari, int $id): object
    {
        $k = DB::table('trx_kunjungan')->where('id', $id)->where('karyawan_id', $hari->karyawanId)->first()
            ?? throw ApiError::tidakAda('Kunjungan tidak ditemukan.');

        if ($k->checkout_at) {
            throw ApiError::konflik('visit_closed', 'Kunjungan ini sudah checkout.');
        }

        return $k;
    }

    private function hariBelumDitutup(object $canvassing): void
    {
        if ($canvassing->dikembalikan_at) {
            throw ApiError::konflik('day_closed', 'Barang sudah dikembalikan; kunjungan hari ini sudah ditutup.');
        }
    }
}
