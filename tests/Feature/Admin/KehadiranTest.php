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
        $this->get('/absensi_sales')->assertOk()
            ->assertSee('Rudi Hartono')->assertSee('08:20')->assertSee('Terlambat')
            ->assertSee('Sinta Wulandari')->assertSee('07:30')->assertSee('9 j 30 m')
            ->assertSee('Motoris')->assertSee('Aplikasi lama')
            ->assertDontSee('Tegar Mahendra'); // di luar 7 hari terakhir

        // Rentang tanggal, sumber, nama, koordinator.
        $this->get('/absensi_sales?dari=2026-09-01&sampai=2026-09-30')->assertOk()->assertSee('Tegar Mahendra')->assertDontSee('Rudi Hartono');
        $this->get('/absensi_sales?sumber=lama')->assertOk()->assertSee('Sinta Wulandari')->assertDontSee('Rudi Hartono');
        $this->get('/absensi_sales?q=rudi')->assertOk()->assertSee('Rudi Hartono')->assertDontSee('Sinta Wulandari');
        $this->get('/absensi_sales?koordinator=137')->assertOk()->assertSee('Rudi Hartono')->assertDontSee('Sinta Wulandari');

        // Menu terlambat = status terlambat.
        $this->get('/absensi_sales_terlambat')->assertOk()->assertSee('Rudi Hartono')->assertDontSee('Sinta Wulandari');
        $this->get('/lap_absensi_sales?status=belum_pulang')->assertOk()->assertSee('Rudi Hartono')->assertDontSee('Sinta Wulandari');
    }

    public function test_checkin_depo_dari_tabel_motoris(): void
    {
        $this->get('/checkin_depo')->assertOk()
            ->assertSee('Rudi Hartono')->assertSee('07:10')->assertSee('35 m dari depo')
            ->assertSee('Sinta Wulandari')->assertSee('8 j 55 m');

        $this->get('/lap_checkin_checkout?depo=22')->assertOk()->assertSee('Sinta Wulandari')->assertDontSee('Rudi Hartono');
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

        $this->get('/checkin_outlet')->assertOk()
            ->assertSee('Toko Makmur')->assertSee('Rp 125.000')->assertSee('15 m')->assertSee('Di luar rute')
            ->assertSee('Toko tutup')->assertSee('Pemilik pergi')->assertSee('Foto (aplikasi lama)');

        $this->get('/checkin_outlet?status=beli')->assertOk()->assertSee('Toko Makmur')->assertDontSee('Pemilik pergi');
        $this->get('/checkin_outlet?status=tidak_beli')->assertOk()->assertSee('Toko tutup')->assertDontSee('Rp 125.000');
        $this->get('/lap_checkin_outlet?outlet=makmur')->assertOk()->assertSee('Toko Makmur')->assertDontSee('Warung Bu Sri');
        $this->get('/checkin_outlet?sumber=lama')->assertOk()->assertSee('Sinta Wulandari')->assertDontSee('Rudi Hartono');
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

        $this->get('/absensi_sales')->assertSee(route('kehadiran.foto', $fotoId), false);
        $this->get('/foto/'.$fotoId)->assertOk()->assertHeader('Content-Type', 'image/jpeg');

        // Koordinator (cakupan per downline) hanya melihat sales bawahannya.
        $koor = $this->akun('koor.satu', 'koordinator', 137, [
            'role_id' => $this->role('Koor Lihat Absen', 'downline', ['absensi_sales' => ['lihat']]),
        ]);
        $this->actingAs($koor);
        $this->get('/absensi_sales')->assertOk()->assertSee('Rudi Hartono')->assertDontSee('Sinta Wulandari');
        $this->get('/foto/'.$fotoId)->assertOk();

        // Role tanpa hak menu absensi: tidak boleh membuka halaman maupun fotonya.
        $lain = $this->akun('staf', 'lainnya', 500, ['role_id' => $this->role('Gudang', 'semua', ['home' => ['lihat']])]);
        $this->actingAs($lain);
        $this->get('/absensi_sales')->assertForbidden();
        $this->get('/foto/'.$fotoId)->assertForbidden();
    }
}
