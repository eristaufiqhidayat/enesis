<?php

namespace App\Http\Controllers;

use App\Support\Akses\Akses;
use App\Support\Tracking\PengaturanDeteksiKunjungan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

// Ambang deteksi "kunjungan berturutan berjarak dekat" (spec 054): dua angka (jarak meter, minimum kunjungan
// berturutan) dipakai AnomaliJarakKunjungan. Disimpan di `pengaturan_admin` (bukan hanya di config), supaya admin
// bisa menyesuaikannya tanpa deploy ulang — penting karena ambang yang pas untuk data lama (hasil impor Marketama)
// berbeda dari data HP langsung nanti.
class PengaturanDeteksiKunjunganController extends Controller
{
    private const MENU = 'deteksi_kunjungan';

    public function index(): Response
    {
        return Inertia::render('Pengaturan/DeteksiKunjungan', [
            'pengaturan' => PengaturanDeteksiKunjungan::ambil(),
            'bisaUbah' => Akses::bisa(request()->user(), self::MENU, 'ubah'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'jarak_dekat_meter' => ['required', 'integer', 'min:1'],
            'min_kunjungan_berturutan' => ['required', 'integer', 'min:2'],
        ]);

        $sebelum = PengaturanDeteksiKunjungan::ambil();
        $user = $request->user();

        PengaturanDeteksiKunjungan::simpan($data['jarak_dekat_meter'], $data['min_kunjungan_berturutan'], $user);

        Akses::catat($user, 'pengaturan_deteksi_kunjungan', 0, 'ubah', sprintf(
            'Ambang deteksi kunjungan: jarak %sm → %sm, minimum %s → %s kunjungan',
            $sebelum['jarak_dekat_meter'], $data['jarak_dekat_meter'], $sebelum['min_kunjungan_berturutan'], $data['min_kunjungan_berturutan']
        ));

        return back()->with('success', 'Ambang deteksi kunjungan disimpan.');
    }
}
