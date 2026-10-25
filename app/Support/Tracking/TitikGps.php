<?php

namespace App\Support\Tracking;

use App\Support\Mobile\Pengaturan;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

// Titik GPS dari aplikasi HP (trx_tracking) untuk halaman riwayat lokasi sales / koordinator.
// recorded_at disimpan UTC; halaman memakai tanggal & jam WIB seperti data lama Marketama.
class TitikGps
{
    /** Titik GPS satu karyawan pada satu tanggal (WIB), bentuknya sama dengan data lama. */
    public static function untuk(int $karyawanId, string $tanggal): Collection
    {
        [$dari, $sampai] = self::rentangUtc($tanggal);
        $zona = Pengaturan::zona();

        return DB::table('trx_tracking')->where('karyawan_id', $karyawanId)->whereBetween('recorded_at', [$dari, $sampai])
            ->get(['lat', 'lng', 'recorded_at', 'akurasi_m'])
            ->map(fn ($t) => (object) [
                'lat' => $t->lat,
                'lng' => $t->lng,
                'waktu' => CarbonImmutable::parse($t->recorded_at, 'UTC')->setTimezone($zona)->format('Y-m-d H:i:s'),
                'akurasi' => $t->akurasi_m,
            ]);
    }

    /**
     * Tambahkan jumlah titik HP per karyawan (kategori sales / koordinator) ke hitungan data lama.
     *
     * @param  array<int, int>  $hasil
     * @return array<int, int>
     */
    public static function tambahJumlah(array $hasil, string $kategori, string $tanggal): array
    {
        [$dari, $sampai] = self::rentangUtc($tanggal);
        $hp = DB::table('trx_tracking as t')->join('mk_karyawan as k', 'k.id', '=', 't.karyawan_id')
            ->where('k.kategori', $kategori)->whereBetween('t.recorded_at', [$dari, $sampai])
            ->groupBy('t.karyawan_id')->selectRaw('t.karyawan_id, count(*) as total')
            ->pluck('total', 'karyawan_id');

        foreach ($hp as $id => $total) {
            $hasil[$id] = ($hasil[$id] ?? 0) + (int) $total;
        }

        return $hasil;
    }

    /** Tanggal terbaru yang punya data (tabel lama atau HP); hari ini bila belum ada. */
    public static function tanggalTerakhir(string $tabelLama, string $kategori): string
    {
        $lama = DB::table($tabelLama)->max('tanggal');
        $hp = DB::table('trx_tracking as t')->join('mk_karyawan as k', 'k.id', '=', 't.karyawan_id')
            ->where('k.kategori', $kategori)->max('t.recorded_at');
        $hp = $hp ? CarbonImmutable::parse($hp, 'UTC')->setTimezone(Pengaturan::zona())->toDateString() : null;

        return collect([$lama ? substr((string) $lama, 0, 10) : null, $hp])->filter()->max()
            ?? CarbonImmutable::now(Pengaturan::zona())->toDateString();
    }

    /** Lokasi depo dari master (mk_depo) untuk karyawan yang tidak punya lokasi depo di data lama. */
    public static function depoMaster(int $karyawanId): ?object
    {
        $depoId = DB::table('mk_karyawan')->where('id', $karyawanId)->value('depo_id');
        $depo = $depoId ? Pengaturan::depo((int) $depoId) : null;

        return $depo && $depo['lat'] !== null ? (object) ['nama' => $depo['nama'], 'latitude' => $depo['lat'], 'longitude' => $depo['lng']] : null;
    }

    /** @return array{0: string, 1: string} awal & akhir tanggal WIB dalam UTC (kolom recorded_at) */
    private static function rentangUtc(string $tanggal): array
    {
        $awal = CarbonImmutable::parse(substr($tanggal, 0, 10), Pengaturan::zona())->startOfDay();

        return [$awal->utc()->format('Y-m-d H:i:s'), $awal->endOfDay()->utc()->format('Y-m-d H:i:s')];
    }
}
