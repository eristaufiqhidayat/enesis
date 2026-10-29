<?php

namespace Tests\Feature\Mobile;

use App\Models\User;
use Illuminate\Support\Facades\DB;

// Absen dan checkin depo TERPISAH (keputusan 28 Okt 2026, lihat claude/menu-absensi-master-area.md):
// absen dicek terhadap area kerja (Master Area), checkin/checkout depo terhadap radius depo. Lihat juga
// HariKerjaSalesTest (langkah 2 & 5) untuk alur gabungan satu hari kerja lengkap.
class AbsenCheckinDepoTest extends MobileApiTestCase
{
    public function test_absen_masuk_tidak_membuat_checkin_depo(): void
    {
        $this->sales();
        $this->login();
        $this->api('POST', 'attendances/check-in', $this->diDepo() + ['photo_id' => $this->foto()])
            ->assertCreated()->assertJsonMissingPath('data.depot_visit_id');

        $this->assertSame(0, DB::table('trx_checkin_depo')->count());
    }

    public function test_absen_ditolak_di_luar_area_kerja_tapi_dekat_depo(): void
    {
        $this->sales();
        $this->login();

        // Jauh di luar polygon area 10 meski cukup dekat dengan depo (radius depo tidak lagi dipakai di sini).
        $this->assertError(
            $this->api('POST', 'attendances/check-in', ['lat' => -6.3, 'lng' => 106.8, 'photo_id' => $this->foto()]),
            422,
            'outside_work_area'
        );
        $this->assertSame(0, DB::table('trx_absensi')->count());
        $this->assertSame(0, DB::table('trx_checkin_depo')->count());
    }

    public function test_absen_ditolak_tanpa_area_kerja_aktif(): void
    {
        $this->sales();
        $this->login();
        DB::table('mk_karyawan_area')->where('karyawan_id', 1)->delete();

        $this->assertError(
            $this->api('POST', 'attendances/check-in', $this->diDepo() + ['photo_id' => $this->foto()]),
            409,
            'no_work_area'
        );
    }

    public function test_absen_koordinator_dicek_terhadap_gabungan_area_tim(): void
    {
        User::factory()->create(['username' => 'koor', 'password' => 'rahasia123', 'kategori' => 'koordinator', 'karyawan_id' => 137]);
        $this->login('koor');

        // Di dalam polygon area 10 (area aktif sales 1 & 2, bawahan koordinator 137): diterima.
        $this->api('POST', 'attendances/check-in', $this->diDepo() + ['photo_id' => $this->foto()])
            ->assertCreated()->assertJsonMissingPath('data.depot_visit_id');
        $this->assertSame(0, DB::table('trx_checkin_depo')->count());
    }

    public function test_checkin_depo_terpisah_tidak_mempengaruhi_absen(): void
    {
        $this->sales();
        $this->login();
        $this->assertError($this->api('POST', 'depot-visits/check-in', $this->diDepo()), 409, 'not_checked_in');

        $this->api('POST', 'attendances/check-in', $this->diDepo() + ['photo_id' => $this->foto()])->assertCreated();
        $this->api('POST', 'depot-visits/check-in', $this->diDepo())->assertCreated();
        $this->assertSame(1, DB::table('trx_checkin_depo')->count());

        // Checkout depo tidak ikut menutup absen (dan sebaliknya) — lihat HariKerjaSalesTest langkah 5.
        $this->api('POST', 'depot-visits/check-out', [])->assertOk();
        $this->assertNull(DB::table('trx_absensi')->value('pulang_at'));
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
