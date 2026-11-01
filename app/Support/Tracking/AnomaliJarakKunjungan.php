<?php

namespace App\Support\Tracking;

use Illuminate\Support\Collection;

// Indikasi kunjungan outlet berurutan yang berjarak mustahil dekat (mis. beberapa outlet "dikunjungi" tanpa
// motoris benar-benar berpindah lokasi) dari daftar kunjungan SATU motoris pada SATU tanggal (KunjunganHarian,
// sudah terurut jam). Berbeda dari AnomaliGps (titik GPS mentah), ini memakai `perjalanan.jarak_m` yang sudah
// dihitung antar kunjungan (spec 045); kunjungan tanpa koordinat atau tanpa perjalanan (checkout kosong/tumpang
// tindih) memutus runtun karena tidak bisa dinilai.
//
// Satu hari bisa punya LEBIH DARI SATU runtun yang memenuhi ambang (mis. motoris berhenti dekat satu klaster
// outlet di pagi hari, lalu klaster lain di siang hari) — semuanya dikembalikan, bukan hanya yang terpanjang,
// supaya runtun yang lebih pendek tidak tertutup/hilang begitu saja oleh runtun lain yang kebetulan lebih panjang
// pada hari yang sama.
class AnomaliJarakKunjungan
{
    /**
     * @param  Collection<int, object>  $kunjungan  hasil KunjunganHarian::untuk, terurut jam checkin
     * @return array<int, array{jumlah: int, kunjungan_id: array<int, int>, outlet_id: array<int, int>, dari_jam: string, sampai_jam: string, saran: array{kategori: string, catatan: string}}> urut dari yang terpanjang; kosong bila tidak ada runtun yang memenuhi ambang
     */
    public static function analisis(Collection $kunjungan): array
    {
        $ambang = PengaturanDeteksiKunjungan::ambil();
        $kunjungan = $kunjungan->values();

        if ($kunjungan->isEmpty()) {
            return [];
        }

        $grup = [];
        $sekarang = [$kunjungan[0]];

        for ($i = 1; $i < $kunjungan->count(); $i++) {
            $k = $kunjungan[$i];
            $jarak = $k->perjalanan['jarak_m'] ?? null;

            if ($jarak !== null && $jarak < $ambang['jarak_dekat_meter']) {
                $sekarang[] = $k;
            } else {
                $grup[] = $sekarang;
                $sekarang = [$k];
            }
        }
        $grup[] = $sekarang;

        return collect($grup)
            ->filter(fn ($g) => count($g) >= $ambang['min_kunjungan_berturutan'])
            ->sortByDesc(fn ($g) => count($g))
            ->map(fn ($g) => self::bentuk($g, $ambang))
            ->values()->all();
    }

    /** @param  array<int, object>  $runtun */
    private static function bentuk(array $runtun, array $ambang): array
    {
        $jumlah = count($runtun);
        $dariJam = $runtun[0]->jam;
        $sampaiJam = $runtun[$jumlah - 1]->jam;

        return [
            'jumlah' => $jumlah,
            'kunjungan_id' => array_map(fn ($k) => $k->id, $runtun),
            // Outlet unik (tanpa null, kunjungan outlet baru belum punya outlet_id) untuk saran Tandai outlet.
            'outlet_id' => array_values(array_unique(array_filter(array_map(fn ($k) => $k->outlet_id, $runtun)))),
            'dari_jam' => $dariJam,
            'sampai_jam' => $sampaiJam,
            'saran' => [
                'kategori' => 'pola_kunjungan_tidak_wajar',
                'catatan' => "{$jumlah} kunjungan outlet berturutan berjarak kurang dari {$ambang['jarak_dekat_meter']} m satu sama lain, dari jam {$dariJam} sampai {$sampaiJam}.",
            ],
        ];
    }
}
