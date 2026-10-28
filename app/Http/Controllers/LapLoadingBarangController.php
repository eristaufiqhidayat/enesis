<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// Lap. Loading Barang: riwayat transaksi satu distributor untuk drawer di tabel (lihat Menu/Show.vue).
class LapLoadingBarangController extends Controller
{
    // Batas baris riwayat yang dikirim; total tetap menghitung seluruh transaksi distributor itu.
    private const BATAS_RIWAYAT = 100;

    // Dicocokkan lewat distributor_id (hasil enesis:sambung-transaksi) bila ada, supaya distributor bernama
    // kembar di depo lain tidak tercampur. Tanpa id, pakai nama teks.
    public function riwayatDistributor(Request $request)
    {
        $data = $request->validate([
            'distributor_id' => ['nullable', 'integer'],
            'distributor_name' => ['required_without:distributor_id', 'nullable', 'string', 'max:255'],
        ]);

        $query = DB::table('lap_loading_barang');

        if (! empty($data['distributor_id'])) {
            $query->where('distributor_id', $data['distributor_id']);
        } else {
            $query->where('distributor_name', $data['distributor_name']);
        }

        return response()->json([
            'total' => (clone $query)->count(),
            'data' => $query
                ->orderByDesc('datetime_trans')
                ->limit(self::BATAS_RIWAYAT)
                ->get(['trans_code', 'datetime_trans', 'koordinator_name', 'warehouse_name', 'total_qty', 'grand_total']),
        ]);
    }
}
