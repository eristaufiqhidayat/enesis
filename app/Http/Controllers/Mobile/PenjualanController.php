<?php

namespace App\Http\Controllers\Mobile;

use App\Support\Mobile\ApiError;
use App\Support\Mobile\Pengaturan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// Penjualan dari satu kunjungan (layar Order). Server menghitung ulang harga, total, poin,
// dan status EC dari data master; client_total hanya untuk mendeteksi harga usang di HP.
class PenjualanController extends MobileController
{
    public function simpan(Request $request): JsonResponse
    {
        $request->validate($this->aturanUmum() + [
            'visit_id' => ['required', 'integer'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'distinct'],
            'items.*.qty' => ['required', 'numeric', 'gt:0', 'max:100000'],
            'client_total' => ['nullable', 'integer', 'min:0'],
        ], ['items.required' => 'Pilih minimal satu produk.', 'items.*.product_id.distinct' => 'Produk yang sama dipilih dua kali.']);

        $karyawan = $this->karyawan($request);
        $hari = $this->hari($request);
        $kunjungan = KunjunganController::terbuka($hari, (int) $request->visit_id);
        $canvassing = $hari->wajibCanvassing();

        if ($canvassing->dikembalikan_at) {
            throw ApiError::konflik('day_closed', 'Barang sudah dikembalikan; tidak bisa membuat order lagi.');
        }

        if (DB::table('trx_penjualan')->where('kunjungan_id', $kunjungan->id)->whereNull('dibatalkan_at')->exists()) {
            throw ApiError::konflik('visit_has_sale', 'Kunjungan ini sudah ada transaksi penjualan.');
        }

        $sisa = $hari->sisa($canvassing);
        $produk = DB::table('mk_produk')->whereIn('id', collect($request->items)->pluck('product_id'))->get()->keyBy('id');
        $salah = [];
        $baris = [];

        foreach ($request->items as $i => $item) {
            $p = $produk[$item['product_id']] ?? null;
            $qty = round((float) $item['qty'], 2);

            if (! $p || $p->harga_jual === null) {
                $salah["items.$i.product_id"] = ['Produk tidak aktif atau belum punya harga.'];

                continue;
            }

            $tersedia = $sisa[$p->id]['sisa'] ?? 0;

            if ($qty > $tersedia) {
                $salah["items.$i.qty"] = ["Melebihi sisa bawaan ($tersedia)."];

                continue;
            }

            $harga = (int) round((float) $p->harga_jual);
            $poin = Pengaturan::poinProduk($p);
            $poinItem = $poin['fokus'] ? (int) floor($qty * config('mobile.poin_per_qty_produk_fokus')) : 0;

            if ($poin['maks_poin'] !== null) {
                $poinItem = min($poinItem, $poin['maks_poin']);
            }

            $baris[] = ['produk_id' => (int) $p->id, 'qty' => $qty, 'harga' => $harga, 'diskon' => 0, 'total' => (int) round($qty * $harga), 'poin' => $poinItem];
        }

        if ($salah) {
            $kode = collect($salah)->keys()->contains(fn ($k) => str_ends_with($k, '.qty')) ? 'exceeds_stock' : 'product_inactive';

            throw new ApiError($kode, 'Periksa lagi produk dan jumlah order.', 422, $salah);
        }

        $total = array_sum(array_column($baris, 'total'));
        $depo = Pengaturan::depo($karyawan->depo_id ? (int) $karyawan->depo_id : null);
        $minimal = $depo['minimal_transaksi'] ?? config('mobile.minimal_transaksi_default');
        $ec = $total >= $minimal;

        $id = DB::transaction(function () use ($request, $karyawan, $hari, $kunjungan, $canvassing, $baris, $total, $ec) {
            $id = DB::table('trx_penjualan')->insertGetId([
                'kode' => 'TMP-'.$request->client_ref,
                'kunjungan_id' => $kunjungan->id,
                'karyawan_id' => $karyawan->id,
                'depo_id' => $karyawan->depo_id,
                'outlet_id' => $kunjungan->outlet_id,
                'canvassing_id' => $canvassing->id,
                'tanggal' => $hari->tanggal,
                'jumlah_sku' => count($baris),
                'total_qty' => array_sum(array_column($baris, 'qty')),
                'total' => $total,
                'poin' => array_sum(array_column($baris, 'poin')),
                'is_effective_call' => $ec,
                'client_total' => $request->client_total,
            ] + $this->kolomUmum($request));

            DB::table('trx_penjualan')->where('id', $id)->update(['kode' => 'MB'.str_replace('-', '', $hari->tanggal).'-'.str_pad((string) $id, 7, '0', STR_PAD_LEFT)]);
            DB::table('trx_penjualan_item')->insert(array_map(fn ($b) => ['penjualan_id' => $id] + $b, $baris));

            return $id;
        });

        return $this->dibuat($this->tampil(DB::table('trx_penjualan')->find($id)) + [
            'min_transaction_value' => $minimal,
            'price_changed' => $request->filled('client_total') && (int) $request->client_total !== $total,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);
        $tanggal = $request->input('date', $this->hariIni());

        $data = DB::table('trx_penjualan')->where('karyawan_id', $this->karyawan($request)->id)->where('tanggal', $tanggal)
            ->orderBy('id')->get()->map(fn ($p) => $this->tampil($p));

        return $this->ok($data, ['date' => $tanggal, 'total' => $data->where('is_void', false)->sum('total')]);
    }

    public function batal(Request $request, int $id): JsonResponse
    {
        $request->validate(['reason' => ['required', 'string', 'min:3', 'max:255']], ['reason.required' => 'Alasan pembatalan wajib.']);

        $jual = DB::table('trx_penjualan')->where('id', $id)->where('karyawan_id', $this->karyawan($request)->id)->first()
            ?? throw ApiError::tidakAda('Transaksi tidak ditemukan.');

        if ($jual->dibatalkan_at) {
            throw ApiError::konflik('already_voided', 'Transaksi ini sudah dibatalkan.');
        }

        if (substr((string) $jual->tanggal, 0, 10) !== $this->hariIni()) {
            throw ApiError::konflik('void_not_allowed', 'Hanya transaksi hari ini yang bisa dibatalkan.');
        }

        $canvassing = DB::table('trx_canvassing')->find($jual->canvassing_id);

        if ($canvassing?->dikembalikan_at) {
            throw ApiError::konflik('day_closed', 'Barang sudah dikembalikan; transaksi tidak bisa dibatalkan lagi.');
        }

        DB::table('trx_penjualan')->where('id', $id)->update(['dibatalkan_at' => now(), 'alasan_batal' => $request->reason]);

        return $this->ok($this->tampil(DB::table('trx_penjualan')->find($id)));
    }

    private function tampil(object $p): array
    {
        return [
            'sale_id' => (int) $p->id,
            'code' => $p->kode,
            'visit_id' => (int) $p->kunjungan_id,
            'outlet_id' => $p->outlet_id ? (int) $p->outlet_id : null,
            'date' => substr((string) $p->tanggal, 0, 10),
            'sku_count' => (int) $p->jumlah_sku,
            'total' => (int) $p->total,
            'points' => (int) $p->poin,
            'is_effective_call' => (bool) $p->is_effective_call,
            'is_void' => $p->dibatalkan_at !== null,
            'void_reason' => $p->alasan_batal,
            'created_at' => $this->waktu($p->received_at),
            'items' => DB::table('trx_penjualan_item as i')->leftJoin('mk_produk as m', 'm.id', '=', 'i.produk_id')
                ->where('i.penjualan_id', $p->id)->orderBy('i.id')->get(['i.*', 'm.nama'])
                ->map(fn ($i) => [
                    'product_id' => (int) $i->produk_id,
                    'name' => $i->nama,
                    'qty' => $this->qty($i->qty),
                    'price' => (int) $i->harga,
                    'total' => (int) $i->total,
                    'points' => (int) $i->poin,
                ]),
        ];
    }
}
