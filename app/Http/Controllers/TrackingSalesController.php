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

// Riwayat lokasi sales: data lama Marketama (tracking_sales_lokasi) digabung dengan
// titik GPS dari aplikasi HP sales motoris (trx_tracking, karyawan_id = sales_id).
class TrackingSalesController extends Controller
{
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
            // Indikasi kunjungan outlet berturutan berjarak dekat (spec 054): daftar runtun yang memenuhi ambang
            // (bisa lebih dari satu per hari), urut dari yang terpanjang; array kosong bila tidak ada.
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
}
