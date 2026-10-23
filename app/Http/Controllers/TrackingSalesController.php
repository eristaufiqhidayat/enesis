<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TrackingSalesController extends Controller
{
    public function index(Request $request)
    {
        $tanggal = $request->query('tanggal')
            ?? DB::table('tracking_sales_lokasi')->max('tanggal')
            ?? now()->toDateString();

        $people = DB::table('tracking_sales')->orderBy('nama')
            ->get(['sales_id as id', 'nama', 'telepon', 'depo', 'koordinator_id as group_id', 'koordinator_nama']);

        return view('tracking.history', [
            'page' => config('marketama.pages.tracking_sales_history_location'),
            'label' => 'Sales',
            'idParam' => 'sales_id',
            'people' => $people,
            'groups' => $people->whereNotNull('group_id')->sortBy('koordinator_nama')->pluck('koordinator_nama', 'group_id')->all(),
            'locationsUrl' => route('tracking-sales.locations'),
            'countsUrl' => route('tracking-sales.counts'),
            'tanggal' => substr((string) $tanggal, 0, 10),
            'counts' => $this->countsFor($tanggal),
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

    private function countsFor(string $tanggal): array
    {
        return DB::table('tracking_sales_lokasi')
            ->whereDate('tanggal', $tanggal)
            ->groupBy('sales_id')
            ->selectRaw('sales_id, count(*) as total')
            ->pluck('total', 'sales_id')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    public function locations(Request $request)
    {
        $data = $request->validate([
            'sales_id' => ['required', 'integer'],
            'tanggal' => ['required', 'date'],
        ]);

        $sales = DB::table('tracking_sales')->where('sales_id', $data['sales_id'])->first();

        $depo = $sales?->depo_lat
            ? ['nama' => $sales->depo, 'latitude' => $sales->depo_lat, 'longitude' => $sales->depo_lng]
            : null;

        $locations = DB::table('tracking_sales_lokasi')
            ->where('sales_id', $data['sales_id'])
            ->whereDate('tanggal', $data['tanggal'])
            ->orderBy('waktu')
            ->get(['lat', 'lng', 'waktu']);

        return response()->json([
            'sales' => $sales,
            'depo' => $depo,
            'total' => $locations->count(),
            'locations' => $locations,
        ]);
    }
}
