<?php

namespace App\Http\Controllers\Mobile;

use App\Support\Mobile\ApiError;
use App\Support\Mobile\HariKerja;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// Barang canvassing: muatan hari ini yang disiapkan staf depo, terima (dengan selisih),
// sisa bawaan, dan pengembalian di akhir hari (layar Terima barang & Tutup hari).
class CanvassingController extends MobileController
{
    public function muatan(Request $request): JsonResponse
    {
        $hari = $this->hari($request);
        $c = $hari->canvassing();

        return $this->ok($c ? $this->tampil($hari, $c) : null);
    }

    public function terima(Request $request, string $nomor): JsonResponse
    {
        $request->validate($this->aturanUmum() + [
            'items' => ['nullable', 'array'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.qty_received' => ['required', 'numeric', 'min:0'],
            'items.*.reason' => ['nullable', 'string', 'max:255'],
        ]);

        $hari = $this->hari($request);
        $c = $this->milik($hari, $nomor);

        if ($c->diterima_at) {
            throw ApiError::konflik('already_accepted', 'Barang canvassing ini sudah diterima.');
        }

        if (! $hari->checkinDepo()) {
            throw ApiError::konflik('depot_check_in_required', 'Checkin depo dulu sebelum terima barang.');
        }

        $item = DB::table('trx_canvassing_item')->where('canvassing_id', $c->id)->get()->keyBy('produk_id');
        $salah = [];

        foreach ($request->input('items', []) as $i => $baris) {
            $produk = $item[$baris['product_id']] ?? null;

            if (! $produk) {
                $salah["items.$i.product_id"] = ['Produk tidak ada di muatan canvassing ini.'];
            } elseif (round((float) $baris['qty_received'], 2) != round((float) $produk->qty_bawa, 2) && blank($baris['reason'] ?? null)) {
                $salah["items.$i.reason"] = ['Alasan selisih wajib diisi.'];
            }
        }

        if ($salah) {
            throw new ApiError('validation_failed', 'Periksa lagi barang yang diterima.', 422, $salah);
        }

        DB::transaction(function () use ($request, $c) {
            foreach ($request->input('items', []) as $baris) {
                DB::table('trx_canvassing_item')->where('canvassing_id', $c->id)->where('produk_id', $baris['product_id'])
                    ->update(['qty_diterima' => $baris['qty_received'], 'alasan_selisih_terima' => $baris['reason'] ?? null]);
            }

            DB::table('trx_canvassing_item')->where('canvassing_id', $c->id)->whereNull('qty_diterima')
                ->update(['qty_diterima' => DB::raw('qty_bawa')]);

            DB::table('trx_canvassing')->where('id', $c->id)->update(['status' => 'diterima', 'diterima_at' => now()] + $this->kolomUmum($request));
        });

        return $this->ok($this->tampil($hari, DB::table('trx_canvassing')->find($c->id)));
    }

    public function sisa(Request $request, string $nomor): JsonResponse
    {
        $hari = $this->hari($request);

        return $this->ok($this->tampil($hari, $this->milik($hari, $nomor, false)));
    }

    public function kembalikan(Request $request, string $nomor): JsonResponse
    {
        $request->validate($this->aturanUmum() + [
            'items' => ['nullable', 'array'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.qty_returned' => ['required', 'numeric', 'min:0'],
            'items.*.reason' => ['nullable', 'string', 'max:255'],
            'cash_amount' => ['nullable', 'integer', 'min:0'],
        ]);

        $hari = $this->hari($request);
        $c = $this->milik($hari, $nomor);

        if (! $c->diterima_at) {
            throw ApiError::konflik('not_accepted', 'Barang canvassing ini belum diterima.');
        }

        if ($c->dikembalikan_at) {
            throw ApiError::konflik('already_returned', 'Sisa barang sudah diserahkan.');
        }

        $hari->tanpaKunjunganTerbuka();
        $sisa = $hari->sisa($c);
        $kembali = $sisa->map(fn ($s) => ['qty' => $s['sisa'], 'alasan' => null]);
        $salah = [];

        foreach ($request->input('items', []) as $i => $baris) {
            $s = $sisa[$baris['product_id']] ?? null;

            if (! $s) {
                $salah["items.$i.product_id"] = ['Produk tidak ada di muatan canvassing ini.'];

                continue;
            }

            $qty = round((float) $baris['qty_returned'], 2);

            if ($qty > $s['dibawa']) {
                $salah["items.$i.qty_returned"] = ["Melebihi jumlah dibawa ({$s['dibawa']})."];
            } elseif ($qty != $s['sisa'] && blank($baris['reason'] ?? null)) {
                $salah["items.$i.reason"] = ["Seharusnya kembali {$s['sisa']}; isi alasan selisih."];
            }

            $kembali[$s['produk_id']] = ['qty' => $qty, 'alasan' => $baris['reason'] ?? null];
        }

        if ($salah) {
            throw new ApiError('return_mismatch', 'Jumlah barang kembali tidak sesuai sisa bawaan.', 422, $salah);
        }

        DB::transaction(function () use ($request, $c, $kembali) {
            foreach ($kembali as $produkId => $k) {
                DB::table('trx_canvassing_item')->where('canvassing_id', $c->id)->where('produk_id', $produkId)
                    ->update(['qty_kembali' => $k['qty'], 'alasan_selisih_kembali' => $k['alasan']]);
            }

            DB::table('trx_canvassing')->where('id', $c->id)->update([
                'status' => 'dikembalikan',
                'dikembalikan_at' => now(),
                'return_client_ref' => $request->client_ref,
            ]);
        });

        $setoran = (int) DB::table('trx_penjualan')->where('canvassing_id', $c->id)->whereNull('dibatalkan_at')->sum('total');

        return $this->ok($this->tampil($hari, DB::table('trx_canvassing')->find($c->id)) + ['cash_to_deposit' => $setoran]);
    }

    /** Canvassing milik sales ini (orang lain => 404); $hariIni = wajib tanggal hari ini. */
    private function milik(HariKerja $hari, string $nomor, bool $hariIni = true): object
    {
        $c = DB::table('trx_canvassing')->where('nomor', $nomor)->where('karyawan_id', $hari->karyawanId)->first()
            ?? throw ApiError::tidakAda('Nomor canvassing tidak ditemukan.');

        if ($hariIni && substr((string) $c->tanggal, 0, 10) !== $hari->tanggal) {
            throw ApiError::konflik('not_today', 'Nomor canvassing ini bukan untuk hari ini.');
        }

        return $c;
    }

    private function tampil(HariKerja $hari, object $c): array
    {
        return [
            'number' => $c->nomor,
            'date' => substr((string) $c->tanggal, 0, 10),
            'status' => $c->status,
            'accepted_at' => $this->waktu($c->diterima_at),
            'returned_at' => $this->waktu($c->dikembalikan_at),
            'items' => $hari->sisa($c)->map(fn ($s) => [
                'product_id' => $s['produk_id'],
                'code' => $s['kode'],
                'name' => $s['nama'],
                'price' => $s['harga'],
                'qty_prepared' => $s['disiapkan'],
                'qty_carried' => $s['dibawa'],
                'qty_sold' => $s['terjual'],
                'qty_remaining' => $s['sisa'],
            ])->values(),
        ];
    }
}
