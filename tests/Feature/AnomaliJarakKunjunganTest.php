<?php

namespace Tests\Feature;

use App\Support\Tracking\AnomaliJarakKunjungan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// Indikasi kunjungan outlet berurutan berjarak dekat (spec 054): runtun terpanjang kunjungan yang jarak
// perjalanannya (dari KunjunganHarian) di bawah ambang. Ambang dibaca dari pengaturan_admin (kunci
// deteksi_kunjungan.*), fallback ke config('marketama.deteksi_jarak_kunjungan') bila belum pernah disimpan
// (default: jarak 5 m, minimum 4 kunjungan).
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
        // Default: minimum 4. Hanya 3 berturutan < 5m.
        $k = $this->kunjungan([
            ['jam' => '08:00', 'jarak_m' => null],
            ['jam' => '08:05', 'jarak_m' => 2],
            ['jam' => '08:10', 'jarak_m' => 3],
        ]);

        $this->assertNull(AnomaliJarakKunjungan::analisis($k));
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

        $hasil = AnomaliJarakKunjungan::analisis($k);

        $this->assertNotNull($hasil);
        $this->assertSame(4, $hasil['jumlah']);
        $this->assertSame([1, 2, 3, 4], $hasil['kunjungan_id']);
        $this->assertSame('08:00', $hasil['dari_jam']);
        $this->assertSame('08:15', $hasil['sampai_jam']);
    }

    public function test_runtun_terputus_oleh_kunjungan_jauh_diambil_yang_terpanjang(): void
    {
        $k = $this->kunjungan([
            ['jam' => '08:00', 'jarak_m' => null],
            ['jam' => '08:05', 'jarak_m' => 2],
            ['jam' => '08:10', 'jarak_m' => 3],
            ['jam' => '08:15', 'jarak_m' => 0],
            // Jauh: memutus runtun.
            ['jam' => '09:00', 'jarak_m' => 5000],
            ['jam' => '09:05', 'jarak_m' => 1],
        ]);

        $hasil = AnomaliJarakKunjungan::analisis($k);

        $this->assertSame(4, $hasil['jumlah']);
        $this->assertSame([1, 2, 3, 4], $hasil['kunjungan_id']);
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
        $this->assertNull(AnomaliJarakKunjungan::analisis($k));
    }

    public function test_ambang_jarak_tepat_batas_tidak_dihitung_dekat(): void
    {
        // Pakai nilai default dari config (belum pernah disimpan di pengaturan_admin): jarak dekat 5 m.
        $k = $this->kunjungan([
            ['jam' => '08:00', 'jarak_m' => null],
            ['jam' => '08:05', 'jarak_m' => 5], // tepat di ambang: tidak dihitung dekat
            ['jam' => '08:10', 'jarak_m' => 4],
            ['jam' => '08:15', 'jarak_m' => 4],
        ]);

        $this->assertNull(AnomaliJarakKunjungan::analisis($k));
    }

    public function test_ambang_dibaca_dari_pengaturan_admin_bukan_ditanam_tetap(): void
    {
        DB::table('pengaturan_admin')->insert([
            ['kunci' => 'deteksi_kunjungan.min_kunjungan_berturutan', 'nilai' => '2', 'created_at' => now(), 'updated_at' => now()],
            ['kunci' => 'deteksi_kunjungan.jarak_dekat_meter', 'nilai' => '100', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $k = $this->kunjungan([
            ['jam' => '08:00', 'jarak_m' => null],
            ['jam' => '08:05', 'jarak_m' => 90],
        ]);

        $hasil = AnomaliJarakKunjungan::analisis($k);
        $this->assertNotNull($hasil);
        $this->assertSame(2, $hasil['jumlah']);
    }

    public function test_fallback_ke_config_bila_kunci_pengaturan_belum_pernah_disimpan(): void
    {
        config(['marketama.deteksi_jarak_kunjungan' => ['jarak_dekat_meter' => 50, 'min_kunjungan_berturutan' => 2]]);

        $k = $this->kunjungan([
            ['jam' => '08:00', 'jarak_m' => null],
            ['jam' => '08:05', 'jarak_m' => 40],
        ]);

        $hasil = AnomaliJarakKunjungan::analisis($k);
        $this->assertNotNull($hasil);
        $this->assertSame(2, $hasil['jumlah']);
    }

    public function test_saran_berisi_outlet_unik_dan_ringkasan_jam(): void
    {
        $k = $this->kunjungan([
            ['jam' => '08:00', 'jarak_m' => null, 'outlet_id' => 10],
            ['jam' => '08:05', 'jarak_m' => 2, 'outlet_id' => 11],
            ['jam' => '08:10', 'jarak_m' => 3, 'outlet_id' => 10], // outlet sama lagi
            ['jam' => '08:15', 'jarak_m' => 0, 'outlet_id' => null], // outlet baru, belum punya id
        ]);

        $hasil = AnomaliJarakKunjungan::analisis($k);

        $this->assertSame([10, 11], $hasil['outlet_id']);
        $this->assertSame('pola_kunjungan_tidak_wajar', $hasil['saran']['kategori']);
        $this->assertStringContainsString('4 kunjungan outlet berturutan berjarak kurang dari 5 m', $hasil['saran']['catatan']);
        $this->assertStringContainsString('dari jam 08:00 sampai 08:15', $hasil['saran']['catatan']);
    }

    public function test_kunjungan_kosong_tidak_menghasilkan_peringatan(): void
    {
        $this->assertNull(AnomaliJarakKunjungan::analisis(collect()));
    }
}
