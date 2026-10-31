<?php

namespace Tests\Feature\Admin;

use App\Support\Akses\Akses;
use Inertia\Testing\AssertableInertia as Assert;

// Tab penghubung Last Seen <-> Riwayat Lokasi (sales & koordinator) dan pilihan awal di halaman riwayat.
class TabHalamanTrackingTest extends AdminTestCase
{
    public function test_sales_punya_tiga_tab_dan_koordinator_dua_tab_di_semua_halamannya(): void
    {
        $sales = [
            ['slug' => 'tracking_sales', 'label' => 'Last Seen'],
            ['slug' => 'tracking_sales_history_location', 'label' => 'Riwayat Lokasi'],
            ['slug' => 'motoris_ditandai', 'label' => 'Ditandai'],
        ];
        $koordinator = [
            ['slug' => 'tracking_koordinator', 'label' => 'Last Seen'],
            ['slug' => 'tracking_koordinator_history_location', 'label' => 'Riwayat Lokasi'],
        ];

        foreach ([$sales, $koordinator] as $tab) {
            foreach (array_column($tab, 'slug') as $slug) {
                $this->get("/{$slug}")->assertOk()->assertInertia(fn (Assert $p) => $p->where('tabHalaman', $tab));
            }
        }
    }

    public function test_role_dengan_satu_halaman_saja_tidak_melihat_tab(): void
    {
        $role = $this->role('Cuma Last Seen', 'semua', ['tracking_sales' => ['lihat']]);
        $this->actingAs($this->akun('pemantau', 'koordinator', null, ['role_id' => $role]));

        $this->get('/tracking_sales')->assertOk()->assertInertia(fn (Assert $p) => $p->where('tabHalaman', []));
    }

    public function test_tab_hanya_memuat_halaman_yang_boleh_dibuka(): void
    {
        $role = $this->role('Dua Halaman', 'semua', [
            'tracking_sales' => ['lihat'],
            'tracking_sales_history_location' => ['lihat'],
        ]);
        $this->actingAs($this->akun('pemantau', 'koordinator', null, ['role_id' => $role]));

        $this->get('/tracking_sales')->assertOk()->assertInertia(fn (Assert $p) => $p->has('tabHalaman', 2));
    }

    public function test_halaman_lain_tidak_punya_tab(): void
    {
        $this->get('/home')->assertOk()->assertInertia(fn (Assert $p) => $p->where('tabHalaman', []));
    }

    /** @return array<int, string> slug semua item sidebar akun, diratakan. */
    private function slugSidebar($user): array
    {
        return collect(Akses::menu($user))->flatMap(fn ($g) => $g['items'])
            ->flatMap(fn ($i) => $i['children'] ?? [$i])->pluck('slug')->all();
    }

    public function test_sidebar_menyatukan_last_seen_dan_riwayat_jadi_satu_entri(): void
    {
        $slug = $this->slugSidebar($this->admin);
        $item = collect(Akses::menu($this->admin))->flatMap(fn ($g) => $g['items'])->firstWhere('slug', 'tracking_sales');

        $this->assertContains('tracking_sales', $slug);
        $this->assertContains('tracking_koordinator', $slug);
        $this->assertNotContains('tracking_sales_history_location', $slug);
        $this->assertNotContains('tracking_koordinator_history_location', $slug);
        $this->assertSame('Lacak Motoris', $item['title']);
        $this->assertSame(['tracking_sales_history_location', 'motoris_ditandai'], $item['serumpun']);
    }

    public function test_sidebar_menampilkan_riwayat_sendiri_bila_akun_tak_boleh_buka_last_seen(): void
    {
        $role = $this->role('Hanya Riwayat', 'semua', ['tracking_sales_history_location' => ['lihat']]);
        $slug = $this->slugSidebar($this->akun('pemantau', 'koordinator', null, ['role_id' => $role]));

        $this->assertContains('tracking_sales_history_location', $slug);
        $this->assertNotContains('tracking_sales', $slug);
    }

    public function test_pencarian_sidebar_tetap_menemukan_halaman_riwayat(): void
    {
        $cari = collect(Akses::menuPencarian($this->admin))->pluck('title', 'slug');

        $this->assertSame('Tracking History Sales Location', $cari['tracking_sales_history_location']);
        $this->assertSame('Tracking History Koordinator Location', $cari['tracking_koordinator_history_location']);
    }

    public function test_matriks_hak_role_tetap_memuat_kedua_halaman(): void
    {
        $this->assertContains('tracking_sales', Akses::semuaSlug());
        $this->assertContains('tracking_sales_history_location', Akses::semuaSlug());
    }

    public function test_riwayat_menerima_pilihan_awal_dari_query(): void
    {
        $this->get('/tracking_sales_history_location?sales_id=11&tanggal=2026-10-21')
            ->assertOk()->assertInertia(fn (Assert $p) => $p->where('pilihId', 11)->where('tanggal', '2026-10-21'));
        $this->get('/tracking_sales_history_location')
            ->assertOk()->assertInertia(fn (Assert $p) => $p->where('pilihId', null));

        $this->get('/tracking_koordinator_history_location?koordinator_id=137&tanggal=2026-10-21')
            ->assertOk()->assertInertia(fn (Assert $p) => $p->where('pilihId', 137));
        $this->get('/tracking_koordinator_history_location')
            ->assertOk()->assertInertia(fn (Assert $p) => $p->where('pilihId', null));
    }
}
