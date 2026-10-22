<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TrackingKoordinatorController extends Controller
{
    public function index(Request $request)
    {
        $tanggal = $request->query('tanggal')
            ?? DB::table('tracking_koordinator_lokasi')->max('tanggal')
            ?? now()->toDateString();

        return view('tracking.koordinator_history', [
            'page' => config('marketama.pages.tracking_koordinator_history_location'),
            'koordinators' => DB::table('tracking_koordinator')->orderBy('nama')->get(),
            'tanggal' => $tanggal,
            'counts' => $this->countsFor($tanggal),
        ]);
    }

    /**
     * Jumlah titik lokasi per koordinator pada satu tanggal, untuk menandai koordinator yang punya data.
     */
    public function counts(Request $request)
    {
        $data = $request->validate(['tanggal' => ['required', 'date']]);

        return response()->json($this->countsFor($data['tanggal']));
    }

    private function countsFor(string $tanggal): array
    {
        return DB::table('tracking_koordinator_lokasi')
            ->whereDate('tanggal', $tanggal)
            ->groupBy('koordinator_id')
            ->selectRaw('koordinator_id, count(*) as total')
            ->pluck('total', 'koordinator_id')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    public function locations(Request $request)
    {
        $data = $request->validate([
            'koordinator_id' => ['required', 'integer'],
            'tanggal' => ['required', 'date'],
        ]);

        $koordinator = DB::table('tracking_koordinator')->where('koordinator_id', $data['koordinator_id'])->first();

        $depo = $koordinator?->depo
            ? DB::table('master_depo')->where('nama', $koordinator->depo)->first(['nama', 'latitude', 'longitude'])
            : null;

        $locations = DB::table('tracking_koordinator_lokasi')
            ->where('koordinator_id', $data['koordinator_id'])
            ->whereDate('tanggal', $data['tanggal'])
            ->orderBy('waktu')
            ->get(['lat', 'lng', 'waktu', 'akurasi']);

        return response()->json([
            'koordinator' => $koordinator,
            'depo' => $depo,
            'total' => $locations->count(),
            'locations' => $locations,
        ]);
    }
}
