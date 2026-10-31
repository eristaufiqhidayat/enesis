<?php

namespace Tests\Feature;

use App\Support\Tracking\AnomaliGps;
use Illuminate\Support\Collection;
use Tests\TestCase;

// Indikasi GPS tidak wajar per motoris per tanggal: lompatan mustahil, pola kembali ke asal, dan penanda mock dari HP.
// Murni perhitungan (tanpa database). 0,05 derajat lintang = sekitar 5,56 km.
class AnomaliGpsTest extends TestCase
{
    private const LANGKAH = 0.05;

    protected function setUp(): void
    {
        parent::setUp();

        // Tes aturan lompatan diisolasi dari aturan titik menyimpang tunggal; tes aturan itu mengaktifkannya sendiri.
        config(['marketama.anomali_gps.min_menyimpang' => PHP_INT_MAX]);
    }

    /**
     * Titik berurutan satu menit sekali pada bujur tetap; $lintang dalam kelipatan LANGKAH dari -6,60.
     *
     * @param  array<int, int|float>  $lintang
     */
    private function rute(array $lintang, int $selangDetik = 60, array $mock = []): Collection
    {
        return collect($lintang)->map(fn ($k, $i) => (object) [
            'lat' => -6.60 + $k * self::LANGKAH,
            'lng' => 106.80,
            'waktu' => date('Y-m-d H:i:s', strtotime('2026-10-22 10:00:00') + $i * $selangDetik),
            'mock' => in_array($i, $mock, true),
        ]);
    }

    public function test_rute_normal_tanpa_peringatan(): void
    {
        // Bergerak 0,5 km tiap 3 menit.
        $titik = collect(range(0, 30))->map(fn ($i) => (object) [
            'lat' => -6.60 + $i * 0.0045, 'lng' => 106.80, 'waktu' => date('Y-m-d H:i:s', strtotime('2026-10-22 10:00:00') + $i * 180),
        ]);

        $this->assertNull(AnomaliGps::analisis($titik));
        $this->assertNull(AnomaliGps::analisis(collect()));
    }

    public function test_satu_atau_dua_lompatan_di_bawah_ambang_tidak_memicu_peringatan(): void
    {
        $this->assertNull(AnomaliGps::analisis($this->rute([0, 1, 0, 0, 0])));
        $this->assertNull(AnomaliGps::analisis($this->rute([0, 1, 0, 1, 0, 0, 0])));
    }

    public function test_lompatan_berulang_dan_kembali_ke_asal_menjadi_indikasi(): void
    {
        // 0,1,0,1,0,2: 5 lompatan; 3 kembali ke asal (tepat di ambang).
        $hasil = AnomaliGps::analisis($this->rute([0, 1, 0, 1, 0, 2]));

        $this->assertSame('indikasi', $hasil['tingkat']);
        $this->assertSame(5, $hasil['lompatan']);
        $this->assertSame(3, $hasil['kembali_ke_asal']);
        $this->assertSame(0, $hasil['titik_mock']);
        $this->assertSame(0, $hasil['kunjungan_mock']);
    }

    public function test_banyak_lompatan_tetapi_kurang_dari_tiga_yang_kembali_tidak_cukup(): void
    {
        // 0,1,0,1,2,3: 5 lompatan, hanya 2 kembali ke asal.
        $this->assertNull(AnomaliGps::analisis($this->rute([0, 1, 0, 1, 2, 3])));
        // 0,1,2,3,4,5: 5 lompatan satu arah, 0 kembali (perjalanan jauh dengan titik jarang, bukan bolak-balik).
        $this->assertNull(AnomaliGps::analisis($this->rute([0, 1, 2, 3, 4, 5])));
    }

    public function test_empat_lompatan_belum_cukup_walau_semuanya_bolak_balik(): void
    {
        $this->assertNull(AnomaliGps::analisis($this->rute([0, 1, 0, 1, 0])));
    }

    public function test_ambang_jarak_dan_kecepatan(): void
    {
        $enam = [0, 1, 0, 1, 0, 1, 0];

        // Kecepatan 5,56 km / 4 menit = 83 km/jam: wajar untuk motor, bukan lompatan.
        $this->assertNull(AnomaliGps::analisis($this->rute($enam, selangDetik: 240)));
        // 5,56 km dalam 2 menit = 167 km/jam: lompatan.
        $this->assertNotNull(AnomaliGps::analisis($this->rute($enam, selangDetik: 120)));
        // Jarak 2,5 km (< 3 km) pada kecepatan 900 km/jam: terlalu dekat untuk dianggap lompatan (derau GPS biasa).
        $titik = collect(range(0, 6))->map(fn ($i) => (object) [
            'lat' => -6.60 + ($i % 2) * 0.0225, 'lng' => 106.80, 'waktu' => date('Y-m-d H:i:s', strtotime('2026-10-22 10:00:00') + $i * 10),
        ]);
        $this->assertNull(AnomaliGps::analisis($titik));
    }

    public function test_titik_tidak_berurutan_diurutkan_menurut_waktu_dulu(): void
    {
        $titik = $this->rute([0, 1, 0, 1, 0, 2])->reverse()->values();

        $this->assertSame(5, AnomaliGps::analisis($titik)['lompatan']);
    }

    public function test_selisih_waktu_nol_diabaikan(): void
    {
        $titik = collect(range(0, 7))->map(fn ($i) => (object) [
            'lat' => -6.60 + ($i % 2) * 0.1, 'lng' => 106.80, 'waktu' => '2026-10-22 10:00:00',
        ]);

        $this->assertNull(AnomaliGps::analisis($titik));
    }

    public function test_penanda_mock_dari_hp_langsung_terdeteksi_tanpa_butuh_lompatan(): void
    {
        $hasil = AnomaliGps::analisis($this->rute([0, 0, 0, 0], mock: [2]));

        $this->assertSame('terdeteksi', $hasil['tingkat']);
        $this->assertSame(1, $hasil['titik_mock']);
        $this->assertSame(0, $hasil['lompatan']);
        $this->assertNull($hasil['terjauh']);
        $this->assertSame([], $hasil['segmen']);
    }

    public function test_kunjungan_lokasi_palsu_dari_hp_terdeteksi(): void
    {
        $hasil = AnomaliGps::analisis($this->rute([0, 0, 0]), kunjunganMock: 2);

        $this->assertSame('terdeteksi', $hasil['tingkat']);
        $this->assertSame(2, $hasil['kunjungan_mock']);
    }

    public function test_terdeteksi_mengalahkan_indikasi_dan_tetap_memuat_lompatan(): void
    {
        $hasil = AnomaliGps::analisis($this->rute([0, 1, 0, 1, 0, 2], mock: [0]));

        $this->assertSame('terdeteksi', $hasil['tingkat']);
        $this->assertSame(5, $hasil['lompatan']);
    }

    public function test_terjauh_dan_segmen_untuk_digambar(): void
    {
        $hasil = AnomaliGps::analisis($this->rute([0, 1, 0, 1, 0, 2]));

        // Lompatan terjauh = 0 -> 2 (11,1 km) dalam 1 menit.
        $this->assertEqualsWithDelta(11.1, $hasil['terjauh']['km'], 0.1);
        $this->assertSame(1.0, $hasil['terjauh']['menit']);
        $this->assertEqualsWithDelta(667, $hasil['terjauh']['kmh'], 5);
        $this->assertSame('10:04', $hasil['terjauh']['jam']);

        $this->assertCount(5, $hasil['segmen']);
        $this->assertSame(['2026-10-22 10:00:00', '2026-10-22 10:01:00', '2026-10-22 10:02:00', '2026-10-22 10:03:00', '2026-10-22 10:04:00'], array_column(array_column($hasil['segmen'], 'dari'), 'waktu'));
        $this->assertSame([true, true, true, false, false], array_column($hasil['segmen'], 'kembali'));
        $this->assertEqualsWithDelta(-6.60, $hasil['segmen'][0]['dari']['lat'], 0.0001);
        $this->assertEqualsWithDelta(-6.55, $hasil['segmen'][0]['ke']['lat'], 0.0001);
    }

    public function test_segmen_dibatasi_seratus_dengan_jumlah_lompatan_tetap_lengkap(): void
    {
        $hasil = AnomaliGps::analisis($this->rute(array_map(fn ($i) => $i % 2, range(0, 120))));

        $this->assertSame(120, $hasil['lompatan']);
        $this->assertCount(AnomaliGps::MAKS_SEGMEN, $hasil['segmen']);
        $waktu = array_column(array_column($hasil['segmen'], 'dari'), 'waktu');
        $urut = $waktu;
        sort($urut);
        $this->assertSame($urut, $waktu, 'Segmen dikembalikan berurutan menurut waktu.');
    }

    public function test_ambang_dapat_diubah_lewat_config(): void
    {
        $this->assertNull(AnomaliGps::analisis($this->rute([0, 1, 0, 1])));

        config(['marketama.anomali_gps.min_lompatan' => 3, 'marketama.anomali_gps.min_kembali' => 2]);

        $this->assertSame('indikasi', AnomaliGps::analisis($this->rute([0, 1, 0, 1]))['tingkat']);
    }

    // ---------------------------------------------------------------- Aturan titik menyimpang tunggal

    private function menyimpangAktif(int $min = 3): void
    {
        config(['marketama.anomali_gps.min_menyimpang' => $min]);
    }

    /** Titik berjeda 30 menit: 5,6 km per 30 menit tidak pernah melewati 120 km/jam, jadi aturan lompatan diam. */
    private function pelan(array $lintang): Collection
    {
        return $this->rute($lintang, selangDetik: 1800);
    }

    public function test_tiga_titik_menyimpang_menjadi_indikasi_walau_tanpa_lompatan_mustahil(): void
    {
        $this->menyimpangAktif();

        $hasil = AnomaliGps::analisis($this->pelan([0, 0, 1, 0, 0, 2, 0, 0, 3, 0, 0]));

        $this->assertSame('indikasi', $hasil['tingkat']);
        $this->assertSame(0, $hasil['lompatan']);
        $this->assertSame(3, $hasil['menyimpang']);
        // Titik menyimpang terjauh: 3 langkah (16,7 km), indeks 8 -> 10:00 + 8 x 30 menit = 14:00.
        $this->assertEqualsWithDelta(16.7, $hasil['menyimpang_terjauh']['km'], 0.1);
        $this->assertSame('14:00', $hasil['menyimpang_terjauh']['jam']);
    }

    public function test_titik_menyimpang_memuat_posisi_waktu_dan_jarak_urut_waktu(): void
    {
        $this->menyimpangAktif();

        $titik = AnomaliGps::analisis($this->pelan([0, 0, 1, 0, 0, 2, 0, 0, 3, 0, 0]))['titik_menyimpang'];

        $this->assertSame(['2026-10-22 11:00:00', '2026-10-22 12:30:00', '2026-10-22 14:00:00'], array_column($titik, 'waktu'));
        $this->assertEqualsWithDelta([5.6, 11.1, 16.7], array_column($titik, 'km'), 0.1);
        $this->assertEqualsWithDelta(-6.55, $titik[0]['lat'], 0.0001);
        $this->assertEqualsWithDelta(106.80, $titik[0]['lng'], 0.0001);
    }

    public function test_dua_titik_menyimpang_belum_cukup_tetapi_ambang_dapat_diubah(): void
    {
        $this->menyimpangAktif();
        $dua = $this->pelan([0, 0, 1, 0, 0, 1, 0, 0]);

        $this->assertNull(AnomaliGps::analisis($dua));

        $this->menyimpangAktif(2);
        $this->assertSame('indikasi', AnomaliGps::analisis($dua)['tingkat']);
    }

    public function test_dua_titik_berurutan_yang_sama_sama_bergeser_bukan_titik_menyimpang_tunggal(): void
    {
        $this->menyimpangAktif();

        $this->assertNull(AnomaliGps::analisis($this->pelan([0, 0, 1, 1, 0, 0, 1, 1, 0, 0, 1, 1, 0, 0])));
    }

    public function test_titik_di_jalur_lurus_yang_tetangganya_berjauhan_bukan_titik_menyimpang(): void
    {
        $this->menyimpangAktif();

        $this->assertNull(AnomaliGps::analisis($this->pelan([0, 1, 2, 3, 4, 5, 6])));
    }

    public function test_penyimpangan_di_bawah_tiga_kilometer_diabaikan_sebagai_derau_gps(): void
    {
        $this->menyimpangAktif();

        // 0,45 langkah = 0,0225 derajat = sekitar 2,5 km.
        $this->assertNull(AnomaliGps::analisis($this->pelan([0, 0, 0.45, 0, 0, 0.45, 0, 0, 0.45, 0, 0])));
    }

    public function test_kasus_nyata_jeda_panjang_sebelum_titik_pertama_dan_kecepatan_di_bawah_batas_tetap_terbaca(): void
    {
        $this->menyimpangAktif();
        // Pola Dhony Rizky Prasetya 22 Okt: titik B di 14:09 datang setelah jeda 158 menit (kaki masuk 2 km/jam), kaki keluar
        // 5,6 km / 3 menit = 111 km/jam (< 120), sehingga aturan lompatan tidak menghitung apa pun. Lalu dua titik B lagi.
        $titik = collect([
            ['11:27:00', 0], ['11:30:00', 0], ['14:09:00', 1], ['14:12:00', 0], ['14:15:00', 0],
            ['14:30:00', 0], ['14:33:00', 1], ['14:36:00', 0], ['14:54:00', 0], ['14:57:00', 0], ['15:00:00', 1], ['15:03:00', 0],
        ])->map(fn ($t) => (object) ['lat' => -6.60 + $t[1] * self::LANGKAH, 'lng' => 106.80, 'waktu' => '2026-10-22 '.$t[0]]);

        $hasil = AnomaliGps::analisis($titik);

        $this->assertSame('indikasi', $hasil['tingkat']);
        $this->assertSame(0, $hasil['lompatan'], 'Tidak satu pun segmen melewati 120 km/jam.');
        $this->assertSame(3, $hasil['menyimpang']);
        $this->assertSame(['2026-10-22 14:09:00', '2026-10-22 14:33:00', '2026-10-22 15:00:00'], array_column($hasil['titik_menyimpang'], 'waktu'));
    }

    public function test_aturan_lompatan_lama_tetap_berlaku_berdampingan_dengan_aturan_baru(): void
    {
        $this->menyimpangAktif();

        // Pola lompatan satu menit yang sama dengan tes aturan lama (5 lompatan, 3 kembali).
        $hasil = AnomaliGps::analisis($this->rute([0, 1, 0, 1, 0, 2]));

        $this->assertSame('indikasi', $hasil['tingkat']);
        $this->assertSame(5, $hasil['lompatan']);
    }

    public function test_hasil_terdeteksi_atau_lompatan_tetap_memuat_kunci_titik_menyimpang_kosong(): void
    {
        $this->menyimpangAktif();

        $hasil = AnomaliGps::analisis($this->rute([0, 0, 0, 0], mock: [2]));

        $this->assertSame('terdeteksi', $hasil['tingkat']);
        $this->assertSame(0, $hasil['menyimpang']);
        $this->assertNull($hasil['menyimpang_terjauh']);
        $this->assertSame([], $hasil['titik_menyimpang']);
    }

    public function test_titik_menyimpang_dibatasi_seratus_dan_tetap_berurutan_menurut_waktu(): void
    {
        $this->menyimpangAktif();

        $hasil = AnomaliGps::analisis($this->pelan(array_map(fn ($i) => $i % 2, range(0, 119))));

        // Pada pola 0,1,0,1,... setiap titik di tengah adalah titik menyimpang: 118 titik.
        $this->assertSame(118, $hasil['menyimpang']);
        $this->assertCount(AnomaliGps::MAKS_SEGMEN, $hasil['titik_menyimpang']);
        $waktu = array_column($hasil['titik_menyimpang'], 'waktu');
        $urut = $waktu;
        sort($urut);
        $this->assertSame($urut, $waktu);
    }

    public function test_rute_normal_tidak_pernah_memiliki_titik_menyimpang(): void
    {
        $this->menyimpangAktif(1);
        $titik = collect(range(0, 30))->map(fn ($i) => (object) [
            'lat' => -6.60 + $i * 0.0045, 'lng' => 106.80, 'waktu' => date('Y-m-d H:i:s', strtotime('2026-10-22 10:00:00') + $i * 180),
        ]);

        $this->assertNull(AnomaliGps::analisis($titik));
    }
}
