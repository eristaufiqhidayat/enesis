<?php

namespace Tests\Feature\Admin;

use App\Support\Akses\HierarkiSales;
use Illuminate\Support\Facades\DB;

class KoordinatorSalesTest extends AdminTestCase
{
    public function test_tambah_pindah_dan_lepas_sales_dari_koordinator(): void
    {
        $koor = $this->akun('koor.satu', 'koordinator', 137);
        DB::table('mk_karyawan')->insert(['id' => 138, 'kategori' => 'koordinator', 'nama' => 'Koor Dua', 'depo_id' => 22, 'aktif' => true]);
        $koor2 = $this->akun('koor.dua', 'koordinator', 138);
        $sinta = $this->akun('sinta', 'sales', 2);
        $rudi = $this->akun('rudi', 'sales', 1);

        // Tombol & modal tampil di detail dan edit.
        $this->get('/user_koordinator/'.$koor2->id)->assertOk()->assertSee('Tambah sales')->assertSee('modal-tambah-sales', false);
        $this->get('/user_koordinator/'.$koor2->id.'/edit')->assertOk()->assertSee('Tambah sales');

        // Cari: rudi masih di bawah Koor Satu, akan dipindah.
        $this->getJson('/user_koordinator/'.$koor2->id.'/sales/cari?q=rudi')->assertOk()
            ->assertJsonFragment(['username' => 'rudi', 'koordinator' => 'Koor Satu', 'sudah' => false]);

        // Tambah sales tanpa koordinator.
        $this->post('/user_koordinator/'.$koor2->id.'/sales', ['sales_id' => $sinta->id])->assertRedirect()->assertSessionHas('sukses');
        $this->assertSame(138, (int) DB::table('mk_karyawan')->where('id', 2)->value('koordinator_id'));

        // Pindah dari koordinator lain.
        $this->post('/user_koordinator/'.$koor2->id.'/sales', ['sales_id' => $rudi->id])->assertRedirect();
        $this->assertSame(138, (int) DB::table('mk_karyawan')->where('id', 1)->value('koordinator_id'));
        $this->assertDatabaseHas('riwayat_admin', ['objek_id' => $rudi->id, 'keterangan' => 'Koordinator: Koor Satu → Koor Dua']);
        $this->get('/user_koordinator/'.$koor2->id)->assertSee('2 aktif dari 2 akun');
        $this->get('/user_koordinator/'.$koor->id)->assertDontSee('rudi</a>', false);

        // Lepas.
        $this->delete('/user_koordinator/'.$koor2->id.'/sales/'.$sinta->id)->assertRedirect();
        $this->assertNull(DB::table('mk_karyawan')->where('id', 2)->value('koordinator_id'));

        // Lepas sales yang bukan bawahannya: 404.
        $this->delete('/user_koordinator/'.$koor->id.'/sales/'.$sinta->id)->assertNotFound();

        // Bukan akun sales: ditolak.
        $this->post('/user_koordinator/'.$koor2->id.'/sales', ['sales_id' => $koor->id])->assertSessionHasErrors('sales_id');
    }

    public function test_pengaturan_enesis_bertahan_setelah_tarik_ulang(): void
    {
        $koor = $this->akun('koor.satu', 'koordinator', 137);
        $sinta = $this->akun('sinta', 'sales', 2);

        $this->post('/user_koordinator/'.$koor->id.'/sales', ['sales_id' => $sinta->id]);

        // marketama:tarik menimpa koordinator_id dari aplikasi lama...
        DB::table('mk_karyawan')->where('id', 2)->update(['koordinator_id' => null]);
        // ...lalu pengaturan Enesis dipasang ulang.
        HierarkiSales::terapkanUlang();

        $this->assertSame(137, (int) DB::table('mk_karyawan')->where('id', 2)->value('koordinator_id'));
    }

    public function test_tanpa_hak_ubah_tidak_bisa_mengatur_sales(): void
    {
        $koor = $this->akun('koor.satu', 'koordinator', 137);
        $sinta = $this->akun('sinta', 'sales', 2);
        $lihat = $this->akun('pengamat', 'lainnya', 500, [
            'role_id' => $this->role('Pengamat', 'semua', ['user_koordinator' => ['lihat'], 'user_sales' => ['lihat']]),
        ]);

        $this->actingAs($lihat);
        $this->get('/user_koordinator/'.$koor->id)->assertOk()->assertDontSee('Tambah sales');
        $this->post('/user_koordinator/'.$koor->id.'/sales', ['sales_id' => $sinta->id])->assertForbidden();
        $this->assertNull(DB::table('mk_karyawan')->where('id', 2)->value('koordinator_id'));
    }
}
