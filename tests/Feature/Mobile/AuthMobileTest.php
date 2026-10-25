<?php

namespace Tests\Feature\Mobile;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

// Login per HP, satu akun satu perangkat, versi aplikasi, dan kunci idempotensi.
class AuthMobileTest extends MobileApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('mobile-login|andi');
    }

    public function test_login_mengembalikan_token_dan_profil(): void
    {
        $this->sales(['name' => 'Akun Andi']);

        $r = $this->login('ANDI')->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.must_change_password', false)
            ->assertJsonPath('data.user.role', 'sales')
            ->assertJsonPath('data.user.name', 'Andi Motoris')
            ->assertJsonPath('data.user.coordinator.name', 'Koor Satu')
            ->assertHeader('X-Min-App-Version', '1.0.0');

        // Server hanya menyimpan hash token.
        $this->assertDatabaseMissing('mobile_tokens', ['token_hash' => $r->json('data.token')]);
        $this->assertDatabaseHas('mobile_tokens', ['token_hash' => hash('sha256', $r->json('data.token')), 'device_id' => 'HP-A', 'device_model' => 'Redmi 10']);

        $this->api('GET', 'me')->assertOk()->assertJsonPath('data.username', 'andi')->assertJsonPath('data.depot.id', 46);
    }

    public function test_login_ditolak(): void
    {
        $this->sales();
        // Koordinator sekarang boleh login HP (menu koordinator); staf "lainnya" tetap ditolak.
        User::factory()->create(['username' => 'staf', 'password' => 'rahasia123', 'kategori' => 'lainnya', 'karyawan_id' => 137]);
        User::factory()->create(['username' => 'lepas', 'password' => 'rahasia123', 'kategori' => 'sales', 'karyawan_id' => null]);
        User::factory()->create(['username' => 'cek', 'password' => 'rahasia123', 'kategori' => 'sales', 'karyawan_id' => 2, 'perlu_keputusan' => true]);
        User::factory()->create(['username' => 'lama', 'password' => 'rahasia123', 'kategori' => 'sales', 'karyawan_id' => 2, 'aktif' => false]);

        $kirim = fn (string $u, string $p = 'rahasia123') => $this->postJson('/api/mobile/v1/auth/login', ['username' => $u, 'password' => $p, 'device_id' => 'HP-A']);

        $this->assertError($kirim('andi', 'salah'), 401, 'invalid_credentials');
        $this->assertError($kirim('lama'), 401, 'invalid_credentials');
        $this->assertError($kirim('staf'), 403, 'role_not_supported');
        $this->assertError($kirim('lepas'), 403, 'no_employee_link');
        $this->assertError($kirim('cek'), 403, 'account_under_review');
        $this->assertError($this->postJson('/api/mobile/v1/auth/login', ['username' => 'andi']), 422, 'validation_failed')
            ->assertJsonStructure(['error' => ['fields' => ['password', 'device_id']]]);
        $this->assertDatabaseCount('mobile_tokens', 0);
    }

    public function test_login_dibatasi_per_username(): void
    {
        $this->sales();

        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/mobile/v1/auth/login', ['username' => 'andi', 'password' => 'salah', 'device_id' => 'HP-A']);
        }

        $this->assertError($this->login(), 429, 'too_many_attempts')->assertHeader('Retry-After');
    }

    public function test_login_di_hp_lain_mencabut_token_lama(): void
    {
        $this->sales();
        $lama = $this->login('andi', 'HP-A')->json('data.token');
        $baru = $this->login('andi', 'HP-B')->json('data.token');

        $this->token = $lama;
        $this->assertError($this->api('GET', 'me'), 401, 'device_replaced');
        $this->token = $baru;
        $this->api('GET', 'me')->assertOk();

        $this->api('POST', 'auth/logout', [], false)->assertOk();
        $this->assertError($this->api('GET', 'me'), 401, 'token_revoked');

        $this->token = 'enm_palsu';
        $this->assertError($this->api('GET', 'me'), 401, 'unauthenticated');
        $this->token = null;
        $this->assertError($this->api('GET', 'me'), 401, 'unauthenticated');
    }

    public function test_token_yang_lama_tidak_dipakai_dicabut(): void
    {
        $this->sales();
        $this->login();

        $this->travel(31)->days();
        $this->assertError($this->api('GET', 'me'), 401, 'token_expired');
        $this->assertSame('idle', DB::table('mobile_tokens')->value('revoked_reason'));
    }

    public function test_password_awal_wajib_diganti_lewat_api(): void
    {
        $user = $this->sales(['wajib_ganti_password' => true]);
        $this->login()->assertOk()->assertJsonPath('data.must_change_password', true);

        $this->api('GET', 'me')->assertOk();
        $this->assertError($this->api('GET', 'master'), 403, 'password_change_required');

        $this->assertError($this->api('POST', 'auth/change-password', ['password' => 'andi', 'password_confirmation' => 'andi'], false), 422, 'validation_failed');
        $this->assertError($this->api('POST', 'auth/change-password', ['password' => 'rahasia123', 'password_confirmation' => 'rahasia123'], false), 422, 'validation_failed');
        $this->api('POST', 'auth/change-password', ['password' => 'baru-12345', 'password_confirmation' => 'baru-12345'], false)->assertOk();

        $this->assertFalse($user->fresh()->wajib_ganti_password);
        $this->api('GET', 'master')->assertOk();

        // Ganti biasa butuh password lama.
        $this->assertError($this->api('POST', 'auth/change-password', ['password' => 'lain-12345', 'password_confirmation' => 'lain-12345'], false), 422, 'validation_failed')
            ->assertJsonStructure(['error' => ['fields' => ['current_password']]]);
    }

    public function test_versi_aplikasi_lama_ditolak(): void
    {
        $this->sales();
        $this->login('andi');
        config(['mobile.versi_minimum' => '1.2.0']);

        $this->assertError($this->api('GET', 'me'), 426, 'upgrade_required')->assertHeader('X-Min-App-Version', '1.2.0');
        $this->withHeaders(['X-App-Version' => '1.2.0', 'Authorization' => 'Bearer '.$this->token])
            ->getJson('/api/mobile/v1/me')->assertOk();
    }

    public function test_akun_koordinator_tidak_bisa_memakai_endpoint_sales(): void
    {
        $koor = User::factory()->create(['username' => 'koor', 'kategori' => 'koordinator', 'karyawan_id' => 137]);
        $this->token = 'enm_koor';
        DB::table('mobile_tokens')->insert(['user_id' => $koor->id, 'token_hash' => hash('sha256', 'enm_koor'), 'device_id' => 'X', 'last_used_at' => now()]);

        $this->assertError($this->api('GET', 'routes/today'), 403, 'forbidden');
    }

    public function test_idempotency_key_wajib_dan_tidak_boleh_dipakai_ulang_untuk_endpoint_lain(): void
    {
        $this->sales();
        $this->login();
        $selfie = $this->foto();

        $this->assertError($this->api('POST', 'attendances/check-in', $this->diDepo() + ['photo_id' => $selfie], false), 422, 'idempotency_key_required');

        $kunci = (string) Str::uuid();
        $this->api('POST', 'attendances/check-in', $this->diDepo() + ['photo_id' => $selfie], $kunci)->assertCreated();
        $this->assertError($this->api('POST', 'depot-visits/check-in', $this->diDepo(), $kunci), 409, 'idempotency_key_reused');

        // Respons gagal tidak disimpan: kunci yang sama boleh dipakai setelah diperbaiki.
        $kunci2 = (string) Str::uuid();
        $this->assertError($this->api('POST', 'depot-visits/check-in', ['lat' => -6.3, 'lng' => 106.8], $kunci2), 422, 'outside_depot_radius');
        $this->api('POST', 'depot-visits/check-in', $this->diDepo(), $kunci2)->assertCreated();
    }

    public function test_pembersihan_harian(): void
    {
        $this->sales();
        $this->login();
        DB::table('mobile_idempotency')->insert(['user_id' => 1, 'kunci' => 'lama', 'endpoint' => 'x', 'status' => 201, 'respons' => '{}', 'created_at' => now()->subDays(40)]);

        $this->travel(31)->days();
        $this->artisan('enesis:bersihkan-mobile')->assertSuccessful();

        $this->assertSame('idle', DB::table('mobile_tokens')->value('revoked_reason'));
        $this->assertDatabaseCount('mobile_idempotency', 0);
    }
}
