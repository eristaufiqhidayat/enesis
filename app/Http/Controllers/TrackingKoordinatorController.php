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
        ]);
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
