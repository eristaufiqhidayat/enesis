<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\LoginOtomatis;
use Tests\TestCase;

class MenuTest extends TestCase
{
    use LoginOtomatis, RefreshDatabase;

    public function test_root_redirects_to_home(): void
    {
        $this->get('/')->assertRedirect('/home');
    }

    public function test_every_sidebar_menu_page_loads(): void
    {
        foreach (array_keys(config('marketama.pages')) as $slug) {
            $this->get("/{$slug}")->assertOk();
        }
    }

    public function test_menu_page_lists_table_rows(): void
    {
        DB::table('provinsi')->insert(['kode' => '32', 'nama_provinsi' => 'Jawa Barat']);

        $this->get('/provinsi')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Menu/Show')
                ->where('tables.0.rows.data.0.nama_provinsi', 'Jawa Barat')
                ->where('tables.0.rows.total', 1)
            );
    }

    public function test_user_sales_diambil_dari_tabel_users(): void
    {
        DB::table('mk_depo')->insert(['id' => 46, 'nama' => 'Sidoarjo']);
        DB::table('mk_karyawan')->insert([
            ['id' => 137, 'kategori' => 'koordinator', 'nama' => 'Koor Satu', 'depo_id' => 46, 'koordinator_id' => null],
            ['id' => 1, 'kategori' => 'sales', 'nama' => 'Andi Motoris', 'depo_id' => 46, 'koordinator_id' => 137],
        ]);
        DB::table('mk_user')->insert(['jenis' => 'sales', 'id' => 2001, 'username' => 'andi', 'nickname' => 'Andi M', 'karyawan_id' => 1]);
        DB::table('users')->insert([
            ['name' => 'Andi', 'username' => 'andi', 'password' => 'x', 'kategori' => 'sales', 'karyawan_id' => 1, 'aktif' => true,
                'wajib_ganti_password' => true, 'perlu_keputusan' => false, 'sumber_jenis' => 'sales', 'sumber_id' => 2001],
            ['name' => 'Lama', 'username' => 'lama', 'password' => 'x', 'kategori' => 'sales', 'karyawan_id' => null, 'aktif' => false,
                'wajib_ganti_password' => false, 'perlu_keputusan' => false, 'sumber_jenis' => null, 'sumber_id' => null],
            ['name' => 'Koor', 'username' => 'koor', 'password' => 'x', 'kategori' => 'koordinator', 'karyawan_id' => 137, 'aktif' => true,
                'wajib_ganti_password' => false, 'perlu_keputusan' => false, 'sumber_jenis' => null, 'sumber_id' => null],
        ]);
        // Tabel salinan layar lama tidak lagi ditampilkan.
        DB::table('user_sales')->insert(['username' => 'dari_csv_lama']);

        $this->get('/user_sales')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('tables.0.rows.total', 2)
            ->where('tables.0.rows.data.0.username', 'andi')
            ->where('tables.0.rows.data.0.nickname', 'Andi M')
            ->where('tables.0.rows.data.0.karyawan', 'Andi Motoris')
            ->where('tables.0.rows.data.0.depo', 'Sidoarjo')
            ->where('tables.0.rows.data.0.koordinator', 'Koor Satu')
            ->where('tables.0.rows.data.0.status', 'Aktif')
            ->where('tables.0.rows.data.0.keterangan', 'Belum ganti password awal')
            ->where('tables.0.rows.data.1.status', 'Tidak Aktif')
            ->where('tables.0.rows.data.1.keterangan', 'Belum terhubung ke karyawan')
            ->missing('tables.0.rows.data.0.password'));

        $this->get('/user_sales?q=sidoarjo')->assertInertia(fn (Assert $page) => $page
            ->where('tables.0.rows.total', 1)
            ->where('tables.0.rows.data.0.username', 'andi'));
    }

    public function test_unknown_menu_returns_404(): void
    {
        $this->get('/tidak_ada')->assertNotFound();
    }
}
