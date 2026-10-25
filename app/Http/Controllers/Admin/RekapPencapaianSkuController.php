<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Akses\Akses;
use App\Support\Motoris\RekapPencapaianSku;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

// Menu Utama › Rekap Pencapaian SKU: tab Per Motoris dan Per Depo dihitung dari penjualan aplikasi motoris
// (trx_penjualan), bukan lagi dari tabel laporan Marketama. Parameter filter memakai nama yang sama dengan
// Marketama (date_from, date_to, warehouse_id[], product_id[]) supaya tautan lama tetap jalan.
class RekapPencapaianSkuController extends Controller
{
    public const MENU = 'rekap_pencapaian_sku';

    public const TAB = ['motoris' => 'Per Motoris', 'depo' => 'Per Depo'];

    public function index(Request $request): Response
    {
        $tab = array_key_exists($request->query('tab'), self::TAB) ? $request->query('tab') : 'motoris';
        $hariIni = RekapPencapaianSku::hariIni();
        $tanggal = fn (string $k) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->query($k)) ? $request->query($k) : $hariIni;
        [$dari, $sampai] = [$tanggal('date_from'), $tanggal('date_to')];
        if ($sampai < $dari) {
            [$dari, $sampai] = [$sampai, $dari];
        }

        $daftarDepo = $this->daftarDepo($request);
        $ids = fn (string $k) => collect((array) $request->query($k))->map(fn ($v) => (int) $v)->filter()->unique()->values()->all();
        $depo = array_values(array_intersect($ids('warehouse_id'), $daftarDepo->keys()->map(fn ($id) => (int) $id)->all()));
        $produk = $ids('product_id');

        $rekap = new RekapPencapaianSku($dari, $sampai, $depo, $produk, $request->user());
        // Hitungan per motoris/depo paling berat; ditunda supaya halaman tampil dulu (lihat Motoris/RekapSku.vue).
        $hasil = Inertia::defer(fn () => $tab === 'depo' ? $rekap->perDepo() : $rekap->perMotoris(), 'hasil');

        return Inertia::render('Motoris/RekapSku', [
            'slug' => self::MENU,
            'judul' => config('marketama.pages.'.self::MENU.'.title') ?? 'Rekap Pencapaian SKU',
            'tab' => $tab,
            'tabs' => collect(self::TAB)->map(fn ($label, $kode) => ['key' => $kode, 'label' => $label])->values(),
            'periode' => [
                'dari' => self::tanggal($dari),
                'sampai' => self::tanggal($sampai),
                'sama' => $dari === $sampai,
                'hkStandar' => $rekap->hkStandar,
                'target' => $rekap->target,
            ],
            'hasil' => $hasil,
            'filter' => ['date_from' => $dari, 'date_to' => $sampai, 'warehouse_id' => $depo, 'product_id' => $produk],
            'daftarDepo' => $daftarDepo,
            'daftarProduk' => DB::table('mk_produk')->orderBy('nama')->pluck('nama', 'id'),
            'bolehUbah' => Akses::bisa($request->user(), self::MENU, 'ubah'),
        ]);
    }

    private static function tanggal(string $tanggal): string
    {
        return Carbon::parse($tanggal)->locale('id')->translatedFormat('d M Y');
    }

    public function simpanTarget(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'target' => ['required', 'numeric', 'min:0.01', 'max:100000'],
        ], ['target.min' => 'Target harus lebih dari 0.', 'target.numeric' => 'Target harus angka.']);

        $lama = RekapPencapaianSku::target();
        $baru = round((float) $data['target'], 2);

        if ($baru !== $lama) {
            RekapPencapaianSku::simpanTarget($baru, $request->user());
            Akses::catat($request->user(), 'target_rekap_sku', 0, 'ubah', "Target {$lama} → {$baru} /hari");
        }

        return redirect()->route('rekap-sku', $request->only(['tab', 'date_from', 'date_to', 'warehouse_id', 'product_id']))
            ->with('sukses', $baru !== $lama ? "Target disimpan: {$this->angka($baru)} /hari." : 'Target tidak berubah.');
    }

    private function angka(float $n): string
    {
        return rtrim(rtrim(number_format($n, 2, ',', '.'), '0'), ',');
    }

    private function daftarDepo(Request $request)
    {
        $cakupan = KuotaMotorisController::depoCakupan($request);

        return DB::table('mk_depo')->when($cakupan !== null, fn ($q) => $q->whereIn('id', $cakupan ?: [0]))
            ->orderBy('nama')->pluck('nama', 'id');
    }
}
