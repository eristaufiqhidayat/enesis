<?php

namespace Tests\Feature\Admin;

use Database\Seeders\MasterProdukSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

// CRUD master produk (Produk, Produk Kompetitor, Kategori, Brand, Satuan) + seeder data Marketama + API HP.
class MasterProdukTest extends AdminTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MasterProdukSeeder::class);
    }

    public function test_seeder_mengisi_master_dengan_id_asli_marketama(): void
    {
        $this->assertSame([1, 2, 3, 4, 6], DB::table('mk_satuan_produk')->orderBy('id')->pluck('id')->all());
        $this->assertSame(2, DB::table('mk_kategori_produk')->count());
        $this->assertSame([1, 2, 3, 4, 5, 6, 39], DB::table('mk_merk_produk')->orderBy('id')->pluck('id')->all());
        $this->assertFalse((bool) DB::table('mk_merk_produk')->where('kode', 'BRD004')->value('aktif'));
        $this->assertSame('Cap Badak', DB::table('mk_produk_kompetitor')->where('id', 2)->value('nama'));
        $this->assertSame(21, DB::table('mk_produk')->count());

        $guava = DB::table('mk_produk')->find(20);
        $this->assertSame('1000628', $guava->kode);
        $this->assertSame([2, 2, 2], [(int) $guava->merk_id, (int) $guava->kategori_id, (int) $guava->satuan_id]);
        $this->assertTrue((bool) $guava->produk_fokus);
        $this->assertSame(5750.0, (float) $guava->harga_jual);
        $this->assertStringStartsWith('/produk/photo/20_', $guava->foto_lama);

        // Diulang: perubahan admin sesudah tanggal data tidak tertimpa.
        DB::table('mk_produk')->where('id', 20)->update(['nama' => 'Diubah Admin', 'diubah_pada' => now()]);
        $this->seed(MasterProdukSeeder::class);
        $this->assertSame('Diubah Admin', DB::table('mk_produk')->where('id', 20)->value('nama'));
        $this->assertSame(21, DB::table('mk_produk')->count());
    }

    public function test_daftar_dan_pencarian_tiap_menu(): void
    {
        $this->get('/satuan_produk')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('MasterProduk/Index')->where('total', 5)
            ->where('baris.data', fn ($b) => collect($b)->firstWhere('nama', 'Pcs')['total_produk'] === 10
                && collect($b)->firstWhere('nama', 'Karton')['total_produk'] === 0));

        $this->get('/merk_produk')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('baris.data', fn ($b) => collect($b)->firstWhere('kode', 'BRD004')['aktif'] === 'Tidak aktif'));

        $this->get('/produk?q=kispray')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('MasterProduk/Index')->where('q', 'kispray')
            ->where('baris.total', 5)
            ->where('baris.data.0.merk', 'Kispray'));

        $this->get('/produk_kompetitor')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('baris.data.0.nama', 'Cap Badak')->where('baris.data.0.satuan', 'Pcs'));
    }

    public function test_tambah_ubah_hapus_satuan_dan_riwayat(): void
    {
        $this->get('/satuan_produk/tambah')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('MasterProduk/Form')->where('item', null));

        // Tambah dari modal (JSON) tanpa item.
        $this->getJson('/satuan_produk/tambah')->assertOk()->assertJsonPath('item', null)->assertJsonStructure(['field', 'nilai', 'bisa']);

        $this->post('/satuan_produk', ['nama' => 'Pcs'])->assertSessionHasErrors('nama');
        $this->post('/satuan_produk', ['nama' => 'Renceng'])->assertRedirect('/satuan_produk');

        $baru = DB::table('mk_satuan_produk')->where('nama', 'Renceng')->first();
        $this->assertSame(7, (int) $baru->id); // melanjutkan ID asli terbesar (6)
        $this->assertSame('admin', $baru->diubah_oleh);

        $this->from('/satuan_produk/7/edit')->put('/satuan_produk/7', ['nama' => 'Renteng'])->assertRedirect('/satuan_produk/7/edit');
        $this->assertSame('Renteng', DB::table('mk_satuan_produk')->where('id', 7)->value('nama'));
        $this->assertDatabaseHas('riwayat_admin', ['objek' => 'satuan_produk', 'objek_id' => 7, 'aksi' => 'diubah', 'keterangan' => 'Nama: Renceng → Renteng']);

        // Satuan Pcs dipakai produk & produk kompetitor: ditolak.
        $this->delete('/satuan_produk/2')->assertSessionHasErrors('hapus');
        $this->assertDatabaseHas('mk_satuan_produk', ['id' => 2]);

        $this->delete('/satuan_produk/7')->assertRedirect('/satuan_produk');
        $this->assertDatabaseMissing('mk_satuan_produk', ['id' => 7]);
    }

    public function test_tambah_produk_dengan_foto_dan_validasi_merk_tidak_aktif(): void
    {
        Storage::fake('local');
        $dasar = [
            'kode' => 'BARU-001', 'nama' => 'Produk Baru 250 ml', 'packsize' => '250 ml', 'kategori_id' => '2', 'satuan_id' => '2',
            'produk_fokus' => '1', 'drop_size' => '2', 'maks_poin_per_invoice' => '3', 'harga_beli' => '4.500', 'harga_jual' => '5.000',
        ];

        // Merk Plossa (4) tidak aktif: tidak boleh dipilih untuk produk baru; kode kembar ditolak.
        $this->post('/produk', $dasar + ['merk_id' => '4'])->assertSessionHasErrors('merk_id');
        $this->post('/produk', ['kode' => '1000628', 'merk_id' => '2'] + $dasar)->assertSessionHasErrors('kode');

        $this->post('/produk', $dasar + ['merk_id' => '2', 'foto' => UploadedFile::fake()->image('produk.jpg', 300, 300)])
            ->assertRedirect('/produk');

        $p = DB::table('mk_produk')->where('kode', 'BARU-001')->first();
        $this->assertSame(54, (int) $p->id);
        $this->assertSame(4500.0, (float) $p->harga_beli);
        $this->assertTrue((bool) $p->produk_fokus);
        Storage::disk('local')->assertExists($p->foto);
        $this->get('/produk/54/foto')->assertOk();

        // Produk lama ber-merk Plossa tetap bisa disimpan dengan merk itu.
        $this->get('/produk/17/edit')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('field', fn ($f) => collect(collect($f)->firstWhere('nama', 'merk_id')['opsi'])->contains('value', '4')));
        // Edit dari modal (JSON) dan simpan kembali ke halaman daftar.
        $this->getJson('/produk/17/edit')->assertOk()->assertJsonPath('item.id', 17)->assertJsonStructure(['field', 'nilai', 'bisa']);
        $this->from('/produk')->put('/produk/17', ['kode' => 'plosasctr', 'nama' => 'Plossa Sachet Citrus', 'merk_id' => '4', 'kategori_id' => '1', 'satuan_id' => '2',
            'drop_size' => '1', 'harga_beli' => '3000', 'harga_jual' => '3500'])->assertRedirect('/produk');
        $this->assertDatabaseHas('riwayat_admin', ['objek' => 'produk', 'objek_id' => 17, 'keterangan' => 'Harga jual: 3000 → 3500']);
    }

    public function test_hapus_produk_ditolak_bila_sudah_dipakai_transaksi(): void
    {
        DB::table('trx_canvassing_item')->insert(['canvassing_id' => 1, 'produk_id' => 20, 'qty_bawa' => 12]);

        $this->get('/produk/20/edit')->assertInertia(fn (Assert $page) => $page->where('dipakai', ['1 item canvassing']));
        $this->delete('/produk/20')->assertSessionHasErrors('hapus');
        $this->assertDatabaseHas('mk_produk', ['id' => 20]);

        $this->delete('/produk/19')->assertRedirect('/produk');
        $this->assertDatabaseMissing('mk_produk', ['id' => 19]);
    }

    public function test_hak_akses_per_aksi(): void
    {
        $role = $this->role('Staf Produk', 'semua', ['produk' => ['lihat']]);
        $this->actingAs($this->akun('staf', 'lainnya', 500, ['role_id' => $role]));

        $this->get('/produk')->assertOk()->assertInertia(fn (Assert $page) => $page->where('bisa.tambah', false)->where('bisa.ubah', false));
        $this->get('/produk/20/edit')->assertOk();
        $this->get('/produk/tambah')->assertForbidden();
        $this->put('/produk/20', ['nama' => 'X'])->assertForbidden();
        $this->delete('/produk/20')->assertForbidden();
        $this->get('/produk/ekspor')->assertForbidden();
        $this->get('/satuan_produk')->assertForbidden();
    }

    public function test_ekspor_csv(): void
    {
        $csv = $this->get('/kategori_produk/ekspor')->assertOk()->streamedContent();

        $this->assertStringContainsString('ID,Kode,Nama,"Total Produk",Diubah', $csv);
        $this->assertStringContainsString('1,CAT001,"Personal and Home Care",16,', $csv);
    }

    public function test_api_hp_membaca_master_produk(): void
    {
        DB::table('mk_merk_produk')->where('id', 2)->update(['nama' => 'Adem Sari Baru', 'diubah_pada' => now()->addMinute()]);
        DB::table('mk_produk')->where('id', 20)->update(['produk_fokus' => false]);

        $user = $this->akun('andi', 'sales', 1);
        DB::table('users')->where('id', $user->id)->update(['wajib_ganti_password' => false]);
        DB::table('mobile_tokens')->insert(['user_id' => $user->id, 'token_hash' => hash('sha256', 'enm_andi'), 'device_id' => 'HP-A', 'last_used_at' => now()]);
        $api = fn (string $uri) => $this->withHeaders(['Authorization' => 'Bearer enm_andi', 'X-App-Version' => '9.9.9'])->getJson('/api/mobile/v1/'.$uri);

        $data = $api('master')->assertOk()->json('data');
        $guava = collect($data['products'])->firstWhere('id', 20);
        $this->assertSame(['Adem Sari Baru', 'Nutraceutical and Functional Drinks', 'Pcs', false], [$guava['brand'], $guava['category'], $guava['unit'], $guava['is_focus']]);
        $this->assertSame(['Cap Badak', 'Cap Kaki Tiga'], collect($data['competitor_products'])->pluck('name')->all());
        $this->assertSame(2, collect($data['competitor_products'])->firstWhere('name', 'Cap Badak')['id']);

        // Sinkron sebagian: produk ikut terkirim bila merk-nya berubah sesudah "since".
        $sejak = rawurlencode(now()->toIso8601String());
        $ids = collect($api('master?since='.$sejak)->assertOk()->json('data.products'))->pluck('id')->sort()->values()->all();
        $this->assertSame([3, 20], $ids); // hanya produk ber-merk Adem Sari (id 2)
    }
}
