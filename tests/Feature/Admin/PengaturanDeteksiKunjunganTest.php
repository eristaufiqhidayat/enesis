<?php

namespace Tests\Feature\Admin;

use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

// Pengaturan ambang deteksi "kunjungan berturutan berjarak dekat" (spec 054): halaman baca & ubah dua angka,
// disimpan di pengaturan_admin (kunci → nilai, pola yang sama dengan target Rekap Pencapaian SKU).
class PengaturanDeteksiKunjunganTest extends AdminTestCase
{
    private const URL = '/deteksi_kunjungan';

    public function test_halaman_menampilkan_nilai_default_dari_config_saat_belum_pernah_disimpan(): void
    {
        $this->get(self::URL)->assertInertia(fn (Assert $page) => $page
            ->component('Pengaturan/DeteksiKunjungan')
            ->where('pengaturan.jarak_dekat_meter', config('marketama.deteksi_jarak_kunjungan.jarak_dekat_meter'))
            ->where('pengaturan.min_kunjungan_berturutan', config('marketama.deteksi_jarak_kunjungan.min_kunjungan_berturutan'))
            ->where('bisaUbah', true)
        );
    }

    public function test_menyimpan_nilai_baru_dan_tercatat_di_riwayat_admin(): void
    {
        $this->post(self::URL, ['jarak_dekat_meter' => 8, 'min_kunjungan_berturutan' => 6])->assertRedirect();

        $this->assertSame('8', DB::table('pengaturan_admin')->where('kunci', 'deteksi_kunjungan.jarak_dekat_meter')->value('nilai'));
        $this->assertSame('6', DB::table('pengaturan_admin')->where('kunci', 'deteksi_kunjungan.min_kunjungan_berturutan')->value('nilai'));
        $this->assertSame($this->admin->id, (int) DB::table('pengaturan_admin')->where('kunci', 'deteksi_kunjungan.jarak_dekat_meter')->value('diubah_oleh'));

        $catatan = DB::table('riwayat_admin')->where('objek', 'pengaturan_deteksi_kunjungan')->first();
        $this->assertSame('ubah', $catatan->aksi);
        $this->assertStringContainsString('5m → 8m', $catatan->keterangan);
        $this->assertStringContainsString('4 → 6', $catatan->keterangan);
    }

    public function test_nilai_tersimpan_terbaca_lagi_pada_kunjungan_berikutnya(): void
    {
        $this->post(self::URL, ['jarak_dekat_meter' => 10, 'min_kunjungan_berturutan' => 3]);

        $this->get(self::URL)->assertInertia(fn (Assert $page) => $page
            ->where('pengaturan.jarak_dekat_meter', 10)
            ->where('pengaturan.min_kunjungan_berturutan', 3)
        );
    }

    public function test_validasi_input(): void
    {
        $this->post(self::URL, ['jarak_dekat_meter' => 0, 'min_kunjungan_berturutan' => 4])->assertSessionHasErrors('jarak_dekat_meter');
        $this->post(self::URL, ['jarak_dekat_meter' => 5, 'min_kunjungan_berturutan' => 1])->assertSessionHasErrors('min_kunjungan_berturutan');
        $this->post(self::URL, ['jarak_dekat_meter' => 'bukan-angka', 'min_kunjungan_berturutan' => 4])->assertSessionHasErrors('jarak_dekat_meter');
    }

    public function test_akun_tanpa_hak_ubah_ditolak_tapi_tetap_bisa_melihat(): void
    {
        $role = $this->role('Pemantau Deteksi', 'semua', ['deteksi_kunjungan' => ['lihat']]);
        $akun = $this->akun('pemantau', 'koordinator', 137, ['role_id' => $role]);

        $this->actingAs($akun)->get(self::URL)->assertInertia(fn (Assert $page) => $page->where('bisaUbah', false));
        $this->actingAs($akun)->post(self::URL, ['jarak_dekat_meter' => 9, 'min_kunjungan_berturutan' => 9])->assertForbidden();
        $this->assertFalse(DB::table('pengaturan_admin')->where('kunci', 'deteksi_kunjungan.jarak_dekat_meter')->exists());
    }

    public function test_akun_tanpa_hak_menu_ditolak(): void
    {
        $role = $this->role('Tanpa Deteksi', 'semua', ['home' => ['lihat']]);
        $akun = $this->akun('tanpa', 'koordinator', 137, ['role_id' => $role]);

        $this->actingAs($akun)->get(self::URL)->assertForbidden();
    }
}
