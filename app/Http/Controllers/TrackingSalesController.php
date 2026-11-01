<?php

namespace App\Http\Controllers;

use App\Support\Akses\Akses;
use App\Support\Tracking\AnomaliGps;
use App\Support\Tracking\AnomaliJarakKunjungan;
use App\Support\Tracking\KunjunganHarian;
use App\Support\Tracking\TitikGps;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

// Riwayat lokasi sales: data lama Marketama (tracking_sales_lokasi) digabung dengan
// titik GPS dari aplikasi HP sales motoris (trx_tracking, karyawan_id = sales_id).
class TrackingSalesController extends Controller
{
    public function index(Request $request)
    {
        $tanggal = substr((string) ($request->query('tanggal') ?? TitikGps::tanggalTerakhir('tracking_sales_lokasi', 'sales')), 0, 10);
        $people = $this->daftarSales();

        return Inertia::render('Tracking/History', [
            'slug' => 'tracking_sales_history_location',
            'title' => config('marketama.pages.tracking_sales_history_location.title'),
            'label' => 'Sales',
            'idParam' => 'sales_id',
            'people' => $people->values()->all(),
            'groups' => $people->whereNotNull('group_id')->sortBy('koordinator_nama')->pluck('koordinator_nama', 'group_id')->all(),
            'locationsUrl' => route('tracking-sales.locations'),
            'countsUrl' => route('tracking-sales.counts'),
            'tanggal' => $tanggal,
            'counts' => (object) $this->countsFor($tanggal),
            'pilihId' => $request->integer('sales_id') ?: null,
        ]);
    }

    /**
     * Jumlah titik lokasi per sales pada satu tanggal, untuk menandai sales yang punya data.
     */
    public function counts(Request $request)
    {
        $data = $request->validate(['tanggal' => ['required', 'date']]);

        return response()->json($this->countsFor($data['tanggal']));
    }

    public function locations(Request $request)
    {
        $data = $request->validate([
            'sales_id' => ['required', 'integer'],
            'tanggal' => ['required', 'date'],
        ]);

        $id = (int) $data['sales_id'];
        $sales = DB::table('tracking_sales')->where('sales_id', $id)->first()
            ?? $this->daftarSales()->firstWhere('id', $id);

        $depo = ($sales->depo_lat ?? null)
            ? (object) ['nama' => $sales->depo, 'latitude' => $sales->depo_lat, 'longitude' => $sales->depo_lng]
            : TitikGps::depoMaster($id);

        $lama = DB::table('tracking_sales_lokasi')
            ->where('sales_id', $id)
            ->whereDate('tanggal', $data['tanggal'])
            ->get(['lat', 'lng', 'waktu']);

        $locations = $lama->concat(TitikGps::untuk($id, $data['tanggal']))
            ->sortBy('waktu')->values();

        // Kunjungan outlet hanya untuk motoris dalam cakupan role akun (rute GPS di atas tidak dibatasi cakupan).
        $dalamCakupan = Akses::batasiKaryawan(DB::table('mk_karyawan as k')->where('k.id', $id), $request->user())->exists();

        $kunjungan = $dalamCakupan ? KunjunganHarian::untuk($id, $data['tanggal']) : collect();

        return response()->json([
            'sales' => $sales,
            'depo' => $depo,
            'total' => $locations->count(),
            'locations' => $locations,
            'kunjungan' => $kunjungan,
            // Indikasi GPS tidak wajar pada tanggal ini saja (null bila di bawah ambang).
            'anomali_gps' => AnomaliGps::analisis($locations, $kunjungan->where('lokasi_palsu', true)->count()),
            // Indikasi kunjungan outlet berturutan berjarak dekat (spec 054), null bila di bawah ambang.
            'indikasi_jarak_kunjungan' => AnomaliJarakKunjungan::analisis($kunjungan),
        ]);
    }

    // ------------------------------------------------------------------

    /** Sales dari data lama + master karyawan aktif (yang memakai aplikasi HP), tanpa ganda. */
    private function daftarSales(): Collection
    {
        $lama = DB::table('tracking_sales')
            ->get(['sales_id as id', 'nama', 'telepon', 'depo', 'koordinator_id as group_id', 'koordinator_nama']);
        $ada = $lama->pluck('id')->flip();

        $master = DB::table('mk_karyawan as k')
            ->leftJoin('mk_depo as d', 'd.id', '=', 'k.depo_id')
            ->leftJoin('mk_karyawan as ko', 'ko.id', '=', 'k.koordinator_id')
            ->where('k.kategori', 'sales')->where('k.aktif', true)
            ->get(['k.id', 'k.nama', 'k.no_hp as telepon', 'd.nama as depo', 'k.koordinator_id as group_id', 'ko.nama as koordinator_nama'])
            ->reject(fn ($k) => isset($ada[$k->id]));

        return $lama->concat($master)->sortBy('nama', SORT_NATURAL | SORT_FLAG_CASE)->values();
    }

    private function countsFor(string $tanggal): array
    {
        $lama = DB::table('tracking_sales_lokasi')
            ->whereDate('tanggal', $tanggal)
            ->groupBy('sales_id')
            ->selectRaw('sales_id, count(*) as total')
            ->pluck('total', 'sales_id')
            ->map(fn ($total) => (int) $total)
            ->all();

        return TitikGps::tambahJumlah($lama, 'sales', $tanggal);
    }
}
