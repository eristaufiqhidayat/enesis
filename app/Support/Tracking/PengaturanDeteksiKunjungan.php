<?php

namespace App\Support\Tracking;

use App\Models\User;
use Illuminate\Support\Facades\DB;

// Ambang indikasi kunjungan berturutan berjarak dekat (spec 054), disimpan di `pengaturan_admin` (kunci → nilai,
// pola yang sama dengan RekapPencapaianSku::target) supaya admin bisa mengubahnya dari halaman Pengaturan Deteksi
// Kunjungan tanpa deploy ulang. Dipakai oleh AnomaliJarakKunjungan dan PengaturanDeteksiKunjunganController.
class PengaturanDeteksiKunjungan
{
    private const KUNCI_JARAK = 'deteksi_kunjungan.jarak_dekat_meter';

    private const KUNCI_MIN = 'deteksi_kunjungan.min_kunjungan_berturutan';

    /** Nilai aktif saat ini; fallback ke config bila kunci belum pernah disimpan. */
    public static function ambil(): array
    {
        $nilai = DB::table('pengaturan_admin')->whereIn('kunci', [self::KUNCI_JARAK, self::KUNCI_MIN])->pluck('nilai', 'kunci');

        return [
            'jarak_dekat_meter' => isset($nilai[self::KUNCI_JARAK]) ? (int) $nilai[self::KUNCI_JARAK] : (int) config('marketama.deteksi_jarak_kunjungan.jarak_dekat_meter', 5),
            'min_kunjungan_berturutan' => isset($nilai[self::KUNCI_MIN]) ? (int) $nilai[self::KUNCI_MIN] : (int) config('marketama.deteksi_jarak_kunjungan.min_kunjungan_berturutan', 4),
        ];
    }

    public static function simpan(int $jarakDekatMeter, int $minKunjunganBerturutan, ?User $user): void
    {
        foreach ([self::KUNCI_JARAK => $jarakDekatMeter, self::KUNCI_MIN => $minKunjunganBerturutan] as $kunci => $nilai) {
            DB::table('pengaturan_admin')->updateOrInsert(
                ['kunci' => $kunci],
                ['nilai' => (string) $nilai, 'diubah_oleh' => $user?->id, 'updated_at' => now(), 'created_at' => now()],
            );
        }
    }
}
