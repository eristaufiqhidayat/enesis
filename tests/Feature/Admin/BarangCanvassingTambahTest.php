<?php

namespace Tests\Feature\Admin;

use Illuminate\Support\Facades\DB;

// Endpoint opsi & simpan muatan canvassing dari web (form Tambah Barang Canvassing).
class BarangCanvassingTambahTest extends AdminTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setTimezone('Asia/Jakarta')->setDate(2026, 10, 7)->setTime(12, 0));

        DB::table('mk_produk')->insert([
            ['id' => 3, 'kode' => 'SKU-3', 'nama' => 'Adem Sari'],
            ['id' => 4, 'kode' => 'SKU-4', 'nama' => 'Plossa'],
        ]);
    }

    private function payload(array $timpa = []): array
    {
        return $timpa + [
            'tanggal' => '2026-10-07', 'depo_id' => 46, 'karyawan_id' => 1,
            'items' => [['produk_id' => 3, 'qty_bawa' => 10], ['produk_id' => 4, 'qty_bawa' => 5.5]],
        ];
    }

    public function test_simpan_valid_membuat_header_dan_item(): void
    {
        $this->post('/barang_canvassing', $this->payload())
            ->assertRedirect('/barang_canvassing?tanggal=2026-10-07')
            ->assertSessionHas('sukses');

        $c = DB::table('trx_canvassing')->first();
        $this->assertSame('CNV20261007-'.str_pad((string) $c->id, 5, '0', STR_PAD_LEFT), $c->nomor);
        $this->assertSame(['disiapkan', 'web', 1, 46], [$c->status, $c->sumber, (int) $c->karyawan_id, (int) $c->depo_id]);
        $this->assertNotNull($c->disiapkan_at);
        $this->assertNull($c->diterima_at);
        $this->assertSame(2, DB::table('trx_canvassing_item')->where('canvassing_id', $c->id)->count());
        $this->assertEquals(5.5, DB::table('trx_canvassing_item')->where('produk_id', 4)->value('qty_bawa'));
    }

    public function test_muatan_baru_tampil_di_tabel_dengan_label_web(): void
    {
        $this->post('/barang_canvassing', $this->payload());

        $this->get('/barang_canvassing')->assertInertia(fn ($p) => $p
            ->loadDeferredProps(fn ($p) => $p
                ->where('tables.0.rows.total', 1)
                ->where('tables.0.rows.data.0.status', 'Disiapkan')
                ->where('tables.0.rows.data.0.sumber', 'Web Enesis')));
    }

    public function test_opsi_memuat_sales_depo_dan_produk(): void
    {
        $this->getJson('/barang_canvassing/opsi?depo=46')->assertOk()
            ->assertJsonCount(2, 'sales') // Rudi & Tegar; Joko nonaktif, Sinta depo lain
            ->assertJsonPath('sales.0.nama', 'Rudi Hartono')
            ->assertJsonCount(2, 'produk')
            ->assertJsonPath('produk.0.kode', 'SKU-3');
    }

    public function test_validasi_menolak_data_tidak_valid(): void
    {
        $kasus = [
            'produk dobel' => ['items' => [['produk_id' => 3, 'qty_bawa' => 1], ['produk_id' => 3, 'qty_bawa' => 2]]],
            'qty nol' => ['items' => [['produk_id' => 3, 'qty_bawa' => 0]]],
            'qty negatif' => ['items' => [['produk_id' => 3, 'qty_bawa' => -2]]],
            'tanpa produk' => ['items' => []],
            'produk tidak ada' => ['items' => [['produk_id' => 999, 'qty_bawa' => 1]]],
            'sales beda depo' => ['karyawan_id' => 2],
            'bukan sales' => ['karyawan_id' => 500],
            'sales nonaktif' => ['karyawan_id' => 4],
            'koordinator' => ['karyawan_id' => 137],
            'depo tidak ada' => ['depo_id' => 999],
        ];

        foreach ($kasus as $nama => $timpa) {
            $this->postJson('/barang_canvassing', $this->payload($timpa))->assertStatus(422, $nama);
        }

        $this->assertSame(0, DB::table('trx_canvassing')->count());
    }

    public function test_sales_tanpa_koordinator_ditolak(): void
    {
        DB::table('mk_karyawan')->where('id', 3)->update(['koordinator_id' => null]);

        $this->postJson('/barang_canvassing', $this->payload(['karyawan_id' => 3]))->assertStatus(422);
    }

    public function test_muatan_kedua_sales_sama_tanggal_sama_ditolak(): void
    {
        $this->post('/barang_canvassing', $this->payload())->assertRedirect();

        $this->postJson('/barang_canvassing', $this->payload())->assertStatus(422)
            ->assertJsonValidationErrors('karyawan_id');
        $this->assertSame(1, DB::table('trx_canvassing')->count());

        // tanggal lain atau sales lain boleh
        $this->post('/barang_canvassing', $this->payload(['tanggal' => '2026-10-08']))->assertRedirect();
        $this->post('/barang_canvassing', $this->payload(['karyawan_id' => 3]))->assertRedirect();
        $this->assertSame(3, DB::table('trx_canvassing')->count());
    }

    public function test_tanpa_hak_tambah_mendapat_403(): void
    {
        $akun = $this->akun('lihat.saja', 'lainnya', 500);
        DB::table('users')->where('id', $akun->id)->update(['role_id' => $this->role('Lihat Saja', 'semua', ['barang_canvassing' => ['lihat']])]);

        $this->actingAs($akun->fresh())->postJson('/barang_canvassing', $this->payload())->assertForbidden();
        $this->actingAs($akun->fresh())->getJson('/barang_canvassing/opsi?depo=46')->assertForbidden();
        $this->assertSame(0, DB::table('trx_canvassing')->count());
    }

    public function test_add_mengarah_ke_modal_di_tabel_dan_butuh_hak_tambah(): void
    {
        $this->get('/barang_canvassing/add')->assertRedirect('/barang_canvassing?tambah=1');

        $akun = $this->akun('lihat.saja', 'lainnya', 500);
        DB::table('users')->where('id', $akun->id)->update(['role_id' => $this->role('Lihat Saja', 'semua', ['barang_canvassing' => ['lihat']])]);
        $this->actingAs($akun->fresh())->get('/barang_canvassing/add')->assertForbidden();
        $this->actingAs($akun->fresh())->get('/barang_canvassing')->assertInertia(fn ($p) => $p->where('bisaTambah', false));

        $this->actingAs($this->admin)->get('/barang_canvassing')->assertInertia(fn ($p) => $p->where('bisaTambah', true));
    }

    public function test_opsi_tanpa_depo_memuat_depo_dan_tanggal_saja(): void
    {
        $this->getJson('/barang_canvassing/opsi')->assertOk()
            ->assertJsonCount(2, 'depo')
            ->assertJsonPath('tanggal', '2026-10-07')
            ->assertJsonCount(0, 'sales')
            ->assertJsonCount(0, 'produk');

        // role bercakupan depo hanya melihat depo-nya sendiri
        $admin = $this->akun('admin.sidoarjo', 'lainnya', 500);
        DB::table('users')->where('id', $admin->id)->update(['role_id' => $this->role('Admin Depo 46', 'depo', ['barang_canvassing' => ['lihat', 'tambah']])]);
        $this->actingAs($admin->fresh())->getJson('/barang_canvassing/opsi')
            ->assertJsonCount(1, 'depo')->assertJsonPath('depo.0.value', 46);
    }

    public function test_cakupan_depo_tidak_bisa_membuat_di_luar_depo(): void
    {
        DB::table('mk_karyawan')->where('id', 2)->update(['koordinator_id' => 137]); // Sinta (depo 22) valid secara kategori
        $admin = $this->akun('admin.sidoarjo', 'lainnya', 500);
        DB::table('users')->where('id', $admin->id)->update(['role_id' => $this->role('Admin Depo 46', 'depo', ['barang_canvassing' => ['lihat', 'tambah']])]);
        $this->actingAs($admin->fresh());

        $this->postJson('/barang_canvassing', $this->payload(['depo_id' => 22, 'karyawan_id' => 2]))->assertStatus(422);
        $this->assertSame(0, DB::table('trx_canvassing')->count());

        $this->getJson('/barang_canvassing/opsi?depo=22')->assertOk()->assertJsonCount(0, 'sales');

        $this->post('/barang_canvassing', $this->payload())->assertRedirect('/barang_canvassing?tanggal=2026-10-07');
        $this->assertSame(1, DB::table('trx_canvassing')->count());
    }
}
