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

    public function test_jenis_tidak_dikenal_ditolak(): void
    {
        $this->artisan('enesis:migrasi-transaksi penjualan')->assertFailed();
    }
}
