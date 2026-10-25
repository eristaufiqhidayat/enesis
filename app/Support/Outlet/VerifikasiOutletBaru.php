<?php

namespace App\Support\Outlet;

use App\Models\User;
use App\Support\Akses\Akses;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Setujui / tolak outlet baru yang didaftarkan sales lewat HP (trx_outlet_baru).
 * Outlet yang disetujui dibuat di master mk_outlet, lalu kunjungan & penjualannya disambung ke ID master.
 */
class VerifikasiOutletBaru
{
    /**
     * ID mk_outlet untuk outlet buatan Enesis dimulai dari sini, jauh di atas ID Marketama (±554 ribu),
     * supaya tidak bentrok dengan `marketama:tarik` yang menyimpan berdasarkan ID asli.
     */
    public const ID_AWAL = 100_000_000;

    /** @return int ID mk_outlet yang dibuat */
    public function setujui(int $id, User $admin): int
    {
        return DB::transaction(function () use ($id, $admin) {
            $baru = $this->ambilMenunggu($id);
            $outletId = max(self::ID_AWAL, (int) DB::table('mk_outlet')->where('id', '>=', self::ID_AWAL)->max('id') + 1);
            $area = $baru->area_id ? DB::table('mk_area')->where('id', $baru->area_id)->value('nama') : null;

            DB::table('mk_outlet')->insert([
                'id' => $outletId,
                'kode' => 'NOO'.str_pad((string) $baru->id, 6, '0', STR_PAD_LEFT),
                'nama' => $baru->nama,
                'alamat' => $baru->alamat,
                'area_id' => $baru->area_id,
                'area' => $area,
                'latitude' => $baru->lat,
                'longitude' => $baru->lng,
                'verifikasi' => 'Terverifikasi',
                'dibuat_oleh' => DB::table('mk_karyawan')->where('id', $baru->karyawan_id)->value('nama'),
                'dibuat_pada' => (string) ($baru->recorded_at ?? $baru->received_at),
            ]);

            DB::table('trx_outlet_baru')->where('id', $id)->update([
                'status' => 'disetujui',
                'outlet_id' => $outletId,
                'diverifikasi_oleh' => $admin->id,
                'diverifikasi_pada' => now(),
            ]);

            // Kunjungan ke outlet ini sebelum disetujui tercatat dengan outlet_baru_id saja.
            $kunjungan = DB::table('trx_kunjungan')->where('outlet_baru_id', $id)->whereNull('outlet_id');
            $kunjunganIds = (clone $kunjungan)->pluck('id');
            $kunjungan->update(['outlet_id' => $outletId]);

            if ($kunjunganIds->isNotEmpty()) {
                DB::table('trx_penjualan')->whereIn('kunjungan_id', $kunjunganIds)->whereNull('outlet_id')
                    ->update(['outlet_id' => $outletId]);
            }

            Akses::catat($admin, 'outlet_baru', $id, 'setujui', "mk_outlet {$outletId}: {$baru->nama}");

            return $outletId;
        });
    }

    public function tolak(int $id, string $alasan, User $admin): void
    {
        DB::transaction(function () use ($id, $alasan, $admin) {
            $baru = $this->ambilMenunggu($id);

            DB::table('trx_outlet_baru')->where('id', $id)->update([
                'status' => 'ditolak',
                'alasan_tolak' => mb_substr($alasan, 0, 255),
                'diverifikasi_oleh' => $admin->id,
                'diverifikasi_pada' => now(),
            ]);

            Akses::catat($admin, 'outlet_baru', $id, 'tolak', "{$baru->nama}: {$alasan}");
        });
    }

    private function ambilMenunggu(int $id): object
    {
        $baru = DB::table('trx_outlet_baru')->where('id', $id)->lockForUpdate()->first();

        if (! $baru) {
            throw new RuntimeException('Outlet baru tidak ditemukan.');
        }

        if ($baru->status !== 'menunggu') {
            throw new RuntimeException("Outlet {$baru->nama} sudah {$baru->status}.");
        }

        return $baru;
    }
}
