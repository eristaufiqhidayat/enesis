<?php

namespace App\Http\Controllers;

use App\Support\Akses\Akses;
use App\Support\Outlet\VerifikasiOutletBaru;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// Tombol Setujui / Tolak di menu All Outlet, tab "Outlet Baru dari HP".
class OutletBaruController extends Controller
{
    public function __construct(private VerifikasiOutletBaru $verifikasi) {}

    public function setujui(Request $request, int $id): RedirectResponse
    {
        $this->cekCakupan($request, $id);

        try {
            $outletId = $this->verifikasi->setujui($id, $request->user());
        } catch (RuntimeException $e) {
            return back()->with('galat', $e->getMessage());
        }

        return back()->with('sukses', "Outlet disetujui dan masuk master outlet (ID {$outletId}).");
    }

    public function tolak(Request $request, int $id): RedirectResponse
    {
        $this->cekCakupan($request, $id);
        $data = $request->validate(['alasan' => ['required', 'string', 'max:255']], ['alasan.required' => 'Alasan penolakan wajib diisi.']);

        try {
            $this->verifikasi->tolak($id, trim($data['alasan']), $request->user());
        } catch (RuntimeException $e) {
            return back()->with('galat', $e->getMessage());
        }

        return back()->with('sukses', 'Outlet baru ditolak.');
    }

    /** Hanya outlet dari sales yang masuk cakupan role (depo / downline / diri) yang boleh diverifikasi. */
    private function cekCakupan(Request $request, int $id): void
    {
        $karyawanId = DB::table('trx_outlet_baru')->where('id', $id)->value('karyawan_id') ?? abort(404);
        $boleh = Akses::batasiKaryawan(DB::table('mk_karyawan as k')->where('k.id', $karyawanId), $request->user())->exists();

        abort_unless($boleh, 403, 'Outlet ini di luar cakupan akun Anda.');
    }
}
