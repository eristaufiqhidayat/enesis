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

// Barang Canvassing: detail produk satu muatan untuk drawer di tabel (lihat Menu/Show.vue), opsi & simpan muatan
// baru dari web (status disiapkan; aplikasi HP sales yang menerimanya), ubah item, dan batalkan (soft delete) selama masih Disiapkan.
class BarangCanvassingController extends Controller
{
    public function item(Request $request, int $canvassing)
    {
        $sumber = (new SumberCanvassing)->termasukBelumDiterima();

        $baris = $sumber->query($request->user())
            ->where('c.id', $canvassing)
            ->get();

        abort_if($baris->isEmpty(), 404);

        $muatan = DB::table('trx_canvassing')->where('id', $canvassing)->first(['status', 'diterima_at']);

        return response()->json([
            'data' => $baris->map(fn ($row) => $sumber->ubahBaris($row))->values(),
            // Item masih bisa diubah: muatan belum diterima motoris dan akun punya hak ubah (lihat ubahItem).
            'dapat_diubah' => $this->masihDisiapkan($muatan) && Akses::bisa($request->user(), 'barang_canvassing', 'ubah'),
            // Muatan masih bisa dibatalkan: belum diterima motoris dan akun punya hak hapus (lihat batalkan).
            'dapat_dibatalkan' => $this->masihDisiapkan($muatan) && Akses::bisa($request->user(), 'barang_canvassing', 'hapus'),
        ]);
    }

    /**
     * Ganti daftar item muatan yang masih Disiapkan: qty diubah, produk baru ditambah, produk yang dihilangkan
     * dihapus. Baris muatan dikunci selama transaksi supaya tidak berebut dengan penerimaan di aplikasi HP.
     */
    public function ubahItem(Request $request, int $canvassing)
    {
        $data = $request->validate($this->aturanItem(), $this->pesanItem());

        abort_unless(SumberCanvassing::dasar($request->user(), hanyaDiterima: false)->where('c.id', $canvassing)->exists(), 404);

        $nomor = DB::transaction(function () use ($canvassing, $data) {
            $muatan = DB::table('trx_canvassing')->where('id', $canvassing)->lockForUpdate()->first();

            abort_unless($this->masihDisiapkan($muatan), 409, 'Muatan sudah diterima motoris, tidak bisa diubah.');

            $lama = DB::table('trx_canvassing_item')->where('canvassing_id', $canvassing)->pluck('id', 'produk_id');
            $baru = collect($data['items'])->keyBy('produk_id');

            foreach ($baru as $produk => $item) {
                if ($lama->has($produk)) {
                    DB::table('trx_canvassing_item')->where('id', $lama[$produk])->update(['qty_bawa' => $item['qty_bawa']]);
                } else {
                    DB::table('trx_canvassing_item')->insert(['canvassing_id' => $canvassing, 'produk_id' => $produk, 'qty_bawa' => $item['qty_bawa']]);
                }
            }

            DB::table('trx_canvassing_item')->whereIn('id', $lama->except($baru->keys()->all())->values())->delete();

            return $muatan->nomor;
        });

        Akses::catat($request->user(), 'barang_canvassing', $canvassing, 'ubah', "Item muatan $nomor");

        return response()->json(['ok' => true]);
    }

    /**
     * Batalkan (soft delete) muatan yang masih Disiapkan: status jadi dibatalkan, dibatalkan_at dan dibatalkan_oleh
     * (ID akun yang login) terisi, item tetap ada. Muatan yang dibatalkan hilang dari daftar dan aplikasi HP.
     * Baris muatan dikunci selama transaksi supaya tidak berebut dengan penerimaan di aplikasi HP.
     */
    public function batalkan(Request $request, int $canvassing)
    {
        abort_unless(SumberCanvassing::dasar($request->user(), hanyaDiterima: false)->where('c.id', $canvassing)->exists(), 404);

        $nomor = DB::transaction(function () use ($request, $canvassing) {
            $muatan = DB::table('trx_canvassing')->where('id', $canvassing)->lockForUpdate()->first();

            abort_unless($this->masihDisiapkan($muatan), 409, 'Muatan sudah diterima motoris, tidak bisa dibatalkan.');

            DB::table('trx_canvassing')->where('id', $canvassing)->update([
                'status' => 'dibatalkan',
                'dibatalkan_at' => now(),
                'dibatalkan_oleh' => $request->user()->id,
            ]);

            return $muatan->nomor;
        });

        Akses::catat($request->user(), 'barang_canvassing', $canvassing, 'hapus', "Batalkan muatan $nomor");

        return response()->json(['ok' => true]);
    }

    /** Muatan Disiapkan = status disiapkan dan belum ada waktu terima (aplikasi HP mengisi diterima_at saat menerima). */
    private function masihDisiapkan(?object $muatan): bool
    {
        return $muatan !== null && $muatan->status === 'disiapkan' && $muatan->diterima_at === null;
    }

    /** Aturan daftar item, dipakai bersama simpan muatan baru dan ubah item. */
    private function aturanItem(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.produk_id' => ['required', 'integer', 'distinct', Rule::exists('mk_produk', 'id')],
            'items.*.qty_bawa' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
        ];
    }

    private function pesanItem(): array
    {
        return [
            'items.required' => 'Tambahkan minimal satu produk.',
            'items.min' => 'Tambahkan minimal satu produk.',
            'items.*.produk_id.distinct' => 'Produk tidak boleh dobel.',
            'items.*.qty_bawa.gt' => 'Qty harus lebih dari 0.',
        ];
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
        ] + $this->aturanItem(), $this->pesanItem());

        $tanggal = $data['tanggal'] ?? now(config('mobile.zona_waktu'))->toDateString();

        if (! $this->salesBoleh($request, (int) $data['depo_id'])->where('k.id', $data['karyawan_id'])->exists()) {
            throw ValidationException::withMessages(['karyawan_id' => 'Motoris tidak valid: harus Sales Motoris aktif di depo ini, punya koordinator, dan dalam cakupan Anda.']);
        }

        $ada = DB::table('trx_canvassing')->where('karyawan_id', $data['karyawan_id'])->where('tanggal', $tanggal)
            ->whereNull('dibatalkan_at')->value('nomor'); // muatan yang dibatalkan tidak dihitung
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
