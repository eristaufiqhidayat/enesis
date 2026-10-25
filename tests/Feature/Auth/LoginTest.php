<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('andi|127.0.0.1');
    }

    private function user(array $atribut = []): User
    {
        return User::factory()->create(['username' => 'Andi', 'password' => 'rahasia123'] + $atribut);
    }

    public function test_halaman_login_tampil_dan_tamu_diarahkan_ke_login(): void
    {
        $this->get('/login')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Auth/Login'));

        foreach (['/home', '/provinsi', '/tracking_koordinator_history_location', '/master_area/design_area', '/loading_barang/add'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
    }

    public function test_login_dengan_username_tanpa_beda_huruf_dan_kembali_ke_halaman_tujuan(): void
    {
        $user = $this->user();

        $this->get('/provinsi')->assertRedirect('/login');
        $this->post('/login', ['username' => 'andi', 'password' => 'rahasia123'])->assertRedirect('/provinsi');

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->login_terakhir_pada);
    }

    public function test_login_tanpa_tujuan_ke_home_dan_halaman_login_tidak_untuk_yang_sudah_masuk(): void
    {
        $this->user();

        $this->post('/login', ['username' => 'Andi', 'password' => 'rahasia123'])->assertRedirect('/home');
        $this->get('/login')->assertRedirect('/home');
    }

    public function test_password_salah_atau_akun_nonaktif_ditolak(): void
    {
        $this->user();
        User::factory()->create(['username' => 'lama', 'password' => 'rahasia123', 'aktif' => false]);

        $this->from('/login')->post('/login', ['username' => 'Andi', 'password' => 'salah'])
            ->assertRedirect('/login')->assertSessionHasErrors(['username' => 'Username atau password salah.']);
        $this->post('/login', ['username' => 'lama', 'password' => 'rahasia123'])->assertSessionHasErrors('username');
        $this->post('/login', ['username' => 'tidak-ada', 'password' => 'x'])->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_logout_mengakhiri_session(): void
    {
        $this->actingAs($this->user());

        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
        $this->get('/home')->assertRedirect('/login');
    }

    public function test_rate_limit_setelah_lima_kali_gagal(): void
    {
        $this->user();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['username' => 'andi', 'password' => 'salah']);
        }

        $this->post('/login', ['username' => 'Andi', 'password' => 'rahasia123'])
            ->assertSessionHasErrors(['username']);
        $this->assertStringContainsString('Terlalu banyak percobaan', session('errors')->first('username'));
        $this->assertGuest();

        // Username lain dari IP yang sama tidak terkena.
        User::factory()->create(['username' => 'budi', 'password' => 'rahasia123']);
        $this->post('/login', ['username' => 'budi', 'password' => 'rahasia123'])->assertRedirect('/home');
    }

    public function test_akun_password_awal_wajib_ganti_password_dulu(): void
    {
        $user = $this->user(['wajib_ganti_password' => true]);

        $this->post('/login', ['username' => 'andi', 'password' => 'rahasia123'])->assertRedirect('/home');
        $this->get('/home')->assertRedirect('/ganti-password');
        $this->get('/ganti-password')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Auth/GantiPassword')->where('wajib', true));

        $this->put('/ganti-password', ['password' => 'pendek', 'password_confirmation' => 'pendek'])->assertSessionHasErrors('password');
        $this->put('/ganti-password', ['password' => 'Andi', 'password_confirmation' => 'Andi'])->assertSessionHasErrors('password');
        $this->put('/ganti-password', ['password' => 'rahasia123', 'password_confirmation' => 'rahasia123'])->assertSessionHasErrors('password');

        $this->put('/ganti-password', ['password' => 'baru-12345', 'password_confirmation' => 'baru-12345'])->assertRedirect('/home');
        $this->assertFalse($user->fresh()->wajib_ganti_password);
        $this->get('/home')->assertOk();

        // Ganti password biasa (bukan wajib) meminta password lama.
        $this->get('/ganti-password')->assertInertia(fn (Assert $page) => $page
            ->component('Auth/GantiPassword')->where('wajib', false));
        $this->put('/ganti-password', ['password_lama' => 'salah', 'password' => 'lain-12345', 'password_confirmation' => 'lain-12345'])
            ->assertSessionHasErrors('password_lama');
    }

    public function test_topbar_menampilkan_nama_user_dan_tombol_keluar(): void
    {
        $this->actingAs(User::factory()->create(['name' => 'Andi Saputra', 'username' => 'andi']));

        $this->get('/tracking_koordinator_history_location')->assertOk()->assertSee('Andi Saputra');

        $this->get('/provinsi')->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.name', 'Andi Saputra')
            ->where('auth.user.username', 'andi')
            ->missing('auth.user.password'));
    }

    public function test_demo_masuk_cepat_mati_secara_bawaan(): void
    {
        $this->get('/login')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Login')->where('demoPeran', null));
        $this->post('/login/demo/super_admin')->assertNotFound();
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['username' => 'demo.super_admin']);
    }

    public function test_demo_masuk_cepat_bila_diaktifkan(): void
    {
        config(['auth.demo_login' => true]);

        $this->get('/login')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Login')->where('demoPeran', config('auth.demo_peran')));

        $this->post('/login/demo/koordinator')->assertRedirect('/home');
        $user = auth()->user();
        $this->assertSame('demo.koordinator', $user->username);
        $this->assertSame('koordinator', $user->kategori);
        $this->assertFalse($user->wajib_ganti_password);
        $this->get('/home')->assertOk();

        // Peran yang tidak dikenal ditolak; akun demo dipakai ulang, tidak dibuat ganda.
        $this->post('/logout');
        $this->post('/login/demo/bukan-peran')->assertNotFound();
        $this->post('/login/demo/koordinator')->assertRedirect('/home');
        $this->assertSame(1, User::where('username', 'demo.koordinator')->count());
    }

    public function test_seeder_admin_lokal_bisa_dipakai_login(): void
    {
        $this->seed(UserSeeder::class);

        $this->post('/login', ['username' => 'admin', 'password' => 'password'])->assertRedirect('/home');
        $this->assertAuthenticated();
    }

    public function test_seeder_admin_dilewati_di_production_tanpa_password(): void
    {
        $this->app['env'] = 'production';
        config(['auth.seed_admin_password' => null]);

        // Langsung run(): $this->seed() lewat db:seed meminta konfirmasi di production.
        (new UserSeeder)->run();

        $this->assertDatabaseMissing('users', ['username' => 'admin']);
    }
}
