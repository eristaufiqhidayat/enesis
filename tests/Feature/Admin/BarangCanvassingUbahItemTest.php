<?php

namespace Tests\Feature\Admin;

use Illuminate\Support\Facades\DB;

// Ubah item muatan canvassing yang masih Disiapkan (PUT /barang_canvassing/{id}/item) dan penanda dapat_diubah.
class BarangCanvassingUbahItemTest extends AdminTestCase
{
    private int $muatan;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('mk_produk')->insert([
            ['id' => 3, 'kode' => 'SKU-3', 'nama' => 'Adem Sari'],
            ['id' => 4, 'kode' => 'SKU-4', 'nama' => 'Plossa'],
            ['id' => 5, 'kode' => 'SKU-5', 'nama' => 'Larutan'],
        ]);

        $this->muatan = $this->buatMuatan();
    }

    /** Muatan untuk motoris 1 (depo 46) dengan item produk 3 (qty 10) dan 4 (qty 5). */
    private function buatMuatan(array $header = [], int $karyawan = 1, string $nomor = 'CNV-1'): int
    {
        $id = DB::table('trx_canvassing')->insertGetId($header + [
            'nomor' => $nomor, 'karyawan_id' => $karyawan, 'depo_id' => 46, 'tanggal' => '2026-10-07',
            'status' => 'disiapkan', 'disiapkan_at' => '2026-10-07 01:00:00', 'sumber' => 'web',
        ]);
        DB::table('trx_canvassing_item')->insert([
            ['canvassing_id' => $id, 'produk_id' => 3, 'qty_bawa' => 10],
            ['canvassing_id' => $id, 'produk_id' => 4, 'qty_bawa' => 5],
        ]);

        return $id;
    }

    private function items(int $id): array
    {
        return DB::table('trx_canvassing_item')->where('canvassing_id', $id)->orderBy('produk_id')
            ->get(['produk_id', 'qty_bawa'])->mapWithKeys(fn ($i) => [(int) $i->produk_id => (float) $i->qty_bawa])->all();
    }

    private function ubah(array $items, ?int $id = null)
    {
        return $this->putJson('/barang_canvassing/'.($id ?? $this->muatan).'/item', ['items' => $items]);
    }

    private function akunDenganHak(string $nama, string $cakupan, array $aksi, ?int $karyawan = 500)
    {
        $akun = $this->akun(str($nama)->slug('.'), 'lainnya', $karyawan);
        DB::table('users')->where('id', $akun->id)->update(['role_id' => $this->role($nama, $cakupan, ['barang_canvassing' => $aksi])]);

        return $akun->fresh();
    }

    public function test_ubah_qty_hapus_dan_tambah_produk(): void
    {
        $idProduk3 = DB::table('trx_canvassing_item')->where('canvassing_id', $this->muatan)->where('produk_id', 3)->value('id');

        $this->ubah([['produk_id' => 3, 'qty_bawa' => 12.5], ['produk_id' => 5, 'qty_bawa' => 2]])
            ->assertOk()->assertJsonPath('ok', true);

        $this->assertSame([3 => 12.5, 5 => 2.0], $this->items($this->muatan)); // produk 4 dihapus, produk 5 ditambah
        // item yang tetap ada diubah di tempat (bukan dibuat ulang), header tidak tersentuh
        $this->assertSame($idProduk3, DB::table('trx_canvassing_item')->where('canvassing_id', $this->muatan)->where('produk_id', 3)->value('id'));
        $header = DB::table('trx_canvassing')->where('id', $this->muatan)->first();
        $this->assertSame(['CNV-1', 'disiapkan', 1, 46], [$header->nomor, $header->status, (int) $header->karyawan_id, (int) $header->depo_id]);
        $this->assertNull($header->diterima_at);
    }

    public function test_validasi_menolak_data_tidak_valid_dan_tidak_mengubah_item(): void
    {
        $kasus = [
            'daftar kosong' => [],
            'produk dobel' => [['produk_id' => 3, 'qty_bawa' => 1], ['produk_id' => 3, 'qty_bawa' => 2]],
            'produk tidak ada' => [['produk_id' => 999, 'qty_bawa' => 1]],
            'qty nol' => [['produk_id' => 3, 'qty_bawa' => 0]],
            'qty negatif' => [['produk_id' => 3, 'qty_bawa' => -1]],
            'qty bukan angka' => [['produk_id' => 3, 'qty_bawa' => 'banyak']],
        ];

        foreach ($kasus as $nama => $items) {
            $this->ubah($items)->assertStatus(422);
        }

        $this->assertSame([3 => 10.0, 4 => 5.0], $this->items($this->muatan));
    }

    public function test_muatan_yang_sudah_diterima_ditolak_409_dan_itemnya_tetap(): void
    {
        foreach (['diterima', 'dikembalikan', 'selesai'] as $i => $status) {
            $id = $this->buatMuatan(['status' => $status, 'diterima_at' => '2026-10-07 02:00:00'], 3, "CNV-S$i");

            $this->ubah([['produk_id' => 5, 'qty_bawa' => 99]], $id)->assertStatus(409);

            $this->assertSame([3 => 10.0, 4 => 5.0], $this->items($id), $status);
        }
    }

    public function test_status_disiapkan_tetapi_sudah_ada_waktu_terima_juga_ditolak(): void
    {
        DB::table('trx_canvassing')->where('id', $this->muatan)->update(['diterima_at' => '2026-10-07 02:00:00']);

        $this->ubah([['produk_id' => 5, 'qty_bawa' => 1]])->assertStatus(409);
    }

    public function test_muatan_tidak_ada_atau_di_luar_aturan_koordinator_motoris_404(): void
    {
        $this->ubah([['produk_id' => 3, 'qty_bawa' => 1]], 999999)->assertNotFound();

        // diterima staf gudang (tanpa koordinator): bukan muatan Koordinator -> Motoris
        $gudang = $this->buatMuatan([], 500, 'CNV-G');
        $this->ubah([['produk_id' => 3, 'qty_bawa' => 1]], $gudang)->assertNotFound();
    }

    public function test_tanpa_hak_ubah_mendapat_403(): void
    {
        $akun = $this->akunDenganHak('Tambah Saja', 'semua', ['lihat', 'tambah']);

        $this->actingAs($akun)->ubah([['produk_id' => 5, 'qty_bawa' => 1]])->assertForbidden();

        $this->assertSame([3 => 10.0, 4 => 5.0], $this->items($this->muatan));
    }

    public function test_cakupan_depo_tidak_bisa_mengubah_muatan_depo_lain(): void
    {
        $balaraja = $this->akunDenganHak('Admin Balaraja', 'depo', ['lihat', 'ubah'], 2);
        $this->actingAs($balaraja)->ubah([['produk_id' => 5, 'qty_bawa' => 1]])->assertNotFound();
        $this->assertSame([3 => 10.0, 4 => 5.0], $this->items($this->muatan));

        $sidoarjo = $this->akunDenganHak('Admin Sidoarjo', 'depo', ['lihat', 'ubah'], 500);
        $this->actingAs($sidoarjo)->ubah([['produk_id' => 5, 'qty_bawa' => 1]])->assertOk();
        $this->assertSame([5 => 1.0], $this->items($this->muatan));
    }

    public function test_perubahan_tercatat_di_riwayat_admin(): void
    {
        $this->ubah([['produk_id' => 3, 'qty_bawa' => 1]])->assertOk();

        $riwayat = DB::table('riwayat_admin')->where('objek', 'barang_canvassing')->where('aksi', 'ubah')->first();
        $this->assertNotNull($riwayat);
        $this->assertStringContainsString('CNV-1', (string) $riwayat->keterangan);
    }

    public function test_respons_item_memuat_dapat_diubah_sesuai_status_dan_hak(): void
    {
        $diterima = $this->buatMuatan(['status' => 'diterima', 'diterima_at' => '2026-10-07 02:00:00'], 3, 'CNV-D');

        $this->getJson("/barang_canvassing/{$this->muatan}/item")->assertOk()->assertJsonPath('dapat_diubah', true);
        $this->getJson("/barang_canvassing/{$diterima}/item")->assertOk()->assertJsonPath('dapat_diubah', false);

        $lihatSaja = $this->akunDenganHak('Lihat Saja', 'semua', ['lihat']);
        $this->actingAs($lihatSaja)->getJson("/barang_canvassing/{$this->muatan}/item")->assertOk()->assertJsonPath('dapat_diubah', false);
    }
}
