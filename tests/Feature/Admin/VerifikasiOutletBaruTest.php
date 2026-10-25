<?php

namespace Tests\Feature\Admin;

use App\Support\Outlet\VerifikasiOutletBaru;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

// Setujui / tolak outlet baru dari HP di menu All Outlet (tab Outlet Baru dari HP).
class VerifikasiOutletBaruTest extends AdminTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        DB::table('mk_area')->insert(['id' => 70, 'nama' => 'Sidoarjo Kota 01', 'depo_id' => 46]);
        DB::table('mk_outlet')->insert(['id' => 554000, 'nama' => 'Outlet Marketama', 'area_id' => 70, 'latitude' => -7.4, 'longitude' => 112.7]);
    }

    private function outletBaru(int $id, int $sales = 1, string $status = 'menunggu'): void
    {
        DB::table('trx_outlet_baru')->insert([
            'id' => $id, 'karyawan_id' => $sales, 'depo_id' => 46, 'area_id' => 70, 'nama' => "Warung {$id}",
            'alamat' => 'Jl. Mawar', 'lat' => -7.45, 'lng' => 112.71, 'status' => $status, 'recorded_at' => '2026-10-24 09:15:00',
        ]);
    }

    public function test_setujui_membuat_master_outlet_dan_menyambung_kunjungan_penjualan(): void
    {
        $this->outletBaru(5);
        $kunjungan = DB::table('trx_kunjungan')->insertGetId([
            'karyawan_id' => 1, 'outlet_baru_id' => 5, 'tanggal' => '2026-10-24', 'checkin_at' => '2026-10-24 09:20:00',
        ]);
        DB::table('trx_penjualan')->insert([
            'kode' => 'PJ-1', 'kunjungan_id' => $kunjungan, 'karyawan_id' => 1, 'tanggal' => '2026-10-24',
            'jumlah_sku' => 1, 'total_qty' => 2, 'total' => 40000, 'is_effective_call' => true,
        ]);

        $this->from('/outlet_all?tab=baru')->post('/outlet_all/baru/5/setujui')
            ->assertRedirect('/outlet_all?tab=baru')
            ->assertSessionHas('sukses');

        $id = VerifikasiOutletBaru::ID_AWAL;
        $outlet = DB::table('mk_outlet')->where('id', $id)->first();
        $this->assertSame('NOO000005', $outlet->kode);
        $this->assertSame('Warung 5', $outlet->nama);
        $this->assertSame(70, (int) $outlet->area_id);
        $this->assertSame('Sidoarjo Kota 01', $outlet->area);
        $this->assertSame('Rudi Hartono', $outlet->dibuat_oleh);

        $baru = DB::table('trx_outlet_baru')->where('id', 5)->first();
        $this->assertSame('disetujui', $baru->status);
        $this->assertSame($id, (int) $baru->outlet_id);
        $this->assertSame($this->admin->id, (int) $baru->diverifikasi_oleh);

        $this->assertSame($id, (int) DB::table('trx_kunjungan')->where('id', $kunjungan)->value('outlet_id'));
        $this->assertSame($id, (int) DB::table('trx_penjualan')->where('kode', 'PJ-1')->value('outlet_id'));
        $this->assertTrue(DB::table('riwayat_admin')->where('objek', 'outlet_baru')->where('objek_id', 5)->where('aksi', 'setujui')->exists());
    }

    public function test_id_master_berikutnya_berurutan_dan_tidak_bentrok_dengan_id_marketama(): void
    {
        $this->outletBaru(5);
        $this->outletBaru(6);

        $this->post('/outlet_all/baru/5/setujui');
        $this->post('/outlet_all/baru/6/setujui');

        $this->assertSame(VerifikasiOutletBaru::ID_AWAL + 1, (int) DB::table('trx_outlet_baru')->where('id', 6)->value('outlet_id'));
        $this->assertSame(3, DB::table('mk_outlet')->count());
    }

    public function test_outlet_yang_sudah_diverifikasi_tidak_bisa_diverifikasi_lagi(): void
    {
        $this->outletBaru(5);
        $this->post('/outlet_all/baru/5/setujui');

        $this->post('/outlet_all/baru/5/setujui')->assertSessionHas('galat');
        $this->post('/outlet_all/baru/5/tolak', ['alasan' => 'Dobel'])->assertSessionHas('galat');

        $this->assertSame(2, DB::table('mk_outlet')->count());
        $this->assertSame('disetujui', DB::table('trx_outlet_baru')->where('id', 5)->value('status'));
    }

    public function test_tolak_wajib_alasan_dan_tidak_membuat_master(): void
    {
        $this->outletBaru(5);

        $this->post('/outlet_all/baru/5/tolak', ['alasan' => ''])->assertSessionHasErrors('alasan');
        $this->assertSame('menunggu', DB::table('trx_outlet_baru')->where('id', 5)->value('status'));

        $this->post('/outlet_all/baru/5/tolak', ['alasan' => 'Toko sudah tutup'])->assertSessionHas('sukses');

        $baru = DB::table('trx_outlet_baru')->where('id', 5)->first();
        $this->assertSame('ditolak', $baru->status);
        $this->assertSame('Toko sudah tutup', $baru->alasan_tolak);
        $this->assertNull($baru->outlet_id);
        $this->assertSame(1, DB::table('mk_outlet')->count());
    }

    public function test_role_tanpa_hak_ubah_tidak_bisa_verifikasi_dan_tidak_melihat_tombol(): void
    {
        $this->outletBaru(5);
        $role = $this->role('Pemantau', 'semua', ['outlet_all' => ['lihat']]);
        $this->actingAs($this->akun('pemantau', 'pemantau', null, ['role_id' => $role]));

        $this->post('/outlet_all/baru/5/setujui')->assertForbidden();
        $this->get('/outlet_all?tab=baru')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $page) => $page->where('tables.0.aksi', null)));

        $this->assertSame('menunggu', DB::table('trx_outlet_baru')->where('id', 5)->value('status'));
    }

    public function test_admin_depo_hanya_melihat_dan_memverifikasi_outlet_sales_depo_sendiri(): void
    {
        $this->outletBaru(5, sales: 1); // depo 46
        $this->outletBaru(6, sales: 2); // depo 22
        $role = $this->role('Admin Depo', 'depo', ['outlet_all' => ['lihat', 'ubah']]);
        $this->actingAs($this->akun('admindepo', 'admin_depo', 500, ['role_id' => $role]));

        $this->get('/outlet_all?tab=baru')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps(fn (Assert $page) => $page
                    ->where('tables.0.aksi', 'verifikasi_outlet_baru')
                    ->where('tables.0.rows.total', 1)
                    ->where('tables.0.rows.data.0.id', 5)
                )
            );

        $this->post('/outlet_all/baru/6/setujui')->assertForbidden();
        $this->post('/outlet_all/baru/5/setujui')->assertSessionHas('sukses');
    }
}
