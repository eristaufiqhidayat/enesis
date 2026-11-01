<?php

namespace Tests\Feature\Admin;

use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

// Tab "Lacak Outlet" (tab keempat grup Lacak Motoris): rekap semua tanda outlet.
class RekapOutletDitandaiTest extends AdminTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        DB::table('mk_outlet')->insert([
            ['id' => 9001, 'nama' => 'Toko Fiktif', 'dibuat_oleh' => 'Rudi Hartono'],
            ['id' => 9002, 'nama' => 'Toko Lama', 'dibuat_oleh' => 'Orang Lain'],
        ]);
        $tanda = fn (int $outlet, string $catatan, ?string $dicabut = null) => DB::table('tanda_outlet')->insert([
            'outlet_id' => $outlet, 'kategori' => 'outlet_fiktif', 'catatan' => $catatan, 'dibuat_oleh' => 1,
            'dibuat_nama' => 'koor', 'dicabut_at' => $dicabut, 'created_at' => '2026-10-30 03:00:00', 'updated_at' => '2026-10-30 03:00:00',
        ]);
        $tanda(9001, 'Tidak ada bangunan');
        $tanda(9002, 'Sudah dicabut', '2026-10-31 04:00:00');
    }

    /** @return array<int, array<string, mixed>> */
    private function baris(string $url): array
    {
        $baris = [];
        $this->get($url)->assertOk()->assertInertia(function (Assert $p) use (&$baris) {
            $p->loadDeferredProps(function (Assert $p) use (&$baris) {
                $baris = $p->toArray()['props']['tables'][0]['rows']['data'];
            });
        });

        return $baris;
    }

    public function test_rekap_hanya_memuat_tanda_aktif(): void
    {
        $baris = $this->baris('/outlet_ditandai');

        $this->assertCount(1, $baris);
        $this->assertSame('Toko Fiktif', $baris[0]['outlet']);
        $this->assertSame('Outlet fiktif', $baris[0]['kategori']);
        $this->assertSame('Rudi Hartono', $baris[0]['motoris_noo']);
        $this->assertArrayNotHasKey('status', $baris[0]);
        $this->assertSame('30 Okt 2026 10:00', $baris[0]['ditandai_pada']);
    }

    public function test_depo_diambil_dari_depo_motoris_pendaftar_bila_area_kosong(): void
    {
        $depo = DB::table('mk_karyawan')->where('nama', 'Rudi Hartono')->value('depo_id');
        $namaDepo = DB::table('mk_depo')->where('id', $depo)->value('nama');

        $this->assertNotNull($namaDepo);
        $this->assertSame($namaDepo, $this->baris('/outlet_ditandai')[0]['depo']);
    }

    public function test_koordinator_diambil_dari_motoris_pendaftar(): void
    {
        $idKoor = DB::table('mk_karyawan')->where('nama', 'Rudi Hartono')->value('koordinator_id');
        $namaKoor = DB::table('mk_karyawan')->where('id', $idKoor)->value('nama');

        $this->assertNotNull($namaKoor);
        $this->assertSame($namaKoor, $this->baris('/outlet_ditandai')[0]['koordinator']);
    }

    public function test_pencarian_menurut_motoris_pendaftar(): void
    {
        $this->assertCount(1, $this->baris('/outlet_ditandai?q=Rudi'));
        $this->assertCount(0, $this->baris('/outlet_ditandai?q=Orang'));
    }

    public function test_tab_outlet_ditandai_muncul_di_lacak_motoris(): void
    {
        $this->get('/outlet_ditandai')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('tabHalaman.2', ['slug' => 'outlet_ditandai', 'label' => 'Lacak Outlet']));
    }

    public function test_butuh_hak_lihat_outlet_ditandai(): void
    {
        $tanpa = $this->akun('tanpa', 'koordinator', 137, ['role_id' => $this->role('Tanpa Rekap', 'semua', ['tracking_sales' => ['lihat']])]);
        $dengan = $this->akun('dengan', 'koordinator', 137, ['role_id' => $this->role('Dengan Rekap', 'semua', ['outlet_ditandai' => ['lihat']])]);

        $this->actingAs($tanpa)->get('/outlet_ditandai')->assertForbidden();
        $this->actingAs($dengan)->get('/outlet_ditandai')->assertOk();
    }
}
