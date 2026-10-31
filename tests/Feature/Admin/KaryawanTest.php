<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\KaryawanController;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

// CRUD karyawan (Sales, Koordinator, Supervisor, Lainnya) di mk_karyawan.
class KaryawanTest extends AdminTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        DB::table('mk_area')->insert([
            ['id' => 70, 'nama' => 'Sidoarjo-01', 'depo_id' => 46], ['id' => 71, 'nama' => 'Sidoarjo-02', 'depo_id' => 46],
            ['id' => 72, 'nama' => 'Sidoarjo-03', 'depo_id' => 46], ['id' => 90, 'nama' => 'Balaraja-01', 'depo_id' => 22],
        ]);
        DB::table('mk_karyawan_area')->insert([['karyawan_id' => 1, 'area_id' => 70, 'aktif' => true], ['karyawan_id' => 1, 'area_id' => 71, 'aktif' => false]]);
        DB::table('mk_wilayah_depo')->insert(['id' => 5, 'nama' => 'Sidoarjo Kota', 'depo_id' => 46]);
        DB::table('mk_karyawan')->insert([
            ['id' => 600, 'kategori' => 'lainnya', 'nama' => 'Rinto', 'depo_id' => null, 'koordinator_id' => null, 'aktif' => true, 'jabatan' => 'Auditor'],
            ['id' => 700, 'kategori' => 'supervisor', 'nama' => 'Carnita', 'depo_id' => null, 'koordinator_id' => null, 'aktif' => true, 'jabatan' => null],
        ]);
    }

    private function dataSales(array $ubah = []): array
    {
        return $ubah + [
            'nama' => 'Bayu Saputra', 'depo_id' => '46', 'koordinator_id' => '137', 'area_ids' => ['71', '72'], 'area_aktif_ids' => ['72'],
            'no_hp' => '0812 3456 7890', 'email' => 'bayu@contoh.id', 'tanggal_mulai' => '2026-10-01', 'aktif' => '1',
            'gaji_pokok' => '3.500.000', 'tunjangan_bbm_makan' => '750000',
        ];
    }

    public function test_daftar_per_kategori_dengan_status_dan_cari(): void
    {
        $this->get('/karyawan_sales')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Karyawan/Index')->where('kategori', 'sales')
            ->where('jumlah', ['semua' => 4, 'aktif' => 3, 'tidak_aktif' => 1])
            ->where('baris.data.0.nama', 'Rudi Hartono')->where('baris.data.0.koordinator', 'Koor Satu')
            ->where('baris.data.0.area_aktif', 'Sidoarjo-01')->where('baris.data.0.total_area', 2));

        $this->get('/karyawan_sales?status=tidak_aktif')->assertInertia(fn (Assert $page) => $page->where('baris.total', 1)->where('baris.data.0.nama', 'Joko Lama'));
        $this->get('/karyawan_sales?q=sinta')->assertInertia(fn (Assert $page) => $page->where('baris.total', 1));
        $this->get('/karyawan_sales?depo=22')->assertInertia(fn (Assert $page) => $page->where('baris.total', 1)->where('baris.data.0.nama', 'Sinta Wulandari'));

        $this->get('/karyawan_koordinator')->assertInertia(fn (Assert $page) => $page
            ->where('baris.data.0.nama', 'Koor Satu')->where('baris.data.0.jumlah_sales', 2));
        $this->get('/karyawan_lainnya')->assertInertia(fn (Assert $page) => $page
            ->where('baris.total', 2)->where('baris.data', fn ($b) => collect($b)->contains('jabatan', 'Auditor')));
        $this->get('/karyawan_supervisor')->assertInertia(fn (Assert $page) => $page->where('baris.total', 1));
    }

    public function test_tambah_sales_dengan_area_koordinator_gaji_dan_foto(): void
    {
        Storage::fake('local');

        $this->getJson('/karyawan_sales/opsi?depo=46')->assertOk()
            ->assertJsonPath('koordinator.0.label', 'Koor Satu')->assertJsonCount(3, 'area');

        // Area depo lain & koordinator depo lain ditolak.
        $this->post('/karyawan_sales', $this->dataSales(['area_ids' => ['90']]))->assertSessionHasErrors('area_ids.0');
        $this->post('/karyawan_sales', $this->dataSales(['depo_id' => '22']))->assertSessionHasErrors('koordinator_id');

        $this->post('/karyawan_sales', $this->dataSales(['foto' => UploadedFile::fake()->image('bayu.jpg')]))
            ->assertRedirect('/karyawan_sales')->assertSessionHas('sukses');

        $id = KaryawanController::ID_AWAL;
        $k = DB::table('mk_karyawan')->find($id);
        $this->assertSame(['sales', 'Bayu Saputra', 46, 137, 72], [$k->kategori, $k->nama, (int) $k->depo_id, (int) $k->koordinator_id, (int) $k->area_aktif_id]);
        $this->assertSame([3500000.0, 750000.0], [(float) $k->gaji_pokok, (float) $k->tunjangan_bbm_makan]);
        $this->assertSame('2026-10-01', $k->tanggal_mulai);
        $this->assertTrue((bool) $k->aktif);
        Storage::disk('local')->assertExists($k->foto);
        $this->assertSame(137, (int) DB::table('hierarki_sales')->where('karyawan_id', $id)->value('koordinator_id'));
        $this->assertSame([71 => false, 72 => true], DB::table('mk_karyawan_area')->where('karyawan_id', $id)->orderBy('area_id')
            ->pluck('aktif', 'area_id')->map(fn ($v) => (bool) $v)->all());
        $this->get("/karyawan_sales/{$id}/foto")->assertOk();
    }

    public function test_ubah_sales_mencatat_riwayat_dan_tidak_ditimpa_tarikan(): void
    {
        $this->getJson('/karyawan_sales/1/edit')->assertOk()
            ->assertJsonPath('nilai.area_ids', ['70', '71'])->assertJsonPath('nilai.area_aktif_ids', ['70'])
            ->assertJsonPath('nilai.koordinator_id', '137');

        $this->put('/karyawan_sales/1', $this->dataSales(['nama' => 'Rudi Hartono', 'area_ids' => ['70', '71', '72'], 'area_aktif_ids' => ['70', '72']]))
            ->assertRedirect('/karyawan_sales');

        $this->assertSame(3, DB::table('mk_karyawan_area')->where('karyawan_id', 1)->count());
        $this->assertDatabaseHas('riwayat_admin', ['objek' => 'karyawan', 'objek_id' => 1, 'keterangan' => 'No HP: - → 0812 3456 7890']);
        $this->assertDatabaseHas('riwayat_admin', ['objek' => 'karyawan', 'objek_id' => 1, 'keterangan' => 'Area: 2 → 3 area']);
        $this->assertNotNull(DB::table('mk_karyawan')->where('id', 1)->value('diubah_pada'));
    }

    public function test_koordinator_wilayah_depo_dan_lainnya_jabatan(): void
    {
        $this->put('/karyawan_koordinator/137', ['nama' => 'Koor Satu', 'depo_id' => '46', 'wilayah_depo_id' => '5', 'no_hp' => '0811',
            'tanggal_mulai' => '2026-01-05', 'aktif' => '1'])->assertSessionHasErrors('no_hp');
        $this->put('/karyawan_koordinator/137', ['nama' => 'Koor Satu', 'depo_id' => '46', 'wilayah_depo_id' => '5', 'no_hp' => '081100',
            'tanggal_mulai' => '2026-01-05', 'aktif' => '1'])->assertRedirect('/karyawan_koordinator');
        $this->assertSame(5, (int) DB::table('mk_karyawan')->where('id', 137)->value('wilayah_depo_id'));

        // Pilihan kategori dari tabel Kategori Karyawan (kolom "kategori"), tanpa kategori bawaan sales/koordinator.
        DB::table('kategori_karyawan')->insert([['source_id' => 1, 'kategori' => 'Koordinator Default'], ['source_id' => 3, 'kategori' => 'Supervisor Nasional Default'],
            ['source_id' => 38, 'kategori' => 'Auditor'], ['source_id' => 40, 'kategori' => 'Merchandiser']]);
        $this->getJson('/karyawan_lainnya/tambah')->assertOk()
            ->assertJsonPath('opsi.jabatan', collect(['Auditor', 'Merchandiser', 'Principal', 'Supervisor'])->map(fn ($j) => ['value' => $j, 'label' => $j])->all());

        $this->post('/karyawan_lainnya', ['nama' => 'Agung', 'jabatan' => 'Direktur', 'aktif' => '1'])->assertSessionHasErrors('jabatan');
        $this->post('/karyawan_lainnya', ['nama' => 'Agung', 'jabatan' => 'Principal', 'aktif' => '1'])->assertRedirect('/karyawan_lainnya');
        $this->assertDatabaseHas('mk_karyawan', ['nama' => 'Agung', 'kategori' => 'lainnya', 'jabatan' => 'Principal']);

        // Menu lain tidak bisa membuka karyawan kategori berbeda.
        $this->get('/karyawan_sales/137/edit')->assertNotFound();
    }

    public function test_hapus_ditolak_bila_dipakai_dan_pindah_area(): void
    {
        $this->akun('rudi.h', 'sales', 1);
        $this->delete('/karyawan_sales/1')->assertSessionHasErrors('hapus');
        $this->delete('/karyawan_koordinator/137')->assertSessionHasErrors('hapus');

        // Pindah area Rudi (2 area) ke Tegar, digabung.
        DB::table('mk_karyawan_area')->insert(['karyawan_id' => 3, 'area_id' => 72, 'aktif' => true]);
        $this->post('/karyawan_sales/1/pindah-area', ['tujuan_id' => 2, 'mode' => 'gabung'])->assertSessionHasErrors('tujuan_id'); // beda depo
        $this->post('/karyawan_sales/1/pindah-area', ['tujuan_id' => 3, 'mode' => 'gabung'])->assertRedirect('/karyawan_sales');
        $this->assertSame([70, 71, 72], DB::table('mk_karyawan_area')->where('karyawan_id', 3)->orderBy('area_id')->pluck('area_id')->map(fn ($v) => (int) $v)->all());
        $this->assertSame(0, DB::table('mk_karyawan_area')->where('karyawan_id', 1)->count());

        // Sales tanpa pemakaian bisa dihapus.
        $this->delete('/karyawan_sales/4')->assertRedirect('/karyawan_sales');
        $this->assertDatabaseMissing('mk_karyawan', ['id' => 4]);
    }

    public function test_hak_akses_dan_cakupan_depo(): void
    {
        $role = $this->role('Admin Depo Balaraja', 'depo', ['karyawan_sales' => ['lihat']]);
        $this->actingAs($this->akun('staf.bal', 'lainnya', 500, ['role_id' => $role]));
        DB::table('mk_karyawan')->where('id', 500)->update(['depo_id' => 22]);

        $this->get('/karyawan_sales')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('baris.total', 1)->where('baris.data.0.nama', 'Sinta Wulandari')->where('bisa.tambah', false));
        $this->get('/karyawan_sales/1/edit')->assertNotFound(); // depo lain
        $this->post('/karyawan_sales', $this->dataSales())->assertForbidden();
        $this->get('/karyawan_koordinator')->assertForbidden();
    }

    public function test_ekspor_csv(): void
    {
        $csv = $this->get('/karyawan_lainnya/ekspor')->assertOk()->streamedContent();

        $this->assertStringContainsString('ID,"Nama Lengkap",Kategori,"No HP",Email,Status,Diubah,"Tanggal Mulai"', $csv);
        $this->assertStringContainsString('600,Rinto,Auditor', $csv);
    }

    public function test_tambah_sales_menyimpan_data_ktp_dan_menolak_nik_tidak_valid(): void
    {
        $this->post('/karyawan_sales', $this->dataSales([
            'nik' => '3273010101990001', 'tempat_lahir' => 'Bandung', 'tanggal_lahir' => '1999-01-01', 'alamat' => 'Jl Merdeka No 12',
        ]))->assertRedirect('/karyawan_sales');

        $id = KaryawanController::ID_AWAL;
        $this->assertDatabaseHas('mk_karyawan', ['id' => $id, 'nik' => '3273010101990001', 'tempat_lahir' => 'Bandung', 'alamat' => 'Jl Merdeka No 12']);
        $this->assertSame('1999-01-01', DB::table('mk_karyawan')->where('id', $id)->value('tanggal_lahir'));

        // NIK yang sama tidak boleh dipakai karyawan lain; NIK harus pas 16 digit.
        $this->post('/karyawan_sales', $this->dataSales(['nama' => 'Karyawan Lain', 'nik' => '3273010101990001']))->assertSessionHasErrors('nik');
        $this->post('/karyawan_sales', $this->dataSales(['nama' => 'Karyawan Lain', 'nik' => '12345']))->assertSessionHasErrors('nik');

        // NIK boleh dikosongkan (data lama belum punya NIK).
        $this->post('/karyawan_sales', $this->dataSales(['nama' => 'Karyawan Lain']))->assertRedirect('/karyawan_sales');
    }

    public function test_baca_ktp_mengisi_nik_nama_tanggal_lahir_dan_alamat_dari_foto(): void
    {
        $tesseract = (bool) trim((string) shell_exec('command -v tesseract 2>/dev/null'));
        $font = '/usr/share/fonts/truetype/dejavu/DejaVuSansMono-Bold.ttf';

        if (! $tesseract || ! is_file($font)) {
            $this->markTestSkipped('Tesseract atau font uji coba tidak tersedia di lingkungan ini.');
        }

        $path = $this->buatFotoKtpUjiCoba($font);

        try {
            $respons = $this->post('/karyawan_sales/baca-ktp', [
                'foto_ktp' => new UploadedFile($path, 'ktp.png', 'image/png', null, true),
            ])->assertOk();

            $this->assertSame('3273010101990001', $respons->json('nik'));
            $this->assertStringContainsStringIgnoringCase('BUDI SANTOSO', (string) $respons->json('nama'));
            $this->assertSame('1999-01-01', $respons->json('tanggal_lahir'));
            $this->assertNotEmpty($respons->json('teks_mentah'));
        } finally {
            @unlink($path);
        }
    }

    public function test_baca_ktp_menolak_file_bukan_gambar(): void
    {
        $this->postJson('/karyawan_sales/baca-ktp', ['foto_ktp' => UploadedFile::fake()->create('ktp.pdf', 10)])
            ->assertStatus(422)->assertJsonValidationErrors('foto_ktp');
    }

    /** Gambar putih berisi teks label KTP (font mono besar) -- cukup jelas untuk dibaca Tesseract. */
    private function buatFotoKtpUjiCoba(string $font): string
    {
        $gambar = imagecreatetruecolor(1500, 900);
        $putih = imagecolorallocate($gambar, 255, 255, 255);
        $hitam = imagecolorallocate($gambar, 0, 0, 0);
        imagefilledrectangle($gambar, 0, 0, 1500, 900, $putih);

        foreach ([
            'NIK : 3273010101990001',
            'Nama : BUDI SANTOSO',
            'Tempat/Tgl Lahir : BANDUNG, 01-01-1999',
            'Alamat : JL MERDEKA NO 12',
        ] as $i => $baris) {
            imagettftext($gambar, 28, 0, 40, 100 + $i * 70, $hitam, $font, $baris);
        }

        $path = tempnam(sys_get_temp_dir(), 'ktp').'.png';
        imagepng($gambar, $path);
        imagedestroy($gambar);

        return $path;
    }
}
