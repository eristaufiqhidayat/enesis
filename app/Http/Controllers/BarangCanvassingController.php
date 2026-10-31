<?php

namespace App\Http\Controllers;

use App\Support\Menu\Motoris\SumberCanvassing;
use Illuminate\Http\Request;

// Detail produk satu muatan canvassing untuk drawer di tabel barang_canvassing (lihat Menu/Show.vue).
class BarangCanvassingController extends Controller
{
    public function item(Request $request, int $canvassing)
    {
        $sumber = new SumberCanvassing;

        $baris = $sumber->query($request->user())
            ->where('c.id', $canvassing)
            ->get();

        abort_if($baris->isEmpty(), 404);

        return response()->json([
            'data' => $baris->map(fn ($row) => $sumber->ubahBaris($row))->values(),
        ]);
    }
}
