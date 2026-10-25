<?php

namespace Tests\Feature\Mobile;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

// API HP koordinator: login, absen, tim hari ini, outlet terdekat, join visit, tracking.
class KoordinatorMobileTest extends MobileApiTestCase
{
    protected function isiMaster(): void
    {
        parent::isiMaster();
        // Sales tim koordinator lain (tidak boleh didampingi koordinator 137).
        DB::table('mk_karyawan')->insert(['id' => 3, 'kategori' => 'sales', 'nama' => 'Cici Lain Tim', 'depo_id' => 46, 'koordinator_id' => 999, 'aktif' => true]);
    }

    private function koordinator(): User
    {
        return User::factory()->create(['username' => 'koor', 'password' => 'rahasia123', 'kategori' => 'koordinator', 'karyawan_id' => 137]);
    }

    private function masukDanAbsen(): void
    {
        $this->koordinator();
        $this->login('koor', 'HP-K');
        $this->api('POST', 'attendances/check-in', $this->diDepo() + ['photo_id' => $this->foto()])->assertCreated();
    }

    public function test_koordinator_bisa_login_dengan_menu_koordinator_saja(): void
    {
        $this->koordinator();

        $this->login('koor', 'HP-K')->assertOk()
            ->assertJsonPath('data.user.role', 'koordinator')
            ->assertJsonPath('data.user.permissions', ['attendance', 'team', 'join_visit', 'tracking']);

        $this->api('GET', 'config')->assertOk();
        $this->api('GET', 'team/today')->assertOk();
        // Menu sales tidak tersedia untuk koordinator.
        $this->assertError($this->api('GET', 'routes/today'), 403, 'forbidden');
        $this->assertError($this->api('POST', 'visits', ['outlet_id' => 11] + $this->diOutlet(11)), 403, 'forbidden');
    }

    public function test_sales_tidak_bisa_memakai_menu_koordinator(): void
    {
        $this->sales();
        $this->login();

        $this->assertError($this->api('GET', 'team/today'), 403, 'forbidden');
        $this->assertError($this->api('POST', 'join-visits', ['sales_id' => 1, 'outlet_id' => 11] + $this->diOutlet(11)), 403, 'forbidden');
    }

    public function test_absen_dan_tracking_koordinator_masuk_tabel_yang_sama_dengan_sales(): void
    {
        $this->masukDanAbsen();

        $this->assertSame(137, (int) DB::table('trx_absensi')->value('karyawan_id'));
        $this->api('POST', 'tracking/batch', ['points' => [
            ['recorded_at' => '2026-10-26T09:05:00+07:00', 'lat' => -6.2, 'lng' => 106.8, 'accuracy_m' => 10],
        ]])->assertOk()->assertJsonPath('data.accepted', 1);
        $this->assertSame(137, (int) DB::table('trx_tracking')->value('karyawan_id'));
    }

    public function test_tim_hari_ini_berisi_sales_bawahan_dan_kegiatannya(): void
    {
        $this->koordinator();
        $this->login('koor', 'HP-K');
        DB::table('trx_absensi')->insert(['karyawan_id' => 1, 'depo_id' => 46, 'tanggal' => '2026-10-26', 'masuk_at' => '2026-10-26 00:55:00', 'terlambat' => false]);
        $kunjungan = DB::table('trx_kunjungan')->insertGetId(['karyawan_id' => 1, 'outlet_id' => 11, 'tanggal' => '2026-10-26', 'checkin_at' => '2026-10-26 01:30:00', 'checkout_at' => '2026-10-26 01:45:00']);
        DB::table('trx_penjualan')->insert([
            'kode' => 'PJ-1', 'kunjungan_id' => $kunjungan, 'karyawan_id' => 1, 'tanggal' => '2026-10-26',
            'jumlah_sku' => 2, 'total_qty' => 5, 'total' => 45000, 'is_effective_call' => true,
        ]);
        DB::table('trx_tracking')->insert(['karyawan_id' => 1, 'recorded_at' => '2026-10-26 01:50:00', 'lat' => -6.201, 'lng' => 106.801]);

        $r = $this->api('GET', 'team/today')->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.checked_in', 1)
            ->assertJsonPath('meta.sales_amount', 45000);

        $this->assertSame(['Andi Motoris', 'Budi Motoris'], array_column($r->json('data'), 'name'));
        $r->assertJsonPath('data.0.visits', 1)
            ->assertJsonPath('data.0.ec', 1)
            ->assertJsonPath('data.0.attendance.is_late', false)
            ->assertJsonPath('data.0.last_position.lat', -6.201)
            ->assertJsonPath('data.1.attendance', null);
    }

    public function test_join_visit_mulai_selesai_dan_riwayat(): void
    {
        $this->koordinator();
        $this->login('koor', 'HP-K');
        $mulai = ['sales_id' => 1, 'outlet_id' => 11] + $this->diOutlet(11);

        $this->assertError($this->api('POST', 'join-visits', $mulai), 409, 'not_checked_in');
        $this->api('POST', 'attendances/check-in', $this->diDepo() + ['photo_id' => $this->foto()])->assertCreated();

        // Outlet terdekat untuk memilih outlet.
        $this->assertSame(11, $this->api('GET', 'coordinator/outlets/nearby?lat=-6.201&lng=106.801&radius=100')->assertOk()->json('data.0.id'));

        $this->assertError($this->api('POST', 'join-visits', ['sales_id' => 3] + $mulai), 422, 'not_your_team');
        $this->assertError($this->api('POST', 'join-visits', ['lat' => -6.3, 'lng' => 106.9] + $mulai), 422, 'outside_outlet_radius');

        $id = $this->api('POST', 'join-visits', $mulai)->assertCreated()->json('data.join_visit_id');
        $this->assertError($this->api('POST', 'join-visits', ['sales_id' => 2] + $mulai), 409, 'join_visit_open');
        $this->assertError($this->api('POST', 'attendances/check-out', $this->diDepo()), 409, 'join_visit_open');

        $this->travel(15)->minutes();
        $this->assertError($this->api('POST', "join-visits/{$id}/finish", ['note' => ''] + $this->diOutlet(11)), 422, 'validation_failed');
        $this->api('POST', "join-visits/{$id}/finish", [
            'note' => 'Display rak rapi, stok minuman tinggal 2.', 'photo_id' => $this->foto('lampiran'),
        ] + $this->diOutlet(11))->assertOk()->assertJsonPath('data.duration_s', 900);
        $this->assertError($this->api('POST', "join-visits/{$id}/finish", ['note' => 'lagi', 'photo_id' => $this->foto('lampiran')]), 409, 'already_finished');

        $this->api('GET', 'join-visits')->assertOk()
            ->assertJsonPath('data.0.id', $id)
            ->assertJsonPath('data.0.sales.name', 'Andi Motoris')
            ->assertJsonPath('data.0.outlet.name', 'Toko Sebelas')
            ->assertJsonPath('data.0.status', 'selesai')
            ->assertJsonPath('data.0.note', 'Display rak rapi, stok minuman tinggal 2.');

        // Setelah join visit selesai, absen pulang boleh.
        $this->api('POST', 'attendances/check-out', $this->diDepo())->assertOk();
    }

    public function test_koordinator_tanpa_tim_tidak_bisa_join_visit_sales_siapa_pun(): void
    {
        $this->masukDanAbsen();
        DB::table('mk_karyawan')->where('id', 1)->update(['aktif' => false]);

        $this->assertError($this->api('POST', 'join-visits', ['sales_id' => 1, 'outlet_id' => 11] + $this->diOutlet(11)), 422, 'not_your_team');
    }

    public function test_alur_hp_koordinator_tampil_di_web(): void
    {
        $this->masukDanAbsen();
        $this->api('POST', 'tracking/batch', ['points' => [
            ['recorded_at' => '2026-10-26T09:05:00+07:00', 'lat' => -6.201, 'lng' => 106.801, 'accuracy_m' => 10],
        ]])->assertOk();
        $id = $this->api('POST', 'join-visits', ['sales_id' => 1, 'outlet_id' => 11] + $this->diOutlet(11))->assertCreated()->json('data.join_visit_id');
        $foto = $this->foto('lampiran');
        $this->api('POST', "join-visits/{$id}/finish", ['note' => 'Sales sopan, stok cukup.', 'photo_id' => $foto])->assertOk();

        $this->app['auth']->forgetGuards();
        $admin = User::factory()->create(['username' => 'admin', 'kategori' => null, 'role_id' => null, 'aktif' => true, 'wajib_ganti_password' => false]);
        $this->actingAs($admin, 'web');

        $this->get('/join_visit')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps(fn (Assert $page) => $page
                    ->where('tables.0.rows.total', 1)
                    ->where('tables.0.rows.data.0.koordinator', 'Koor Satu')
                    ->where('tables.0.rows.data.0.sales', 'Andi Motoris')
                    ->where('tables.0.rows.data.0.outlet', 'Toko Sebelas')
                    ->where('tables.0.rows.data.0.mulai', '09:00')
                    ->where('tables.0.rows.data.0.catatan', 'Sales sopan, stok cukup.')
                    ->where('tables.0.rows.data.0.foto_id', $foto)
                )
            );
        $this->get("/foto/{$foto}")->assertOk();
        $this->get('/absensi_koordinator?dari=2026-10-26&sampai=2026-10-26')->assertOk()->assertSee('Koor Satu');
        $this->getJson('/tracking_koordinator_history_location/counts?tanggal=2026-10-26')->assertOk()->assertJsonPath('137', 1);
    }
}
