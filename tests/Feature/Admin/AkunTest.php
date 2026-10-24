<?php

namespace Tests\Feature\Admin;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

// Kelola akun login: daftar, tambah, edit, password awal, status, sesi HP, keputusan, hapus.
class AkunTest extends AdminTestCase
{
    public function test_daftar_user_sales_dari_tabel_users_dengan_filter(): void
    {
        $rudi = $this->akun('rudi.h', 'sales', 1, ['wajib_ganti_password' => true]);
        $this->akun('sinta', 'sales', 2, ['perlu_keputusan' => true]);
        $this->akun('joko', 'sales', 4, ['aktif' => false]);
        $this->akun('koor', 'koordinator', 137);
        DB::table('mk_user')->insert(['jenis' => 'sales', 'id' => 2001, 'username' => 'rudi.h', 'nickname' => 'Rudi H', 'karyawan_id' => 1]);
        DB::table('users')->where('id', $rudi->id)->update(['sumber_jenis' => 'sales', 'sumber_id' => 2001]);
        DB::table('mobile_tokens')->insert(['user_id' => $rudi->id, 'token_hash' => str_repeat('a', 64), 'device_id' => 'X', 'device_model' => 'Redmi 10', 'app_version' => '1.0.0', 'last_used_at' => now()]);

        $this->get('/user_sales')->assertOk()
            ->assertSee('rudi.h')->assertSee('Rudi H')->assertSee('Rudi Hartono')->assertSee('Sidoarjo')->assertSee('Koor Satu')
            ->assertSee('Redmi 10 · v1.0.0')->assertSee('Belum ganti password awal')->assertSee('Perlu keputusan admin')
            ->assertSee('Karyawan sudah tidak aktif')->assertDontSee('>koor<', false)
            ->assertSee('Tambah akun');

        $this->get('/user_sales?status=keputusan')->assertOk()->assertSee('sinta')->assertDontSee('rudi.h');
        $this->get('/user_sales?status=nonaktif')->assertOk()->assertSee('joko')->assertDontSee('sinta');
        $this->get('/user_sales?q=balaraja')->assertOk()->assertSee('sinta')->assertDontSee('rudi.h');
        $this->get('/user_sales?depo=46')->assertOk()->assertSee('rudi.h')->assertDontSee('sinta');
        $this->get('/user_koordinator')->assertOk()->assertSee('koor')->assertDontSee('rudi.h');
    }

    public function test_lihat_sales_di_bawah_koordinator(): void
    {
        $koor = $this->akun('koor.satu', 'koordinator', 137);
        $this->akun('rudi', 'sales', 1);
        $this->akun('tegar', 'sales', 3);
        $this->akun('sinta', 'sales', 2);
        $this->akun('joko', 'sales', 4, ['aktif' => false]);

        // Daftar koordinator: jumlah sales aktif bertaut ke User Sales tersaring.
        $this->get('/user_koordinator')->assertOk()
            ->assertSee('Sales aktif')
            ->assertSee(route('akun.user_sales.index', ['koordinator' => 137]), false);

        // User Sales tersaring per koordinator.
        $this->get('/user_sales?koordinator=137')->assertOk()
            ->assertSee('Semua koordinator')->assertSee('rudi')->assertSee('tegar')->assertSee('joko')
            ->assertDontSee('sinta');

        // Cari juga lewat nama koordinator.
        $this->get('/user_sales?q=koor+satu')->assertOk()->assertSee('rudi')->assertDontSee('sinta');

        // Detail koordinator menampilkan daftar sales bawahannya.
        $this->get('/user_koordinator/'.$koor->id)->assertOk()
            ->assertSee('Sales di bawah koordinator ini')->assertSee('2 aktif dari 3 akun')
            ->assertSee('rudi')->assertDontSee('sinta');

        // Juga di halaman edit koordinator.
        $this->get('/user_koordinator/'.$koor->id.'/edit')->assertOk()
            ->assertSee('Sales di bawah koordinator ini')->assertSee('tegar');

        // Akun sales tidak menampilkan kartu ini.
        $this->get('/user_sales/'.User::where('username', 'rudi')->value('id'))->assertOk()
            ->assertDontSee('Sales di bawah koordinator ini');
    }

    public function test_klik_menu_dari_halaman_inertia_membuka_halaman_blade_penuh(): void
    {
        // Tanpa ini Inertia menampilkan halaman Blade di dalam popup.
        $this->get('/user_sales', ['X-Inertia' => 'true', 'X-Inertia-Version' => ''])
            ->assertStatus(409)->assertHeader('X-Inertia-Location', url('/user_sales'));
        $this->get('/role', ['X-Inertia' => 'true'])->assertStatus(409);

        // Halaman Inertia dan permintaan biasa tidak berubah.
        $versi = (string) app(HandleInertiaRequests::class)->version(request());
        $this->get('/provinsi', ['X-Inertia' => 'true', 'X-Inertia-Version' => $versi])->assertOk()->assertHeader('X-Inertia', 'true');
        $this->get('/user_sales')->assertOk();
        $this->getJson('/user_sales/karyawan?q=ru')->assertOk();
    }

    public function test_tambah_akun_password_otomatis_tampil_sekali(): void
    {
        $this->get('/user_sales/tambah')->assertOk()->assertSee('Password awal')->assertSee('name="karyawan_id"', false);

        $res = $this->post('/user_sales', [
            'kategori' => 'sales', 'username' => 'Tegar', 'karyawan_id' => 3, 'password_mode' => 'otomatis', 'aktif' => '1',
        ])->assertOk()->assertSee('hanya tampil sekali')->assertHeader('Cache-Control', 'no-store, private');

        $user = User::where('username', 'Tegar')->firstOrFail();
        preg_match('/id="password_awal"[^>]*>([^<]+)</', $res->getContent(), $m);
        $this->assertTrue(Hash::check(trim($m[1]), $user->password));
        $this->assertTrue($user->wajib_ganti_password);
        $this->assertSame('Tegar Mahendra', $user->name);
        $this->assertSame($this->roleId('sales'), (int) $user->role_id);
        $this->assertDatabaseHas('riwayat_admin', ['objek' => 'user', 'objek_id' => $user->id, 'aksi' => 'dibuat', 'aktor_id' => $this->admin->id]);

        // Password tidak tersimpan di session untuk dibaca ulang.
        $this->get('/user_sales/'.$user->id)->assertOk()->assertDontSee(trim($m[1]));
    }

    public function test_aturan_tambah_akun(): void
    {
        $this->akun('rudi.h', 'sales', 1);

        $this->post('/user_sales', ['kategori' => 'sales', 'username' => 'baru', 'password_mode' => 'otomatis', 'aktif' => '1'])
            ->assertSessionHasErrors(['karyawan_id' => 'Akun sales dan koordinator wajib terhubung ke karyawan.']);
        $this->post('/user_sales', ['kategori' => 'sales', 'username' => 'RUDI.H', 'karyawan_id' => 3, 'password_mode' => 'otomatis', 'aktif' => '1'])
            ->assertSessionHasErrors('username');
        $this->post('/user_sales', ['kategori' => 'sales', 'username' => 'rudi2', 'karyawan_id' => 1, 'password_mode' => 'otomatis', 'aktif' => '1'])
            ->assertSessionHasErrors('karyawan_id');
        $this->post('/user_sales', ['kategori' => 'sales', 'username' => 'pakai spasi', 'karyawan_id' => 3, 'password_mode' => 'otomatis', 'aktif' => '1'])
            ->assertSessionHasErrors('username');

        // Password sendiri: langsung ke detail; akun lainnya boleh tanpa karyawan.
        $this->post('/user_lainnya', [
            'kategori' => 'lainnya', 'username' => 'gudang', 'password_mode' => 'manual',
            'password' => 'gudang-123', 'password_confirmation' => 'gudang-123', 'aktif' => '1',
        ])->assertRedirect();
        $gudang = User::where('username', 'gudang')->firstOrFail();
        $this->assertTrue(Hash::check('gudang-123', $gudang->password));
        $this->assertFalse($gudang->wajib_ganti_password);
    }

    public function test_edit_ganti_role_kategori_dan_nonaktif_mencabut_sesi(): void
    {
        $rudi = $this->akun('rudi.h', 'sales', 1);
        DB::table('mobile_tokens')->insert(['user_id' => $rudi->id, 'token_hash' => str_repeat('b', 64), 'device_id' => 'X', 'last_used_at' => now()]);
        $gudangRole = $this->role('Admin Depo', 'depo', ['home' => ['lihat']]);

        $this->get("/user_sales/{$rudi->id}/edit")->assertOk()->assertSee('value="rudi.h"', false)->assertDontSee('Password awal</legend>', false);

        $this->put("/user_sales/{$rudi->id}", [
            'kategori' => 'sales', 'username' => 'rudi.h', 'karyawan_id' => 1, 'role_id' => $gudangRole, 'aktif' => '0',
        ])->assertRedirect("/user_sales/{$rudi->id}");

        $rudi->refresh();
        $this->assertFalse($rudi->aktif);
        $this->assertSame($gudangRole, (int) $rudi->role_id);
        $this->assertSame('admin', DB::table('mobile_tokens')->value('revoked_reason'));

        $this->put("/user_sales/{$rudi->id}", ['kategori' => 'koordinator', 'username' => 'rudi.h', 'karyawan_id' => 1, 'aktif' => '0'])
            ->assertRedirect("/user_koordinator/{$rudi->id}");
        $this->assertSame('koordinator', $rudi->fresh()->kategori);
    }

    public function test_atur_password_awal_baru(): void
    {
        $rudi = $this->akun('rudi.h', 'sales', 1);

        $res = $this->post("/user_sales/{$rudi->id}/password")->assertOk()->assertSee('Password awal untuk rudi.h');
        preg_match('/id="password_awal"[^>]*>([^<]+)</', $res->getContent(), $m);

        $rudi->refresh();
        $this->assertTrue(Hash::check(trim($m[1]), $rudi->password));
        $this->assertFalse(Hash::check('rahasia123', $rudi->password));
        $this->assertTrue($rudi->wajib_ganti_password);
    }

    public function test_nonaktif_aktif_cabut_sesi_dan_keputusan_akun_ganda(): void
    {
        $a = $this->akun('agung', 'sales', 1, ['perlu_keputusan' => true]);
        $b = $this->akun('agung_877', 'sales', 1, ['perlu_keputusan' => true]);
        DB::table('mobile_tokens')->insert(['user_id' => $b->id, 'token_hash' => str_repeat('c', 64), 'device_id' => 'X', 'device_model' => 'Samsung A15', 'last_used_at' => now()]);

        $this->get("/user_sales/{$a->id}")->assertOk()->assertSee('Perlu keputusan admin')->assertSee('agung_877')->assertSee('Pakai akun ini');

        $this->post("/user_sales/{$a->id}/pakai")->assertRedirect("/user_sales/{$a->id}");
        $this->assertTrue($a->fresh()->aktif);
        $this->assertFalse($a->fresh()->perlu_keputusan);
        $this->assertFalse($b->fresh()->aktif);
        $this->assertNotNull(DB::table('mobile_tokens')->where('user_id', $b->id)->value('revoked_at'));

        // Mengaktifkan akun kedua ditolak selama karyawan punya akun aktif lain.
        $this->post("/user_sales/{$b->id}/status", ['aktif' => 1])->assertSessionHasErrors('status');

        $this->post("/user_sales/{$a->id}/status", ['aktif' => 0, 'alasan' => 'Karyawan keluar'])->assertRedirect();
        $this->assertFalse($a->fresh()->aktif);
        $this->assertDatabaseHas('riwayat_admin', ['objek_id' => $a->id, 'aksi' => 'dinonaktifkan', 'keterangan' => 'Karyawan keluar']);

        $this->post("/user_sales/{$b->id}/status", ['aktif' => 1])->assertRedirect();
        $this->assertTrue($b->fresh()->aktif);

        DB::table('mobile_tokens')->insert(['user_id' => $b->id, 'token_hash' => str_repeat('d', 64), 'device_id' => 'Y', 'last_used_at' => now()]);
        $this->post("/user_sales/{$b->id}/cabut-sesi")->assertRedirect();
        $this->assertSame(0, DB::table('mobile_tokens')->where('user_id', $b->id)->whereNull('revoked_at')->count());

        // Akun sendiri tidak bisa dinonaktifkan.
        $this->post("/super_admin/{$this->admin->id}/status", ['aktif' => 0])->assertSessionHasErrors('status');
    }

    public function test_hapus_hanya_akun_yang_belum_pernah_dipakai(): void
    {
        $lama = $this->akun('dari.lama', 'sales', 1, ['sumber_jenis' => 'sales', 'sumber_id' => 9]);
        $pernah = $this->akun('pernah', 'sales', 2, ['login_terakhir_pada' => now()]);
        $baru = $this->akun('baru', 'sales', 3);

        $this->get("/user_sales/{$lama->id}")->assertSee('Akun dari data aplikasi lama tidak dihapus');
        $this->delete("/user_sales/{$lama->id}", ['konfirmasi' => 'dari.lama'])->assertSessionHasErrors('hapus');
        $this->delete("/user_sales/{$pernah->id}", ['konfirmasi' => 'pernah'])->assertSessionHasErrors('hapus');
        $this->delete("/user_sales/{$baru->id}", ['konfirmasi' => 'salah'])->assertSessionHasErrors('hapus');

        $this->delete("/user_sales/{$baru->id}", ['konfirmasi' => 'BARU'])->assertRedirect('/user_sales');
        $this->assertDatabaseMissing('users', ['id' => $baru->id]);
        $this->assertDatabaseHas('riwayat_admin', ['objek_id' => $baru->id, 'aksi' => 'dihapus']);
    }

    public function test_cari_karyawan_menandai_yang_sudah_punya_akun(): void
    {
        $this->akun('rudi.h', 'sales', 1);

        $this->getJson('/user_sales/karyawan?q=ru')->assertOk()
            ->assertJsonPath('0.nama', 'Rudi Hartono')->assertJsonPath('0.akun_aktif', 'rudi.h')->assertJsonPath('0.depo', 'Sidoarjo');
        $this->getJson('/user_sales/karyawan?q=joko')->assertOk()->assertJsonCount(0); // karyawan nonaktif
        $this->getJson('/user_sales/karyawan?q=3')->assertOk()->assertJsonPath('0.nama', 'Tegar Mahendra');
    }

    public function test_hak_dan_cakupan_data_role(): void
    {
        $this->akun('rudi.h', 'sales', 1);
        $this->akun('sinta', 'sales', 2);

        // Role bawaan "Lainnya" tidak punya hak menu kelola akun.
        $staf = $this->akun('staf', 'lainnya', 500);
        $this->actingAs($staf);
        $this->get('/user_sales')->assertForbidden();
        $this->get('/home')->assertOk()->assertDontSee('"slug":"user_sales"', false)->assertDontSee('"slug":"role"', false);

        // Role buatan: lihat user sales, cakupan depo sendiri (Sidoarjo).
        DB::table('users')->where('id', $staf->id)->update(['role_id' => $this->role('Admin Depo', 'depo', ['home' => ['lihat'], 'user_sales' => ['lihat']])]);
        $this->actingAs($staf->fresh());
        $this->get('/user_sales')->assertOk()->assertSee('rudi.h')->assertDontSee('sinta')->assertDontSee('Tambah akun');
        $this->get('/user_sales/tambah')->assertForbidden();
        $this->get('/user_sales/'.User::where('username', 'sinta')->value('id'))->assertNotFound();
        $this->get('/home')->assertSee('"slug":"user_sales"', false);
    }

    public function test_role_mengatur_login_web_dan_aplikasi_hp(): void
    {
        $sales = $this->akun('rudi.h', 'sales', 1);
        auth()->logout();

        // Role bawaan Sales Motoris: hanya aplikasi HP.
        $this->post('/login', ['username' => 'rudi.h', 'password' => 'rahasia123'])
            ->assertSessionHasErrors(['username' => 'Akun ini hanya untuk aplikasi HP, tidak bisa membuka web Enesis.']);
        $this->assertGuest();

        // Sesi web yang sudah ada ikut dikeluarkan.
        $this->actingAs($sales)->get('/home')->assertRedirect('/login');

        $this->postJson('/api/mobile/v1/auth/login', ['username' => 'rudi.h', 'password' => 'rahasia123', 'device_id' => 'HP'], ['X-App-Version' => '1.0.0'])
            ->assertOk();

        DB::table('roles')->where('kode', 'sales')->update(['login_mobile' => false]);
        $this->postJson('/api/mobile/v1/auth/login', ['username' => 'rudi.h', 'password' => 'rahasia123', 'device_id' => 'HP'], ['X-App-Version' => '1.0.0'])
            ->assertForbidden()->assertJsonPath('error.code', 'role_not_supported');
    }
}
