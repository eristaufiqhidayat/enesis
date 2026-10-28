<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\LoginOtomatis;
use Tests\TestCase;

// Riwayat transaksi satu distributor untuk drawer di Lap. Loading Barang (lihat Menu/Show.vue).
class RiwayatDistributorLapLoadingBarangTest extends TestCase
{
    use LoginOtomatis, RefreshDatabase;

    private function buatUserDenganHak(string $menu): User
    {
        $role = DB::table('roles')->insertGetId([
            'kode' => 'staf_riwayat', 'nama' => 'Staf Riwayat', 'bawaan' => false, 'cakupan' => 'semua',
            'login_web' => true, 'login_mobile' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('role_hak')->insert([
            'role_id' => $role, 'menu' => $menu,
            'lihat' => true, 'tambah' => false, 'ubah' => false, 'hapus' => false, 'ekspor' => false,
        ]);

        return User::factory()->create(['role_id' => $role]);
    }

    public function test_menampilkan_transaksi_distributor_terbaru_lebih_dulu(): void
    {
        $this->actingAs($this->buatUserDenganHak('lap_loading_barang'));

        DB::table('lap_loading_barang')->insert([
            ['trans_code' => 'L1', 'datetime_trans' => '2026-10-20 08:00:00', 'distributor_name' => 'PT Distributor Contoh', 'koordinator_name' => 'Sugeng', 'warehouse_name' => 'Medan', 'total_qty' => 100, 'grand_total' => 1000000],
            ['trans_code' => 'L2', 'datetime_trans' => '2026-10-22 11:23:47', 'distributor_name' => 'PT Distributor Contoh', 'koordinator_name' => 'Budi', 'warehouse_name' => 'Medan', 'total_qty' => 878, 'grand_total' => 10440000],
            ['trans_code' => 'L3', 'datetime_trans' => '2026-10-21 09:00:00', 'distributor_name' => 'PT Lain', 'koordinator_name' => 'Sugeng', 'warehouse_name' => 'Medan', 'total_qty' => 5, 'grand_total' => 50000],
        ]);

        $this->getJson('/lap_loading_barang/riwayat-distributor?distributor_name='.urlencode('PT Distributor Contoh'))
            ->assertOk()
            ->assertJson([
                'total' => 2,
                'data' => [
                    ['trans_code' => 'L2', 'datetime_trans' => '2026-10-22 11:23:47', 'koordinator_name' => 'Budi', 'total_qty' => 878, 'grand_total' => 10440000],
                    ['trans_code' => 'L1', 'datetime_trans' => '2026-10-20 08:00:00', 'koordinator_name' => 'Sugeng', 'total_qty' => 100, 'grand_total' => 1000000],
                ],
            ]);
    }

    public function test_distributor_kembar_dibedakan_lewat_id(): void
    {
        $this->actingAs($this->buatUserDenganHak('lap_loading_barang'));

        DB::table('lap_loading_barang')->insert([
            ['trans_code' => 'L1', 'datetime_trans' => '2026-10-20 08:00:00', 'distributor_name' => 'PT Kembar', 'distributor_id' => 8, 'grand_total' => 1000000],
            ['trans_code' => 'L2', 'datetime_trans' => '2026-10-21 08:00:00', 'distributor_name' => 'PT Kembar', 'distributor_id' => 9, 'grand_total' => 2000000],
        ]);

        $this->getJson('/lap_loading_barang/riwayat-distributor?distributor_id=9&distributor_name=PT+Kembar')
            ->assertOk()
            ->assertJson(['total' => 1, 'data' => [['trans_code' => 'L2']]]);
    }

    public function test_nama_distributor_wajib_diisi(): void
    {
        $this->actingAs($this->buatUserDenganHak('lap_loading_barang'));

        $this->getJson('/lap_loading_barang/riwayat-distributor')->assertUnprocessable();
    }

    public function test_butuh_hak_lihat_menu_lap_loading_barang(): void
    {
        $this->actingAs($this->buatUserDenganHak('loading_barang'));

        $this->getJson('/lap_loading_barang/riwayat-distributor?distributor_name=PT+X')->assertForbidden();
    }
}
