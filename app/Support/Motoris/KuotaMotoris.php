<?php

namespace App\Support\Motoris;

use App\Support\Marketama\HalamanParser;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Depo beserta kota (City Group) dan kuota sales motoris yang berlaku pada satu bulan.
// Kuota: isian admin di master_kuota_motoris (baris terbaru yang berlaku_mulai <= bulan);
// bila belum pernah diisi, kuota dari Master Depo Marketama (mk_depo.data, cadangan master_depo).
class KuotaMotoris
{
    /**
     * @param  array<int>|null  $hanyaDepo  batasi ke depo tertentu (cakupan role); null = semua
     * @return Collection<int, object{id: int, nama: string, kota: ?string, aktif: bool, kuota_marketama: ?int, kuota: ?int, sumber: string, berlaku_mulai: ?string}>
     */
    public static function untukBulan(string $bulan, ?array $hanyaDepo = null): Collection
    {
        $isian = DB::table('master_kuota_motoris')->where('berlaku_mulai', '<=', $bulan)
            ->orderBy('berlaku_mulai')->get(['depo_id', 'kuota', 'berlaku_mulai'])
            ->keyBy('depo_id'); // terakhir menang = baris terbaru yang sudah berlaku

        return self::depo($hanyaDepo)->map(function ($d) use ($isian) {
            $baris = $isian[$d->id] ?? null;
            $d->kuota = $baris ? (int) $baris->kuota : $d->kuota_marketama;
            $d->sumber = $baris ? 'enesis' : ($d->kuota_marketama !== null ? 'marketama' : 'kosong');
            $d->berlaku_mulai = $baris?->berlaku_mulai;

            return $d;
        });
    }

    /** Simpan kuota satu depo mulai bulan tertentu; mengembalikan true bila nilai berubah. */
    public static function simpan(int $depoId, string $bulan, int $kuota, ?int $userId): bool
    {
        $ada = DB::table('master_kuota_motoris')->where('depo_id', $depoId)->where('berlaku_mulai', $bulan)->first();

        if ($ada && (int) $ada->kuota === $kuota) {
            return false;
        }

        DB::table('master_kuota_motoris')->updateOrInsert(
            ['depo_id' => $depoId, 'berlaku_mulai' => $bulan],
            ['kuota' => $kuota, 'diubah_oleh' => $userId, 'updated_at' => now()] + ($ada ? [] : ['created_at' => now()]),
        );

        return true;
    }

    /** Hapus isian admin satu depo pada bulan itu (kembali ke kuota bulan sebelumnya / Marketama). */
    public static function hapus(int $depoId, string $bulan): bool
    {
        return DB::table('master_kuota_motoris')->where('depo_id', $depoId)->where('berlaku_mulai', $bulan)->delete() > 0;
    }

    /** @return Collection<int, object> depo urut kota lalu nama */
    private static function depo(?array $hanyaDepo): Collection
    {
        $lama = Schema::hasTable('master_depo')
            ? DB::table('master_depo')->get(['nama', 'kota_besar', 'kuota_sales_motoris'])->keyBy(fn ($b) => HalamanParser::kunciNama($b->nama))
            : collect();

        return DB::table('mk_depo')
            ->when($hanyaDepo !== null, fn ($q) => $q->whereIn('id', $hanyaDepo ?: [0]))
            ->get(['id', 'nama', 'aktif', 'data'])
            ->map(function ($d) use ($lama) {
                $data = json_decode((string) $d->data, true) ?: [];
                $cadangan = $lama[HalamanParser::kunciNama($d->nama)] ?? null;
                $kota = self::teks($data['kota_besar'] ?? null) ?? self::teks($cadangan?->kota_besar);
                $kuota = self::teks($data['kuota_sales_motoris'] ?? null) ?? self::teks($cadangan?->kuota_sales_motoris);

                return (object) [
                    'id' => (int) $d->id,
                    'nama' => $d->nama,
                    'kota' => $kota,
                    'aktif' => $d->aktif === null ? true : (bool) $d->aktif,
                    'kuota_marketama' => $kuota === null ? null : (int) round((float) HalamanParser::angka($kuota)),
                ];
            })
            ->sortBy([['kota', 'asc'], ['nama', 'asc']])->values();
    }

    private static function teks($nilai): ?string
    {
        $t = trim((string) $nilai);

        return $t === '' || $t === '-' ? null : $t;
    }
}
