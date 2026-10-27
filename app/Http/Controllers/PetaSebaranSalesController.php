<?php

namespace App\Http\Controllers;

use App\Support\Menu\Motoris\SumberLastSeenSales;
use App\Support\Mobile\Pengaturan;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

// Peta Sebaran Sales: posisi GPS terakhir semua sales aktif se-Indonesia (data yang sama dengan menu
// Tracking Last Seen Sales, termasuk batas cakupan role), dikelompokkan per depo saat peta diperkecil.
class PetaSebaranSalesController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Tracking/Sebaran', [
            'slug' => 'tracking_sales',
            'title' => 'Peta Sebaran Sales',
            'dataUrl' => route('peta-sebaran-sales.data'),
            'lastSeenUrl' => route('menu', 'tracking_sales'),
        ]);
    }

    /** Semua sales aktif dalam cakupan akun, dengan posisi terakhir dan status kebaruannya. */
    public function data(Request $request, SumberLastSeenSales $sumber): JsonResponse
    {
        $sekarang = Pengaturan::sekarang();
        $hariIni = $sekarang->startOfDay();

        $sales = $sumber->query($request->user())->addSelect(['k.depo_id', 'k.koordinator_id'])->get()
            ->map(fn ($b) => $sumber->ubahBaris($b))
            ->map(function ($b) use ($sekarang, $hariIni) {
                $waktu = $b->sumber ? CarbonImmutable::parse($b->terakhir_terlihat, $sekarang->getTimezone()) : null;
                $status = match (true) {
                    $b->latitude === null || $b->longitude === null || ! $waktu => 'belum',
                    $waktu->gte($hariIni) => 'hari_ini',
                    $waktu->gte($hariIni->subDays(7)) => 'minggu',
                    default => 'lama',
                };

                return [
                    'id' => (int) $b->id,
                    'nama' => $b->nama,
                    'telepon' => $b->telepon,
                    'depoId' => $b->depo_id !== null ? (int) $b->depo_id : null,
                    'depo' => $b->depo,
                    'koordinator' => $b->koordinator,
                    'lat' => $b->latitude,
                    'lng' => $b->longitude,
                    'waktu' => $b->sumber ? $b->terakhir_terlihat : null,
                    'sejak' => $b->sejak,
                    'sumber' => $b->sumber,
                    'baterai' => $b->baterai,
                    'lokasiPalsu' => (bool) $b->lokasi_palsu,
                    'status' => $status,
                ];
            })->values();

        $depoIds = $sales->pluck('depoId')->filter()->unique()->values()->all();
        $depo = DB::table('area_depo')->whereIn('warehouse_id', $depoIds ?: [0])->get(['warehouse_id', 'name', 'lat', 'lng'])
            ->map(fn ($d) => [
                'id' => (int) $d->warehouse_id,
                'nama' => $d->name,
                'lat' => $d->lat !== null ? (float) $d->lat : null,
                'lng' => $d->lng !== null ? (float) $d->lng : null,
            ])->values();

        return response()->json([
            'sales' => $sales,
            'depo' => $depo,
            'diperbarui' => $sekarang->format('Y-m-d H:i'),
        ]);
    }
}
