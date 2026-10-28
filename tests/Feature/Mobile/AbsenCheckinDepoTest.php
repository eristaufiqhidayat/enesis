<?php

namespace Tests\Feature\Mobile;

use App\Models\User;
use Illuminate\Support\Facades\DB;

// Absen = checkin depo (disatukan seperti Marketama, 28 Okt 2026): absen masuk membuat checkin depo, absen pulang
// menutup checkin depo, checkout depo dari aplikasi lama ikut menutup absen; migrasi melengkapi data absen lama.
class AbsenCheckinDepoTest extends MobileApiTestCase
{
    public function test_checkout_depo_aplikasi_lama_ikut_menutup_absen(): void
    {
        $this->sales();
        $this->login();
        $this->api('POST', 'attendances/check-in', $this->diDepo() + ['photo_id' => $this->foto()])->assertCreated();

        $this->api('POST', 'depot-visits/check-out', $this->diDepo())->assertOk();

        $this->assertNotNull(DB::table('trx_absensi')->value('pulang_at'));
        $this->assertSame((string) DB::table('trx_checkin_depo')->value('checkout_at'), (string) DB::table('trx_absensi')->value('pulang_at'));
        // Absen pulang sesudahnya (alur aplikasi lama) dijawab sukses; checkout depo kedua kali ditolak.
        $this->api('POST', 'attendances/check-out', $this->diDepo())->assertOk();
        $this->assertError($this->api('POST', 'depot-visits/check-out', []), 409, 'already_checked_out');
    }

    public function test_absen_koordinator_juga_checkin_depo(): void
    {
        User::factory()->create(['username' => 'koor', 'password' => 'rahasia123', 'kategori' => 'koordinator', 'karyawan_id' => 137]);
        $this->login('koor');

        $id = $this->api('POST', 'attendances/check-in', $this->diDepo() + ['photo_id' => $this->foto()])->assertCreated()->json('data.depot_visit_id');

        $this->assertNotNull($id);
        $this->assertSame(137, (int) DB::table('trx_checkin_depo')->where('id', $id)->value('karyawan_id'));
    }

    public function test_absen_ditolak_di_luar_radius_tidak_membuat_checkin_depo(): void
    {
        $this->sales();
        $this->login();

        $this->assertError($this->api('POST', 'attendances/check-in', ['lat' => -6.3, 'lng' => 106.8, 'photo_id' => $this->foto()]), 422, 'outside_depot_radius');
        $this->assertSame(0, DB::table('trx_absensi')->count());
        $this->assertSame(0, DB::table('trx_checkin_depo')->count());
    }

    public function test_migrasi_melengkapi_checkin_depo_dari_absen_lama(): void
    {
        DB::table('trx_absensi')->insert([
            ['karyawan_id' => 1, 'depo_id' => 46, 'tanggal' => '2026-10-24', 'masuk_at' => '2026-10-24 00:55:00', 'pulang_at' => '2026-10-24 10:00:00',
                'masuk_lat' => -6.2, 'masuk_lng' => 106.8, 'sumber' => 'motoris'],
            ['karyawan_id' => 2, 'depo_id' => 46, 'tanggal' => '2026-10-24', 'masuk_at' => '2026-10-24 01:00:00', 'pulang_at' => '2026-10-24 11:00:00',
                'masuk_lat' => -6.2, 'masuk_lng' => 106.8, 'sumber' => 'motoris'],
        ]);
        // Karyawan 2 sudah checkin depo tapi belum checkout.
        DB::table('trx_checkin_depo')->insert(['karyawan_id' => 2, 'depo_id' => 46, 'tanggal' => '2026-10-24', 'checkin_at' => '2026-10-24 01:05:00']);

        $migrasi = require database_path('migrations/2026_10_28_000003_satukan_absen_checkin_depo.php');
        $migrasi->up();

        $satu = DB::table('trx_checkin_depo')->where('karyawan_id', 1)->first();
        $this->assertSame(['2026-10-24 00:55:00', '2026-10-24 10:00:00'], [(string) $satu->checkin_at, (string) $satu->checkout_at]);
        $this->assertSame('2026-10-24 11:00:00', (string) DB::table('trx_checkin_depo')->where('karyawan_id', 2)->value('checkout_at'));
        $this->assertSame(2, DB::table('trx_checkin_depo')->count());
    }
}
