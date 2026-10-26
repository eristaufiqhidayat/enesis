<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\LoginOtomatis;
use Tests\TestCase;

class MenuSortTest extends TestCase
{
    use LoginOtomatis, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Urutan sisipan sengaja tidak sama dengan urutan nama, supaya sort terlihat jelas.
        DB::table('provinsi')->insert([
            ['kode' => '32', 'nama_provinsi' => 'Jawa Barat'],
            ['kode' => '11', 'nama_provinsi' => 'Aceh'],
            ['kode' => '51', 'nama_provinsi' => 'Bali'],
        ]);
    }

    public function test_sort_naik_dan_turun_pada_kolom_yang_diizinkan(): void
    {
        $this->get('/provinsi?sort[provinsi]=nama_provinsi&arah[provinsi]=asc')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $page) => $page
                ->where('tables.0.sort', ['kolom' => 'nama_provinsi', 'arah' => 'asc'])
                ->where('tables.0.rows.data.0.nama_provinsi', 'Aceh')
                ->where('tables.0.rows.data.2.nama_provinsi', 'Jawa Barat')
            ));

        $this->get('/provinsi?sort[provinsi]=nama_provinsi&arah[provinsi]=desc')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $page) => $page
                ->where('tables.0.sort', ['kolom' => 'nama_provinsi', 'arah' => 'desc'])
                ->where('tables.0.rows.data.0.nama_provinsi', 'Jawa Barat')
                ->where('tables.0.rows.data.2.nama_provinsi', 'Aceh')
            ));
    }

    public function test_arah_kosong_atau_tidak_dikenal_dianggap_naik(): void
    {
        $this->get('/provinsi?sort[provinsi]=nama_provinsi')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $page) => $page
                ->where('tables.0.sort', ['kolom' => 'nama_provinsi', 'arah' => 'asc'])
                ->where('tables.0.rows.data.0.nama_provinsi', 'Aceh')
            ));
    }

    public function test_sort_pada_kolom_di_luar_whitelist_diabaikan(): void
    {
        // Whitelist dibatasi lewat config; source_id ada di columns tapi tidak di 'sortable', jadi tidak boleh dipakai sebagai orderBy.
        // Urutan kembali ke id (sisipan): Jawa Barat, Aceh, Bali.
        config(['marketama.tables.provinsi.sortable' => ['kode', 'nama_provinsi']]);

        $this->get('/provinsi?sort[provinsi]=source_id&arah[provinsi]=desc')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $page) => $page
                ->where('tables.0.sort', null)
                ->where('tables.0.sortable_columns', ['kode', 'nama_provinsi'])
                ->where('tables.0.rows.data.0.nama_provinsi', 'Jawa Barat')
                ->where('tables.0.rows.data.1.nama_provinsi', 'Aceh')
            ));
    }

    public function test_kolom_sort_yang_tidak_ada_tidak_menyebabkan_error(): void
    {
        $this->get('/provinsi?sort[provinsi]=tidak_ada;drop&arah[provinsi]=desc')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $page) => $page
                ->where('tables.0.sort', null)
                ->where('tables.0.rows.total', 3)
            ));
    }

    public function test_sort_hanya_berlaku_untuk_tabel_pemilik_namespace(): void
    {
        // Parameter sort untuk tabel lain tidak mempengaruhi tabel provinsi di halaman ini.
        $this->get('/provinsi?sort[tabel_lain]=nama_provinsi&arah[tabel_lain]=desc')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $page) => $page
                ->where('tables.0.sort', null)
                ->where('tables.0.rows.data.0.nama_provinsi', 'Jawa Barat')
            ));
    }

    public function test_filter_kolom_mempersempit_baris_sesuai_nilai(): void
    {
        $this->get('/provinsi?filter[provinsi][nama_provinsi]=aceh')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $page) => $page
                ->where('tables.0.rows.total', 1)
                ->where('tables.0.rows.data.0.nama_provinsi', 'Aceh')
                ->where('tables.0.filter_kolom', ['nama_provinsi' => 'aceh'])
            ));
    }

    public function test_filter_kolom_digabung_dengan_pencarian_global_sebagai_and(): void
    {
        // q mencocokkan Jawa Barat, filter kode 11 (Aceh): tidak ada baris yang cocok keduanya.
        $this->get('/provinsi?q=Jawa&filter[provinsi][kode]=11')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $page) => $page
                ->where('tables.0.rows.total', 0)
            ));
    }

    public function test_filter_kosong_dan_kolom_di_luar_whitelist_diabaikan(): void
    {
        config(['marketama.tables.provinsi.filterable' => ['kode']]);

        // Nilai kosong tidak memfilter; nama_provinsi tidak ada di whitelist sehingga diabaikan.
        $this->get('/provinsi?filter[provinsi][kode]=&filter[provinsi][nama_provinsi]=Aceh')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $page) => $page
                ->where('tables.0.rows.total', 3)
                ->where('tables.0.filter_kolom', [])
                ->where('tables.0.filterable_columns', ['kode'])
            ));
    }

    public function test_filter_hanya_berlaku_untuk_tabel_pemilik_namespace(): void
    {
        $this->get('/provinsi?filter[tabel_lain][kode]=11')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $page) => $page
                ->where('tables.0.rows.total', 3)
            ));
    }

    public function test_default_semua_kolom_tabel_bisa_di_sort_kecuali_no(): void
    {
        // Tanpa key 'sortable' di config, semua kolom yang ada di database ikut bisa di-sort (kecuali 'no').
        $this->get('/provinsi')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $page) => $page
                ->where('tables.0.sortable_columns', ['source_id', 'kode', 'nama_provinsi'])
                ->where('tables.0.filterable_columns', ['source_id', 'kode', 'nama_provinsi'])
            ));
    }

    public function test_sortable_kosong_mematikan_sort(): void
    {
        config(['marketama.tables.sales_tidak_penjualan.sortable' => []]);

        $this->get('/monitoring_harian_sales?tab=sales_tidak_penjualan&sort[sales_tidak_penjualan]=nama_sales&arah[sales_tidak_penjualan]=desc')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $page) => $page
                ->where('tables.0.sortable_columns', [])
                ->where('tables.0.sort', null)
            ));
    }
}
