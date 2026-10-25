<?php

namespace Tests\Feature;

use App\Support\Akses\Akses;
use Tests\TestCase;

// Menu yang belum terhubung ke data aplikasi motoris diberi awalan "* " di sidebar (config marketama.menu_motoris).
class MenuMotorisTest extends TestCase
{
    private function judul(): array
    {
        $hasil = [];

        foreach (Akses::menu(null) as $grup) {
            foreach ($grup['items'] as $item) {
                foreach ($item['children'] ?? [$item] as $m) {
                    $hasil[$m['slug']] = $m['title'];
                }
            }
        }

        return $hasil;
    }

    public function test_menu_belum_terhubung_motoris_diberi_bintang(): void
    {
        $judul = $this->judul();

        $this->assertSame('* Loading Barang', $judul['loading_barang']);
        $this->assertSame('* Outlet Duplikat / Fiktif', $judul['outlet_duplikat']);
        $this->assertSame('Stok Kompetitor', $judul['stok_kompetitor']);
        $this->assertSame('Lap. Join Visit', $judul['lap_join_visit']);
        $this->assertSame('Checkin Outlet', $judul['checkin_outlet']);
        $this->assertSame('Summary All Depo', $judul['summary_all_depo']);
        $this->assertSame('Join Visit', $judul['join_visit']);
    }

    public function test_daftar_menu_motoris_hanya_berisi_slug_yang_ada(): void
    {
        $this->assertEmpty(array_diff(config('marketama.menu_motoris'), array_keys($this->judul())));
    }

    public function test_grup_flyout_tidak_diberi_bintang(): void
    {
        foreach (Akses::menu(null) as $grup) {
            foreach ($grup['items'] as $item) {
                if (isset($item['children'])) {
                    $this->assertStringStartsNotWith('* ', $item['title']);
                }
            }
        }
    }
}
