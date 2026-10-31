<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Admin\KuotaMotorisController;
use App\Support\Akses\Akses;
use App\Support\Menu\Motoris\SumberCanvassing;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

// Barang Canvassing: detail produk satu muatan untuk drawer di tabel (lihat Menu/Show.vue), serta opsi & simpan
// muatan baru dari web (status disiapkan; aplikasi HP sales yang menerimanya).
class BarangCanvassingController extends Controller
{
    public function item(Request $request, int $canvassing)
    {
        $sumber = (new SumberCanvassing)->termasukBelumDiterima();

        $baris = $sumber->query($request->user())
            ->where('c.id', $canvassing)
            ->get();

        abort_if($baris->isEmpty(), 404);

        return response()->json([
            'data' => $baris->map(fn ($row) => $sumber->ubahBaris($row))->values(),
        ]);
    }

    /**
     * Opsi form tambah (modal di Menu/Show.vue): depo dalam cakupan role dan tanggal default selalu ikut;
     * sales (dibatasi cakupan role) dan produk baru dimuat setelah satu depo dipilih (?depo=).
     */
    public function opsi(Request $request)
    {
        $depo = $request->integer('depo');
        $cakupan = KuotaMotorisController::depoCakupan($request);

        return response()->json([
            'tanggal' => now(config('mobile.zona_waktu'))->toDateString(),
            'depo' => DB::table('mk_depo')->when($cakupan !== null, fn ($q) => $q->whereIn('id', $cakupan ?: [0]))
                ->orderBy('nama')->get(['id', 'nama'])
                ->map(fn ($d) => ['value' => (int) $d->id, 'label' => $d->nama])->values(),
            'sales' => $depo ? $this->salesBoleh($request, $depo)
                ->orderBy('k.nama')->get(['k.id', 'k.nama'])
                ->map(fn ($k) => ['id' => (int) $k->id, 'nama' => $k->nama])->values() : [],
            'produk' => $depo ? DB::table('mk_produk')->orderBy('nama')->get(['id', 'kode', 'nama'])
                ->map(fn ($p) => ['id' => (int) $p->id, 'kode' => $p->kode, 'nama' => $p->nama])->values() : [],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'tanggal' => ['nullable', 'date_format:Y-m-d'],
            'depo_id' => ['required', 'integer', Rule::exists('mk_depo', 'id')],
            'karyawan_id' => ['required', 'integer'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.produk_id' => ['required', 'integer', 'distinct', Rule::exists('mk_produk', 'id')],
            'items.*.qty_bawa' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
        ], [
            'items.required' => 'Tambahkan minimal satu produk.',
            'items.min' => 'Tambahkan minimal satu produk.',
            'items.*.produk_id.distinct' => 'Produk tidak boleh dobel.',
            'items.*.qty_bawa.gt' => 'Qty harus lebih dari 0.',
        ]);

        $tanggal = $data['tanggal'] ?? now(config('mobile.zona_waktu'))->toDateString();

        if (! $this->salesBoleh($request, (int) $data['depo_id'])->where('k.id', $data['karyawan_id'])->exists()) {
            throw ValidationException::withMessages(['karyawan_id' => 'Motoris tidak valid: harus Sales Motoris aktif di depo ini, punya koordinator, dan dalam cakupan Anda.']);
        }

        $ada = DB::table('trx_canvassing')->where('karyawan_id', $data['karyawan_id'])->where('tanggal', $tanggal)->value('nomor');
        if ($ada) {
            throw ValidationException::withMessages(['karyawan_id' => "Motoris ini sudah punya muatan di tanggal tersebut ($ada)."]);
        }

        $nomor = DB::transaction(function () use ($data, $tanggal) {
            $id = DB::table('trx_canvassing')->insertGetId([
                'nomor' => 'TMP-'.uniqid(),
                'karyawan_id' => $data['karyawan_id'],
                'depo_id' => $data['depo_id'],
                'tanggal' => $tanggal,
                'status' => 'disiapkan',
                'disiapkan_at' => now(),
                'sumber' => 'web',
            ]);
            $nomor = 'CNV'.str_replace('-', '', $tanggal).'-'.str_pad((string) $id, 5, '0', STR_PAD_LEFT);
            DB::table('trx_canvassing')->where('id', $id)->update(['nomor' => $nomor]);
            DB::table('trx_canvassing_item')->insert(array_map(
                fn ($i) => ['canvassing_id' => $id, 'produk_id' => $i['produk_id'], 'qty_bawa' => $i['qty_bawa']],
                $data['items'],
            ));

            return $nomor;
        });

        Akses::catat($request->user(), 'barang_canvassing', $data['karyawan_id'], 'tambah', "Muatan $nomor");

        // Kembali ke daftar pada tanggal muatan baru, supaya langsung terlihat (daftar hanya menampilkan satu hari).
        return redirect('/barang_canvassing?tanggal='.$tanggal)->with('sukses', "Muatan $nomor disiapkan.");
    }

    /** Sales Motoris aktif di depo itu yang punya Sales Koordinator, dalam cakupan role (alias k). */
    private function salesBoleh(Request $request, int $depo): Builder
    {
        $q = DB::table('mk_karyawan as k')
            ->join('mk_karyawan as ko', 'ko.id', '=', 'k.koordinator_id')
            ->where('k.kategori', 'sales')->where('ko.kategori', 'koordinator')
            ->where('k.aktif', true)->where('k.depo_id', $depo);

        return Akses::batasiKaryawan($q, $request->user());
    }
}
