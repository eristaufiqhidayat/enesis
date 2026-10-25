<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// Salin data lama (lap_*) ke tabel motoris (trx_*), data rekaan.
class MigrasiTransaksiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mobile.zona_waktu' => 'Asia/Jakarta', 'mobile.batas_masuk' => '08:00']);

        DB::table('mk_depo')->insert([['id' => 46, 'nama' => 'Sidoarjo'], ['id' => 22, 'nama' => 'Balaraja']]);
        DB::table('mk_karyawan')->insert([
            ['id' => 1, 'kategori' => 'sales', 'nama' => 'Andi', 'depo_id' => 46],
            ['id' => 2, 'kategori' => 'sales', 'nama' => 'Budi', 'depo_id' => 22],
        ]);
    }

    public function test_absensi_lama_masuk_ke_trx_absensi_tanpa_dobel(): void
    {
        $absen = fn ($src, $nama, $tgl, $masuk, $pulang = null, $depo = 'Sidoarjo') => [
            'source_id' => $src, 'date' => $tgl, 'time_in' => $masuk ? "$tgl $masuk" : null, 'time_out' => $pulang ? "$tgl $pulang" : null,
            'employee_name' => $nama, 'warehouse_name' => $depo,
        ];
        DB::table('lap_absensi_sales')->insert([
            $absen(101, 'Andi', '2026-10-01', '07:45:00', '17:00:00'),
            $absen(102, 'Andi', '2026-10-02', '08:20:00'),               // terlambat
            $absen(103, 'Andi', '2026-10-02', '09:00:00'),               // ganda di tanggal sama
            $absen(104, 'Budi', '2026-10-01', '07:30:00', null, 'Balaraja'),
            $absen(105, 'Orang Lain', '2026-10-01', '07:30:00'),
            $absen(null, 'Andi', '2026-10-05', '07:30:00'),
            $absen(106, 'Andi', '2026-10-03', '07:50:00'),               // sudah absen di motoris
        ]);

        // Andi sudah absen lewat aplikasi motoris tanggal 3.
        DB::table('trx_absensi')->insert(['karyawan_id' => 1, 'depo_id' => 46, 'tanggal' => '2026-10-03', 'masuk_at' => '2026-10-03 00:40:00']);

        $this->artisan('enesis:migrasi-transaksi absensi')->assertSuccessful();

        $this->assertSame(3, DB::table('trx_absensi')->where('sumber', 'lama')->count());
        $andi1 = DB::table('trx_absensi')->where('sumber_id', 101)->first();
        $this->assertSame('2026-10-01', substr($andi1->tanggal, 0, 10));
        $this->assertSame('2026-10-01 00:45:00', substr($andi1->masuk_at, 0, 19)); // 07:45 WIB = 00:45 UTC
        $this->assertSame('2026-10-01 10:00:00', substr($andi1->pulang_at, 0, 19));
        $this->assertSame(46, (int) $andi1->depo_id);
        $this->assertFalse((bool) $andi1->terlambat);
        $this->assertTrue((bool) DB::table('trx_absensi')->where('sumber_id', 102)->value('terlambat'));
        $this->assertSame(22, (int) DB::table('trx_absensi')->where('sumber_id', 104)->value('depo_id'));

        // Data motoris tidak ditimpa.
        $motoris = DB::table('trx_absensi')->where('tanggal', 'like', '2026-10-03%')->get();
        $this->assertCount(1, $motoris);
        $this->assertSame('motoris', $motoris[0]->sumber);

        // Diulang: tidak ada tambahan.
        $this->artisan('enesis:migrasi-transaksi absensi')->assertSuccessful();
        $this->assertSame(3, DB::table('trx_absensi')->where('sumber', 'lama')->count());

        // Baris lama baru (susulan) ikut masuk pada jalankan berikutnya.
        DB::table('lap_absensi_sales')->insert($absen(107, 'Andi', '2026-10-06', '07:40:00'));
        $this->artisan('enesis:migrasi-transaksi absensi --tanpa-sambung')->assertSuccessful();
        $this->assertSame(3, DB::table('trx_absensi')->where('sumber', 'lama')->count()); // tanpa sambung: karyawan_id kosong
        $this->artisan('enesis:migrasi-transaksi absensi')->assertSuccessful();
        $this->assertSame(4, DB::table('trx_absensi')->where('sumber', 'lama')->count());
    }

    public function test_checkin_depo_lama_masuk_ke_trx_checkin_depo(): void
    {
        DB::table('lap_checkin_checkout')->insert([
            ['source_id' => 201, 'attendance_date' => '2026-10-01', 'checkin_time' => '2026-10-01 06:30:00', 'checkout_time' => '2026-10-01 16:00:00',
                'location_lat' => -7.4501, 'location_long' => 112.7182, 'warehouse_name' => 'Sidoarjo', 'employee_name' => 'Andi'],
            ['source_id' => 202, 'attendance_date' => '2026-10-01', 'checkin_time' => '2026-10-01 06:45:00', 'checkout_time' => null,
                'location_lat' => 0, 'location_long' => 0, 'warehouse_name' => null, 'employee_name' => 'Budi'],
            ['source_id' => 203, 'attendance_date' => null, 'checkin_time' => null, 'checkout_time' => null,
                'location_lat' => null, 'location_long' => null, 'warehouse_name' => 'Sidoarjo', 'employee_name' => 'Andi'],
        ]);

        $this->artisan('enesis:migrasi-transaksi checkin_depo')->assertSuccessful();

        $andi = DB::table('trx_checkin_depo')->where('sumber_id', 201)->first();
        $this->assertSame(1, (int) $andi->karyawan_id);
        $this->assertSame(46, (int) $andi->depo_id);
        $this->assertSame('2026-09-30 23:30:00', substr($andi->checkin_at, 0, 19));
        $this->assertEqualsWithDelta(-7.4501, (float) $andi->lat, 0.0001);

        // Depo kosong di data lama: pakai depo karyawan; titik 0,0 dianggap kosong.
        $budi = DB::table('trx_checkin_depo')->where('sumber_id', 202)->first();
        $this->assertSame(22, (int) $budi->depo_id);
        $this->assertNull($budi->lat);
        $this->assertNull($budi->checkout_at);

        $this->assertDatabaseMissing('trx_checkin_depo', ['sumber_id' => 203]); // tanpa tanggal
        $this->assertSame(2, DB::table('trx_checkin_depo')->count());
    }

    public function test_join_visit_lama_masuk_ke_trx_join_visit(): void
    {
        DB::table('mk_karyawan')->insert(['id' => 137, 'kategori' => 'koordinator', 'nama' => 'Koor Satu', 'depo_id' => 46]);
        DB::table('mk_outlet')->insert(['id' => 11, 'kode' => 'OUT-11', 'nama' => 'Toko Sebelas']);
        $baris = fn ($src, $koor) => [
            'source_id' => $src, 'koordinator_name' => $koor, 'sales_name' => 'Andi', 'warehouse_name' => 'Sidoarjo',
            'inspection_date' => '2026-10-20 00:00:00', 'start_time' => '2026-10-20 10:00:00', 'end_time' => '2026-10-20 10:20:00',
            'description' => 'Rak rapi', 'outlet_code' => 'OUT-11', 'photo' => 'jv_501.jpg',
        ];
        DB::table('lap_join_visit')->insert([$baris(501, 'Koor Satu'), $baris(502, 'Koor Tidak Ada')]);

        $this->artisan('enesis:migrasi-transaksi join_visit')->assertSuccessful();
        $this->artisan('enesis:migrasi-transaksi join_visit')->assertSuccessful(); // diulang: tidak dobel

        $jv = DB::table('trx_join_visit')->sole();
        $this->assertSame(['lama', 501, 137, 1, 11, 46], [$jv->sumber, (int) $jv->sumber_id, (int) $jv->koordinator_id, (int) $jv->sales_id, (int) $jv->outlet_id, (int) $jv->depo_id]);
        $this->assertSame('2026-10-20', substr((string) $jv->tanggal, 0, 10));
        $this->assertSame('2026-10-20 03:00:00', (string) $jv->mulai_at); // 10:00 WIB disimpan UTC
        $this->assertSame(1200, (int) $jv->durasi_detik);
        $this->assertSame(['Rak rapi', 'jv_501.jpg'], [$jv->catatan, $jv->foto_lama]);
    }

    public function test_kunjungan_lama_masuk_ke_trx_kunjungan(): void
    {
        DB::table('mk_outlet')->insert([['id' => 11, 'kode' => 'OUT-1', 'nama' => 'Toko Satu']]);
        $kunjungan = fn ($src, $nama, $kode, $masuk, $keluar = null, $lain = []) => $lain + [
            'source_id' => $src, 'salesperson_name' => $nama, 'warehouse_name' => 'Sidoarjo', 'outlet_code' => $kode,
            'date_checkin' => substr($masuk, 0, 10), 'datetime_checkin' => $masuk, 'datetime_checkout' => $keluar,
            'sales_latitude' => -7.4500, 'sales_longitude' => 112.7100, 'outlet_latitude' => -7.4505, 'outlet_longitude' => 112.7100,
            'category' => null, 'description' => null, 'photo' => null,
        ];
        DB::table('lap_checkin_outlet')->insert([
            $kunjungan(301, 'Andi', 'OUT-1', '2026-10-01 09:00:00', '2026-10-01 09:12:30', ['category' => 2, 'description' => 'Toko tutup', 'photo' => 'https://app.lama/foto/a.jpg']),
            $kunjungan(302, 'Andi', 'OUT-1', '2026-10-01 13:00:00'),           // kunjungan kedua di hari yang sama tetap masuk
            $kunjungan(303, 'Andi', 'OUT-TIDAK-ADA', '2026-10-01 14:00:00'),
            $kunjungan(304, 'Andi', 'OUT-1', '2026-10-02 10:00:00', null, ['photo' => 'foto/relatif.jpg']),
        ]);
        DB::table('lap_checkin_outlet')->where('source_id', 304)->update(['datetime_checkin' => null]);

        $this->artisan('enesis:migrasi-transaksi kunjungan')->assertSuccessful();

        $this->assertSame(2, DB::table('trx_kunjungan')->where('sumber', 'lama')->count());
        $k = DB::table('trx_kunjungan')->where('sumber_id', 301)->first();
        $this->assertSame(11, (int) $k->outlet_id);
        $this->assertSame(46, (int) $k->depo_id);
        $this->assertSame('2026-10-01 02:00:00', substr($k->checkin_at, 0, 19));
        $this->assertSame(750, (int) $k->durasi_detik);
        $this->assertEqualsWithDelta(56, (int) $k->jarak_m, 2);
        $this->assertSame('https://app.lama/foto/a.jpg', $k->foto_url);
        $this->assertSame(2, (int) $k->kategori_lama);
        $this->assertSame('Toko tutup', $k->keterangan_lama);
        $this->assertNull($k->tidak_beli_kategori);

        $this->artisan('enesis:migrasi-transaksi kunjungan')->assertSuccessful();
        $this->assertSame(2, DB::table('trx_kunjungan')->count());
    }

    public function test_jenis_tidak_dikenal_ditolak(): void
    {
        $this->artisan('enesis:migrasi-transaksi pembelian')->assertFailed();
    }
}
