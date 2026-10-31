<?php

namespace Tests\Feature\Admin;

use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

// Batalkan (soft delete) muatan canvassing yang masih Disiapkan: DELETE /barang_canvassing/{id}.
class BarangCanvassingBatalkanTest extends AdminTestCase
{
    private int $muatan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setTimezone('Asia/Jakarta')->setDate(2026, 10, 7)->setTime(12, 0));

        DB::table('mk_produk')->insert([['id' => 3, 'kode' => 'SKU-3', 'nama' => 'Adem Sari'], ['id' => 4, 'kode' => 'SKU-4', 'nama' => 'Plossa']]);
        $this->muatan = $this->buatMuatan();
    }

    private function buatMuatan(array $header = [], int $karyawan = 1, string $nomor = 'CNV-1'): int
    {
        $id = DB::table('trx_canvassing')->insertGetId($header + [
            'nomor' => $nomor, 'karyawan_id' => $karyawan, 'depo_id' => 46, 'tanggal' => '2026-10-07',
            'status' => 'disiapkan', 'disiapkan_at' => '2026-10-07 01:00:00', 'sumber' => 'web',
        ]);
        DB::table('trx_canvassing_item')->insert([['canvassing_id' => $id, 'produk_id' => 3, 'qty_bawa' => 10]]);

        return $id;
    }

    private function akunDenganHak(string $nama, string $cakupan, array $aksi, ?int $karyawan = 500)
    {
        $akun = $this->akun(str($nama)->slug('.'), 'lainnya', $karyawan);
        DB::table('users')->where('id', $akun->id)->update(['role_id' => $this->role($nama, $cakupan, ['barang_canvassing' => $aksi])]);

        return $akun->fresh();
    }

    public function test_batalkan_muatan_disiapkan_mengisi_penanda_dan_pelaku(): void
    {
        $this->deleteJson("/barang_canvassing/{$this->muatan}")->assertOk()->assertJsonPath('ok', true);

        $m = DB::table('trx_canvassing')->where('id', $this->muatan)->first();
        $this->assertSame('dibatalkan', $m->status);
        $this->assertNotNull($m->dibatalkan_at);
        $this->assertSame($this->admin->id, (int) $m->dibatalkan_oleh); // ID akun yang login
        $this->assertSame(1, DB::table('trx_canvassing_item')->where('canvassing_id', $this->muatan)->count()); // item tetap ada
        $this->assertSame(1, DB::table('riwayat_admin')->where('objek', 'barang_canvassing')->where('aksi', 'hapus')->count());
    }

    public function test_pelaku_diambil_dari_akun_login_bukan_dari_input(): void
    {
        $this->deleteJson("/barang_canvassing/{$this->muatan}", ['dibatalkan_oleh' => 12345])->assertOk();

        $this->assertSame($this->admin->id, (int) DB::table('trx_canvassing')->where('id', $this->muatan)->value('dibatalkan_oleh'));
    }

    public function test_muatan_dibatalkan_hilang_dari_daftar_dan_tidak_bisa_dibuka_atau_diubah(): void
    {
        $this->get('/barang_canvassing')->assertInertia(fn (Assert $p) => $p->loadDeferredProps(fn (Assert $p) => $p->where('tables.0.rows.total', 1)));

        $this->deleteJson("/barang_canvassing/{$this->muatan}")->assertOk();

        $this->get('/barang_canvassing')->assertInertia(fn (Assert $p) => $p->loadDeferredProps(fn (Assert $p) => $p->where('tables.0.rows.total', 0)));
        $this->getJson("/barang_canvassing/{$this->muatan}/item")->assertNotFound();
        $this->putJson("/barang_canvassing/{$this->muatan}/item", ['items' => [['produk_id' => 3, 'qty_bawa' => 1]]])->assertNotFound();
        $this->deleteJson("/barang_canvassing/{$this->muatan}")->assertNotFound(); // tidak bisa dibatalkan dua kali
    }

    public function test_muatan_yang_sudah_diterima_ditolak_409_dan_tidak_berubah(): void
    {
        foreach (['diterima', 'dikembalikan', 'selesai'] as $i => $status) {
            $id = $this->buatMuatan(['status' => $status, 'diterima_at' => '2026-10-07 02:00:00'], 3, "CNV-S$i");

            $this->deleteJson("/barang_canvassing/$id")->assertStatus(409);

            $m = DB::table('trx_canvassing')->where('id', $id)->first();
            $this->assertSame([$status, null, null], [$m->status, $m->dibatalkan_at, $m->dibatalkan_oleh]);
        }
    }

    public function test_muatan_tidak_ada_atau_di_luar_aturan_koordinator_motoris_404(): void
    {
        $this->deleteJson('/barang_canvassing/999999')->assertNotFound();

        $gudang = $this->buatMuatan([], 500, 'CNV-G'); // karyawan tanpa koordinator
        $this->deleteJson("/barang_canvassing/$gudang")->assertNotFound();
    }

    public function test_tanpa_hak_hapus_mendapat_403(): void
    {
        $akun = $this->akunDenganHak('Ubah Saja', 'semua', ['lihat', 'tambah', 'ubah']);

        $this->actingAs($akun)->deleteJson("/barang_canvassing/{$this->muatan}")->assertForbidden();

        $this->assertSame('disiapkan', DB::table('trx_canvassing')->where('id', $this->muatan)->value('status'));
    }

    public function test_cakupan_depo_tidak_bisa_membatalkan_muatan_depo_lain(): void
    {
        $balaraja = $this->akunDenganHak('Admin Balaraja', 'depo', ['lihat', 'hapus'], 2);
        $this->actingAs($balaraja)->deleteJson("/barang_canvassing/{$this->muatan}")->assertNotFound();
        $this->assertNull(DB::table('trx_canvassing')->where('id', $this->muatan)->value('dibatalkan_at'));

        $sidoarjo = $this->akunDenganHak('Admin Sidoarjo', 'depo', ['lihat', 'hapus'], 500);
        $this->actingAs($sidoarjo)->deleteJson("/barang_canvassing/{$this->muatan}")->assertOk();
        $this->assertSame($sidoarjo->id, (int) DB::table('trx_canvassing')->where('id', $this->muatan)->value('dibatalkan_oleh'));
    }

    public function test_muatan_pengganti_bisa_dibuat_setelah_pembatalan(): void
    {
        $payload = ['tanggal' => '2026-10-07', 'depo_id' => 46, 'karyawan_id' => 1, 'items' => [['produk_id' => 3, 'qty_bawa' => 5]]];

        $this->postJson('/barang_canvassing', $payload)->assertStatus(422); // masih ada muatan CNV-1

        $this->deleteJson("/barang_canvassing/{$this->muatan}")->assertOk();

        $this->post('/barang_canvassing', $payload)->assertRedirect();
        $this->assertSame(1, DB::table('trx_canvassing')->where('karyawan_id', 1)->whereNull('dibatalkan_at')->count());
    }

    public function test_respons_item_memuat_dapat_dibatalkan_sesuai_status_dan_hak(): void
    {
        $diterima = $this->buatMuatan(['status' => 'diterima', 'diterima_at' => '2026-10-07 02:00:00'], 3, 'CNV-D');

        $this->getJson("/barang_canvassing/{$this->muatan}/item")->assertOk()->assertJsonPath('dapat_dibatalkan', true);
        $this->getJson("/barang_canvassing/{$diterima}/item")->assertOk()->assertJsonPath('dapat_dibatalkan', false);

        $tanpaHapus = $this->akunDenganHak('Tanpa Hapus', 'semua', ['lihat', 'ubah']);
        $this->actingAs($tanpaHapus)->getJson("/barang_canvassing/{$this->muatan}/item")->assertOk()->assertJsonPath('dapat_dibatalkan', false);
    }
}
