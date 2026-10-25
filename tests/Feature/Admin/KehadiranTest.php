<?php

namespace Tests\Feature\Admin;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// Menu Absensi Sales & Checkin Checkout Depo membaca tabel motoris (trx_*).
class KehadiranTest extends AdminTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['mobile.zona_waktu' => 'Asia/Jakarta']);
        $this->travelTo(now()->setTimezone('Asia/Jakarta')->setDate(2026, 10, 24)->setTime(12, 0));

        DB::table('trx_absensi')->insert([
            // Rudi (karyawan 1, koor 137) dari motoris, terlambat, dengan selfie.
            ['karyawan_id' => 1, 'depo_id' => 46, 'tanggal' => '2026-10-24', 'masuk_at' => '2026-10-24 01:20:00', 'pulang_at' => null,
                'terlambat' => true, 'masuk_lat' => -7.45, 'masuk_lng' => 112.71, 'masuk_foto_id' => null, 'sumber' => 'motoris', 'sumber_id' => null],
            // Sinta (karyawan 2, tanpa koor) riwayat aplikasi lama.
            ['karyawan_id' => 2, 'depo_id' => 22, 'tanggal' => '2026-10-20', 'masuk_at' => '2026-10-20 00:30:00', 'pulang_at' => '2026-10-20 10:00:00',
                'terlambat' => false, 'masuk_lat' => null, 'masuk_lng' => null, 'masuk_foto_id' => null, 'sumber' => 'lama', 'sumber_id' => 9001],
            // Di luar rentang bawaan (7 hari terakhir).
            ['karyawan_id' => 3, 'depo_id' => 46, 'tanggal' => '2026-09-01', 'masuk_at' => '2026-09-01 00:30:00', 'pulang_at' => null,
                'terlambat' => false, 'masuk_lat' => null, 'masuk_lng' => null, 'masuk_foto_id' => null, 'sumber' => 'lama', 'sumber_id' => 9002],
        ]);

        DB::table('trx_checkin_depo')->insert([
            ['karyawan_id' => 1, 'depo_id' => 46, 'tanggal' => '2026-10-24', 'checkin_at' => '2026-10-24 00:10:00', 'checkout_at' => null,
                'lat' => -7.45, 'lng' => 112.71, 'jarak_m' => 35, 'sumber' => 'motoris', 'sumber_id' => null],
            ['karyawan_id' => 2, 'depo_id' => 22, 'tanggal' => '2026-10-21', 'checkin_at' => '2026-10-21 00:05:00', 'checkout_at' => '2026-10-21 09:00:00',
                'lat' => null, 'lng' => null, 'jarak_m' => null, 'sumber' => 'lama', 'sumber_id' => 8001],
        ]);
    }

    public function test_absensi_sales_dari_tabel_motoris(): void
    {
        $props = $this->inertiaProps($this->get('/absensi_sales')->assertOk(), 'Kehadiran/Absensi');
        $this->assertSame(['Rudi Hartono', 'Sinta Wulandari'], $this->sales($props)); // Tegar di luar 7 hari terakhir
        $this->assertSame(2, $props['ringkas']['total']);
        $this->assertSame(['08:20', '07:30'], array_column($props['baris']['data'], 'masuk'));
        $this->assertTrue($props['baris']['data'][0]['terlambat']);
        $this->assertSame('9 j 30 m', $props['baris']['data'][1]['durasi']);
        $this->assertSame(['motoris', 'lama'], array_column($props['baris']['data'], 'sumber'));

        // Rentang tanggal, sumber, nama, koordinator.
        $props = $this->inertiaProps($this->get('/absensi_sales?dari=2026-09-01&sampai=2026-09-30')->assertOk(), 'Kehadiran/Absensi');
        $this->assertSame(['Tegar Mahendra'], $this->sales($props));
        $this->assertSame(['Sinta Wulandari'], $this->sales($this->inertiaProps($this->get('/absensi_sales?sumber=lama'), 'Kehadiran/Absensi')));
        $this->assertSame(['Rudi Hartono'], $this->sales($this->inertiaProps($this->get('/absensi_sales?q=rudi'), 'Kehadiran/Absensi')));
        $this->assertSame(['Rudi Hartono'], $this->sales($this->inertiaProps($this->get('/absensi_sales?koordinator=137'), 'Kehadiran/Absensi')));

        // Menu terlambat = status terlambat.
        $props = $this->inertiaProps($this->get('/absensi_sales_terlambat')->assertOk(), 'Kehadiran/Absensi');
        $this->assertSame('terlambat', $props['status']);
        $this->assertSame(['Rudi Hartono'], $this->sales($props));
        $this->assertSame(['Rudi Hartono'], $this->sales($this->inertiaProps($this->get('/lap_absensi_sales?status=belum_pulang'), 'Kehadiran/Absensi')));

        // Tab "Semua" di menu terlambat tidak boleh kembali ke filter terlambat.
        $props = $this->inertiaProps($this->get('/absensi_sales_terlambat?status=semua')->assertOk(), 'Kehadiran/Absensi');
        $this->assertSame('semua', $props['status']);
        $this->assertSame(['Rudi Hartono', 'Sinta Wulandari'], $this->sales($props));
    }

    public function test_checkin_depo_dari_tabel_motoris(): void
    {
        $props = $this->inertiaProps($this->get('/checkin_depo')->assertOk(), 'Kehadiran/CheckinDepo');
        $this->assertSame(['Rudi Hartono', 'Sinta Wulandari'], $this->sales($props));
        $this->assertSame(['07:10', '07:05'], array_column($props['baris']['data'], 'masuk'));
        $this->assertSame(35, $props['baris']['data'][0]['jarak_m']);
        $this->assertSame('8 j 55 m', $props['baris']['data'][1]['durasi']);
        $this->assertSame(2, $props['ringkas']['total']);

        $this->assertSame(['Sinta Wulandari'], $this->sales($this->inertiaProps($this->get('/lap_checkin_checkout?depo=22'), 'Kehadiran/CheckinDepo')));
    }

    /** Nama sales per baris halaman, dalam urutan tampil. */
    private function sales(array $props): array
    {
        return array_column($props['baris']['data'], 'sales');
    }

    public function test_checkin_outlet_dari_tabel_motoris(): void
    {
        DB::table('mk_outlet')->insert([
            ['id' => 11, 'kode' => 'OT-11', 'nama' => 'Toko Makmur'],
            ['id' => 12, 'kode' => 'OT-12', 'nama' => 'Warung Bu Sri'],
        ]);
        $beli = DB::table('trx_kunjungan')->insertGetId(['karyawan_id' => 1, 'depo_id' => 46, 'outlet_id' => 11, 'tanggal' => '2026-10-24',
            'checkin_at' => '2026-10-24 02:00:00', 'checkout_at' => '2026-10-24 02:15:00', 'durasi_detik' => 900, 'lat' => -7.45, 'lng' => 112.71,
            'jarak_m' => 12, 'di_luar_rute' => true, 'sumber' => 'motoris']);
        DB::table('trx_penjualan')->insert(['kode' => 'J-1', 'kunjungan_id' => $beli, 'karyawan_id' => 1, 'depo_id' => 46, 'outlet_id' => 11,
            'tanggal' => '2026-10-24', 'jumlah_sku' => 2, 'total_qty' => 5, 'total' => 125000, 'total_diskon' => 0, 'is_effective_call' => true]);
        DB::table('trx_kunjungan')->insert([
            ['karyawan_id' => 1, 'depo_id' => 46, 'outlet_id' => 12, 'tanggal' => '2026-10-24', 'checkin_at' => '2026-10-24 03:00:00',
                'tidak_beli_kategori' => 'toko_tutup', 'tidak_beli_keterangan' => null, 'sumber' => 'motoris', 'sumber_id' => null, 'foto_url' => null, 'keterangan_lama' => null],
            ['karyawan_id' => 2, 'depo_id' => 22, 'outlet_id' => 12, 'tanggal' => '2026-10-22', 'checkin_at' => '2026-10-22 03:00:00',
                'tidak_beli_kategori' => null, 'tidak_beli_keterangan' => null, 'sumber' => 'lama', 'sumber_id' => 7001, 'foto_url' => 'https://app.lama/f.jpg', 'keterangan_lama' => 'Pemilik pergi'],
        ]);

        $baris = fn (string $url) => $this->inertiaProps($this->get($url)->assertOk(), 'Kehadiran/Kunjungan')['baris']['data'];
        $outlet = fn (string $url) => array_column($baris($url), 'outlet');

        $semua = collect($baris('/checkin_outlet'));
        $this->assertEqualsCanonicalizing(['Toko Makmur', 'Warung Bu Sri'], $semua->pluck('outlet')->unique()->all());
        $makmur = $semua->firstWhere('outlet', 'Toko Makmur');
        $this->assertEquals(125000, $makmur['total_jual']);
        $this->assertSame('15 m', $makmur['durasi']);
        $this->assertTrue($makmur['di_luar_rute']);
        $this->assertContains('Toko tutup', $semua->pluck('tidak_beli')->all());
        $this->assertContains('Pemilik pergi', $semua->pluck('keterangan_lama')->all());
        $this->assertContains('https://app.lama/f.jpg', $semua->pluck('foto_lama')->all());

        $this->assertSame(['Toko Makmur'], $outlet('/checkin_outlet?status=beli'));
        $this->assertNotContains('Toko Makmur', $outlet('/checkin_outlet?status=tidak_beli'));
        $this->assertSame(['Toko Makmur'], $outlet('/lap_checkin_outlet?outlet=makmur'));
        $this->assertSame(['Sinta Wulandari'], array_column($baris('/checkin_outlet?sumber=lama'), 'sales'));
    }

    public function test_prop_halaman_tidak_menimpa_menu_sidebar(): void
    {
        // Sidebar membaca prop bersama `menu` (array); prop halaman bernama sama membuat seluruh halaman kosong.
        foreach (['absensi_sales' => 'Kehadiran/Absensi', 'checkin_depo' => 'Kehadiran/CheckinDepo', 'checkin_outlet' => 'Kehadiran/Kunjungan'] as $url => $komponen) {
            $props = $this->inertiaProps($this->get('/'.$url)->assertOk(), $komponen);
            $this->assertIsArray($props['menu'], $url);
            $this->assertSame($url, $props['slug']);
        }
    }

    public function test_cakupan_role_dan_foto_selfie(): void
    {
        Storage::fake('local');
        config(['mobile.unggah_disk' => 'local']);
        $rudi = $this->akun('rudi', 'sales', 1);
        $fotoId = (string) Str::uuid();
        Storage::disk('local')->put('mobile/x/selfie.jpg', UploadedFile::fake()->image('s.jpg')->getContent());
        DB::table('mobile_uploads')->insert(['id' => $fotoId, 'user_id' => $rudi->id, 'jenis' => 'selfie', 'disk' => 'local',
            'path' => 'mobile/x/selfie.jpg', 'mime' => 'image/jpeg', 'ukuran' => 10, 'created_at' => now()]);
        DB::table('trx_absensi')->where('karyawan_id', 1)->update(['masuk_foto_id' => $fotoId]);

        $props = $this->inertiaProps($this->get('/absensi_sales')->assertOk(), 'Kehadiran/Absensi');
        $this->assertSame(route('kehadiran.foto', $fotoId), $props['baris']['data'][0]['foto']);
        $this->get('/foto/'.$fotoId)->assertOk()->assertHeader('Content-Type', 'image/jpeg');

        // Koordinator (cakupan per downline) hanya melihat sales bawahannya.
        $koor = $this->akun('koor.satu', 'koordinator', 137, [
            'role_id' => $this->role('Koor Lihat Absen', 'downline', ['absensi_sales' => ['lihat']]),
        ]);
        $this->actingAs($koor);
        $this->assertSame(['Rudi Hartono'], $this->sales($this->inertiaProps($this->get('/absensi_sales')->assertOk(), 'Kehadiran/Absensi')));
        $this->get('/foto/'.$fotoId)->assertOk();

        // Role tanpa hak menu absensi: tidak boleh membuka halaman maupun fotonya.
        $lain = $this->akun('staf', 'lainnya', 500, ['role_id' => $this->role('Gudang', 'semua', ['home' => ['lihat']])]);
        $this->actingAs($lain);
        $this->get('/absensi_sales')->assertForbidden();
        $this->get('/foto/'.$fotoId)->assertForbidden();
    }
}
