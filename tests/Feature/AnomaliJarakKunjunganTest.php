<?php

namespace Tests\Feature;

use App\Support\Tracking\AnomaliJarakKunjungan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

// Indikasi kunjungan outlet berurutan berjarak dekat (spec 054): daftar SEMUA runtun kunjungan (bisa lebih dari
// satu per hari) yang jarak perjalanannya (dari KunjunganHarian) di bawah ambang, urut dari yang terpanjang.
// Ambang dikirim pemanggil (dari localStorage browser lewat endpoint locations); tanpa nilai dipakai bawaan
// config('marketama.deteksi_jarak_kunjungan') yaitu jarak 11 m dan minimum 3 kunjungan. Tes di bawah memakai 5 m / 4 kunjungan eksplisit.
class AnomaliJarakKunjunganTest extends TestCase
{
    use RefreshDatabase;

    /** @param array<int, array{jam: string, outlet_id: ?int, jarak_m: ?int}> $baris jarak_m null = tanpa perjalanan (memutus runtun) */
    private function kunjungan(array $baris): Collection
    {
        return collect($baris)->map(fn ($b, $i) => (object) [
            'id' => $i + 1,
            'jam' => $b['jam'],
            'outlet_id' => array_key_exists('outlet_id', $b) ? $b['outlet_id'] : $i + 100,
            'perjalanan' => $i === 0 ? null : ($b['jarak_m'] === null ? null : ['menit' => 10, 'jarak_m' => $b['jarak_m']]),
        ]);
    }

    public function test_runtun_di_bawah_ambang_tidak_menghasilkan_peringatan(): void
    {
        // Minimum 4. Hanya 3 berturutan < 5m.
        $k = $this->kunjungan([
            ['jam' => '08:00', 'jarak_m' => null],
            ['jam' => '08:05', 'jarak_m' => 2],
            ['jam' => '08:10', 'jarak_m' => 3],
        ]);

        $this->assertSame([], AnomaliJarakKunjungan::analisis($k, 5, 4));
    }

    public function test_tepat_di_ambang_menghasilkan_peringatan(): void
    {
        // 4 kunjungan: 3 pasangan berurutan < 5m.
        $k = $this->kunjungan([
            ['jam' => '08:00', 'jarak_m' => null],
            ['jam' => '08:05', 'jarak_m' => 2],
            ['jam' => '08:10', 'jarak_m' => 3],
            ['jam' => '08:15', 'jarak_m' => 0],
        ]);

        $hasil = AnomaliJarakKunjungan::analisis($k, 5, 4);

        $this->assertCount(1, $hasil);
        $this->assertSame(4, $hasil[0]['jumlah']);
        $this->assertSame([1, 2, 3, 4], $hasil[0]['kunjungan_id']);
        $this->assertSame('08:00', $hasil[0]['dari_jam']);
        $this->assertSame('08:15', $hasil[0]['sampai_jam']);
    }

    public function test_dua_runtun_terpisah_sama_sama_dilaporkan_bukan_hanya_yang_terpanjang(): void
    {
        // Runtun pertama: 4 kunjungan dekat (08:00-08:15). Lompatan jauh. Runtun kedua: 6 kunjungan dekat (lebih panjang).
        $k = $this->kunjungan([
            ['jam' => '08:00', 'jarak_m' => null],
            ['jam' => '08:05', 'jarak_m' => 2],
            ['jam' => '08:10', 'jarak_m' => 3],
            ['jam' => '08:15', 'jarak_m' => 0],
            ['jam' => '09:00', 'jarak_m' => 5000], // memutus runtun
            ['jam' => '09:05', 'jarak_m' => 1],
            ['jam' => '09:10', 'jarak_m' => 2],
            ['jam' => '09:15', 'jarak_m' => 3],
            ['jam' => '09:20', 'jarak_m' => 4],
            ['jam' => '09:25', 'jarak_m' => 0],
        ]);

        $hasil = AnomaliJarakKunjungan::analisis($k, 5, 4);

        // Urut dari yang terpanjang, tapi runtun pertama (4) tetap ikut dilaporkan, tidak hilang karena kalah panjang.
        $this->assertCount(2, $hasil);
        $this->assertSame(6, $hasil[0]['jumlah']);
        $this->assertSame([5, 6, 7, 8, 9, 10], $hasil[0]['kunjungan_id']);
        $this->assertSame(4, $hasil[1]['jumlah']);
        $this->assertSame([1, 2, 3, 4], $hasil[1]['kunjungan_id']);
    }

    public function test_runtun_terputus_oleh_kunjungan_jauh_diambil_yang_memenuhi_ambang_saja(): void
    {
        $k = $this->kunjungan([
            ['jam' => '08:00', 'jarak_m' => null],
            ['jam' => '08:05', 'jarak_m' => 2],
            ['jam' => '08:10', 'jarak_m' => 3],
            ['jam' => '08:15', 'jarak_m' => 0],
            // Jauh: memutus runtun. Sisanya (2 kunjungan) di bawah ambang (4), tidak ikut dilaporkan.
            ['jam' => '09:00', 'jarak_m' => 5000],
            ['jam' => '09:05', 'jarak_m' => 1],
        ]);

        $hasil = AnomaliJarakKunjungan::analisis($k, 5, 4);

        $this->assertCount(1, $hasil);
        $this->assertSame(4, $hasil[0]['jumlah']);
        $this->assertSame([1, 2, 3, 4], $hasil[0]['kunjungan_id']);
    }

    public function test_runtun_terputus_oleh_kunjungan_tanpa_koordinat(): void
    {
        // Kunjungan tanpa koordinat (perjalanan.jarak_m null) memutus runtun, bukan dianggap dekat.
        $k = $this->kunjungan([
            ['jam' => '08:00', 'jarak_m' => null],
            ['jam' => '08:05', 'jarak_m' => 2],
            ['jam' => '08:10', 'jarak_m' => 3],
            ['jam' => '08:15', 'jarak_m' => null], // tanpa koordinat
            ['jam' => '08:20', 'jarak_m' => 2],
            ['jam' => '08:25', 'jarak_m' => 3],
        ]);

        // Dua runtun terpisah masing-masing 3 kunjungan: keduanya di bawah ambang (4).
        $this->assertSame([], AnomaliJarakKunjungan::analisis($k, 5, 4));
    }

    public function test_ambang_jarak_tepat_batas_tidak_dihitung_dekat(): void
    {
        // Bawaan dari config tanpa parameter ambang.
        $k = $this->kunjungan([
            ['jam' => '08:00', 'jarak_m' => null],
            ['jam' => '08:05', 'jarak_m' => 5], // tepat di ambang: tidak dihitung dekat
            ['jam' => '08:10', 'jarak_m' => 4],
            ['jam' => '08:15', 'jarak_m' => 4],
        ]);

        $this->assertSame([], AnomaliJarakKunjungan::analisis($k, 5, 4));
    }

    public function test_bawaan_tanpa_parameter_adalah_11_meter_dan_minimum_3_kunjungan(): void
    {
        // 3 kunjungan berturutan masing-masing berjarak 10 m (< 11 m) memenuhi minimum 3; 11 m tepat tidak termasuk dekat.
        $k = $this->kunjungan([
            ['jam' => '08:00', 'jarak_m' => null],
            ['jam' => '08:05', 'jarak_m' => 10],
            ['jam' => '08:10', 'jarak_m' => 10],
        ]);
        $hasil = AnomaliJarakKunjungan::analisis($k);
        $this->assertCount(1, $hasil);
        $this->assertSame(3, $hasil[0]['jumlah']);
        $this->assertStringContainsString('11 m', $hasil[0]['saran']['catatan']);

        $tepat = $this->kunjungan([
            ['jam' => '08:00', 'jarak_m' => null],
            ['jam' => '08:05', 'jarak_m' => 11],
            ['jam' => '08:10', 'jarak_m' => 11],
        ]);
        $this->assertSame([], AnomaliJarakKunjungan::analisis($tepat));
    }

    public function test_bawaan_mengikuti_config_dan_parameter_mengalahkan_config(): void
    {
        config(['marketama.deteksi_jarak_kunjungan' => ['jarak_dekat_meter' => 50, 'min_kunjungan_berturutan' => 2]]);

        $k = $this->kunjungan([
            ['jam' => '08:00', 'jarak_m' => null],
            ['jam' => '08:05', 'jarak_m' => 40],
        ]);

        $this->assertCount(1, AnomaliJarakKunjungan::analisis($k));
        $this->assertSame([], AnomaliJarakKunjungan::analisis($k, 30, 2));
    }

    public function test_saran_berisi_outlet_unik_dan_ringkasan_jam(): void
    {
        $k = $this->kunjungan([
            ['jam' => '08:00', 'jarak_m' => null, 'outlet_id' => 10],
            ['jam' => '08:05', 'jarak_m' => 2, 'outlet_id' => 11],
            ['jam' => '08:10', 'jarak_m' => 3, 'outlet_id' => 10], // outlet sama lagi
            ['jam' => '08:15', 'jarak_m' => 0, 'outlet_id' => null], // outlet baru, belum punya id
        ]);

        $hasil = AnomaliJarakKunjungan::analisis($k, 5, 4)[0];

        $this->assertSame([10, 11], $hasil['outlet_id']);
        $this->assertSame('pola_kunjungan_tidak_wajar', $hasil['saran']['kategori']);
        $this->assertStringContainsString('4 kunjungan outlet berturutan berjarak kurang dari 5 m', $hasil['saran']['catatan']);
        $this->assertStringContainsString('dari jam 08:00 sampai 08:15', $hasil['saran']['catatan']);
    }

    public function test_kunjungan_kosong_tidak_menghasilkan_peringatan(): void
    {
        $this->assertSame([], AnomaliJarakKunjungan::analisis(collect()));
    }
}
