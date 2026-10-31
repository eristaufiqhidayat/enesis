<?php

namespace App\Support\Tracking;

use App\Support\Mobile\Lokasi;
use Illuminate\Support\Collection;

// Indikasi GPS tidak wajar (mis. fake GPS) dari titik GPS satu motoris pada SATU tanggal. Sengaja tidak menggabungkan
// tanggal lain: motoris tidak bekerja setiap hari, jadi pola hanya dinilai dalam hari yang sedang dilihat.
//
// - Lompatan mustahil: dua titik berurutan berjarak >= jarak_km dengan kecepatan implisit > kecepatan_kmh.
// - Kembali ke asal: titik sesudah lompatan kembali ke dekat titik sebelumnya (A -> B -> A, < kembali_km).
// - Titik menyimpang tunggal: titik yang >= menyimpang_km dari titik sebelum DAN sesudahnya, padahal kedua tetangganya saling
//   berdekatan (< tetangga_km): posisi melompat jauh lalu langsung kembali. Tidak bergantung pada kecepatan atau jeda waktu,
//   jadi tetap tertangkap bila titik sebelumnya datang setelah jeda panjang.
// - "terdeteksi": HP menandai mock_location pada titik GPS atau kunjungan (bukti langsung dari perangkat).
// - "indikasi": tanpa penanda dari HP (termasuk semua data lama Marketama), tetapi lompatannya berulang dan banyak yang
//   kembali ke asal, ATAU ada cukup banyak titik menyimpang tunggal. Hanya indikasi dari pola data, bukan bukti.
//   Ambang di config marketama.anomali_gps.
class AnomaliGps
{
    /** Segmen yang dikirim untuk digambar di peta (yang terjauh lebih dulu). */
    public const MAKS_SEGMEN = 100;

    /**
     * @param  Collection<int, object>  $titik  objek dengan lat, lng, waktu ("Y-m-d H:i:s", zona aplikasi) dan opsional mock
     * @param  int  $kunjunganMock  jumlah kunjungan hari itu yang ditandai lokasi palsu oleh HP
     * @return array<string, mixed>|null null bila di bawah ambang
     */
    public static function analisis(Collection $titik, int $kunjunganMock = 0): ?array
    {
        $ambang = config('marketama.anomali_gps');
        $urut = $titik->sortBy('waktu')->values();
        $mock = $urut->filter(fn ($t) => ! empty($t->mock))->count();
        $lompatan = [];

        for ($i = 1; $i < $urut->count(); $i++) {
            [$a, $b] = [$urut[$i - 1], $urut[$i]];
            $detik = strtotime((string) $b->waktu) - strtotime((string) $a->waktu);

            // Selisih waktu nol atau negatif tidak bisa dihitung kecepatannya.
            if ($detik <= 0) {
                continue;
            }

            $km = Lokasi::jarak((float) $a->lat, (float) $a->lng, (float) $b->lat, (float) $b->lng) / 1000;
            $kmh = $km / ($detik / 3600);

            if ($km < $ambang['jarak_km'] || $kmh <= $ambang['kecepatan_kmh']) {
                continue;
            }

            $sesudah = $urut[$i + 1] ?? null;
            $lompatan[] = [
                'dari' => ['lat' => (float) $a->lat, 'lng' => (float) $a->lng, 'waktu' => (string) $a->waktu],
                'ke' => ['lat' => (float) $b->lat, 'lng' => (float) $b->lng, 'waktu' => (string) $b->waktu],
                'km' => round($km, 1),
                'menit' => round($detik / 60, 1),
                'kmh' => (int) round($kmh),
                'kembali' => $sesudah !== null
                    && Lokasi::jarak((float) $a->lat, (float) $a->lng, (float) $sesudah->lat, (float) $sesudah->lng) / 1000 < $ambang['kembali_km'],
            ];
        }

        $menyimpang = self::menyimpang($urut, $ambang);
        $kembali = count(array_filter($lompatan, fn ($l) => $l['kembali']));
        $terdeteksi = $mock > 0 || $kunjunganMock > 0;
        $indikasi = (count($lompatan) >= $ambang['min_lompatan'] && $kembali >= $ambang['min_kembali'])
            || count($menyimpang) >= $ambang['min_menyimpang'];

        if (! $terdeteksi && ! $indikasi) {
            return null;
        }

        $terjauh = collect($lompatan)->sortByDesc('km')->values();
        $menyimpangTerjauh = collect($menyimpang)->sortByDesc('km')->values();

        return [
            'tingkat' => $terdeteksi ? 'terdeteksi' : 'indikasi',
            'lompatan' => count($lompatan),
            'kembali_ke_asal' => $kembali,
            'titik_mock' => $mock,
            'kunjungan_mock' => $kunjunganMock,
            'terjauh' => $terjauh->isEmpty() ? null : [
                'km' => $terjauh[0]['km'],
                'menit' => $terjauh[0]['menit'],
                'kmh' => $terjauh[0]['kmh'],
                'jam' => substr($terjauh[0]['dari']['waktu'], 11, 5),
            ],
            // Digambar berurutan menurut waktu; bila lebih dari batas, yang terjauh dipertahankan.
            'segmen' => $terjauh->take(self::MAKS_SEGMEN)->sortBy(fn ($l) => $l['dari']['waktu'])->values()->all(),
            'menyimpang' => count($menyimpang),
            'menyimpang_terjauh' => $menyimpangTerjauh->isEmpty() ? null : [
                'km' => $menyimpangTerjauh[0]['km'],
                'jam' => substr($menyimpangTerjauh[0]['waktu'], 11, 5),
            ],
            'titik_menyimpang' => $menyimpangTerjauh->take(self::MAKS_SEGMEN)->sortBy('waktu')->values()->all(),
        ];
    }

    /**
     * Titik menyimpang tunggal pada titik yang sudah berurutan menurut waktu. `km` = jarak ke tetangga yang lebih dekat.
     *
     * @param  Collection<int, object>  $urut
     * @param  array<string, float|int>  $ambang
     * @return array<int, array{lat: float, lng: float, waktu: string, km: float}>
     */
    private static function menyimpang(Collection $urut, array $ambang): array
    {
        $jarak = fn (object $a, object $b) => Lokasi::jarak((float) $a->lat, (float) $a->lng, (float) $b->lat, (float) $b->lng) / 1000;
        $hasil = [];

        for ($i = 1; $i < $urut->count() - 1; $i++) {
            $sebelum = $jarak($urut[$i - 1], $urut[$i]);
            $sesudah = $jarak($urut[$i], $urut[$i + 1]);

            if ($sebelum >= $ambang['menyimpang_km'] && $sesudah >= $ambang['menyimpang_km'] && $jarak($urut[$i - 1], $urut[$i + 1]) < $ambang['tetangga_km']) {
                $hasil[] = [
                    'lat' => (float) $urut[$i]->lat,
                    'lng' => (float) $urut[$i]->lng,
                    'waktu' => (string) $urut[$i]->waktu,
                    'km' => round(min($sebelum, $sesudah), 1),
                ];
            }
        }

        return $hasil;
    }
}
