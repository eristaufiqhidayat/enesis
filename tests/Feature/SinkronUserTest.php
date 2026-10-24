<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

// Akun login Enesis dari mk_user + mk_karyawan (data rekaan).
class SinkronUserTest extends TestCase
{
    use RefreshDatabase;

    private function isiData(): void
    {
        DB::table('mk_karyawan')->insert([
            ['id' => 1, 'kategori' => 'sales', 'nama' => 'Andi', 'aktif' => true],
            ['id' => 2, 'kategori' => 'sales', 'nama' => 'Andi Lama', 'aktif' => false],
            ['id' => 3, 'kategori' => 'koordinator', 'nama' => 'Citra', 'aktif' => true],
            ['id' => 4, 'kategori' => 'sales', 'nama' => 'Budi', 'aktif' => true],
        ]);

        $u = fn ($jenis, $id, $username, $karyawan, $aktif = true) => [
            'jenis' => $jenis, 'id' => $id, 'username' => $username, 'karyawan_id' => $karyawan, 'aktif' => $aktif,
        ];

        DB::table('mk_user')->insert([
            $u('sales', 10, 'andi', 1),
            $u('sales', 11, 'Andi', 2),                // karyawan nonaktif + username kembar
            $u('sales', 12, 'budi', 4),
            $u('koordinator', 13, 'budi2', 4),         // karyawan 4 punya dua akun aktif
            $u('koordinator', 14, 'citra', 3),
            $u('super_admin', 15, 'CITRA', null),      // kembar dengan akun aktif lain
            $u('lainnya', 16, 'Admin', null, false),   // bentrok dengan akun manual
        ]);

        DB::table('users')->insert(['name' => 'Admin Manual', 'username' => 'admin', 'password' => Hash::make('x'), 'aktif' => true]);
    }

    private function user(string $jenis, int $id): object
    {
        return DB::table('users')->where(['sumber_jenis' => $jenis, 'sumber_id' => $id])->first();
    }

    public function test_aturan_migrasi_akun(): void
    {
        $this->isiData();

        $this->artisan('enesis:sinkron-user')->assertSuccessful();

        $this->assertSame(8, DB::table('users')->count());

        // Akun aktif milik karyawan nonaktif -> nonaktif; username kembar diberi akhiran.
        $andiLama = $this->user('sales', 11);
        $this->assertFalse((bool) $andiLama->aktif);
        $this->assertSame('Andi_11', $andiLama->username);
        $this->assertStringContainsString('karyawan sudah tidak aktif', $andiLama->catatan_migrasi);
        $this->assertSame('andi', $this->user('sales', 10)->username);
        $this->assertFalse((bool) $this->user('sales', 10)->perlu_keputusan);

        // Karyawan dengan dua akun aktif -> keduanya perlu keputusan, tetap aktif.
        foreach ([['sales', 12], ['koordinator', 13]] as [$j, $id]) {
            $this->assertTrue((bool) $this->user($j, $id)->perlu_keputusan);
            $this->assertTrue((bool) $this->user($j, $id)->aktif);
        }

        // Dua akun aktif berusername sama -> perlu keputusan, satu diganti.
        $this->assertSame('citra', $this->user('koordinator', 14)->username);
        $this->assertSame('CITRA_15', $this->user('super_admin', 15)->username);
        $this->assertTrue((bool) $this->user('super_admin', 15)->perlu_keputusan);

        // Username akun manual tidak diambil.
        $this->assertSame('Admin_16', $this->user('lainnya', 16)->username);

        // Nama dari karyawan, kategori dari jenis, wajib ganti password.
        $andi = $this->user('sales', 10);
        $this->assertSame('Andi', $andi->name);
        $this->assertSame('sales', $andi->kategori);
        $this->assertSame(1, (int) $andi->karyawan_id);
        $this->assertTrue((bool) $andi->wajib_ganti_password);
    }

    public function test_jalan_ulang_tidak_menimpa_password(): void
    {
        $this->isiData();
        $this->artisan('enesis:sinkron-user')->assertSuccessful();

        $this->artisan('enesis:atur-password', ['username' => 'ANDI'])
            ->expectsOutputToContain('Password awal untuk andi:')
            ->assertSuccessful();
        $hash = $this->user('sales', 10)->password;

        $this->artisan('enesis:sinkron-user')->assertSuccessful();

        $this->assertSame(8, DB::table('users')->count());
        $this->assertSame($hash, $this->user('sales', 10)->password);
        $this->assertSame('Andi_11', $this->user('sales', 11)->username);
    }

    public function test_atur_password_menghasilkan_password_yang_valid(): void
    {
        $this->isiData();
        $this->artisan('enesis:sinkron-user')->assertSuccessful();

        $this->artisan('enesis:atur-password', ['username' => 'citra'])->assertSuccessful();

        // Password tidak bisa dibaca dari database; pastikan hash berubah dan wajib ganti.
        $citra = $this->user('koordinator', 14);
        $this->assertTrue((bool) $citra->wajib_ganti_password);
        $this->assertFalse(Hash::check('', $citra->password));

        $this->artisan('enesis:atur-password', ['username' => 'tidak-ada'])->assertFailed();
    }

    public function test_tanpa_mk_user_gagal(): void
    {
        $this->artisan('enesis:sinkron-user')->assertFailed();
    }
}
