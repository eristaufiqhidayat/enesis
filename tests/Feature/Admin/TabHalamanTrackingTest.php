<?php

namespace Tests\Feature\Admin;

use App\Support\Akses\Akses;
use Inertia\Testing\AssertableInertia as Assert;

// Tab penghubung Last Seen <-> Ditandai (sales) dan Last Seen <-> Riwayat Lokasi (koordinator); riwayat sales kini modal.
class TabHalamanTrackingTest extends AdminTestCase
{
    public function test_sales_punya_dua_tab_tanpa_riwayat_dan_koordinator_dua_tab_di_semua_halamannya(): void
    {
        $sales = [
            ['slug' => 'tracking_sales', 'label' => 'Last Seen'],
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
            'motoris_ditandai' => ['lihat'],
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
        $this->assertSame(['motoris_ditandai'], $item['serumpun']);
    }

    public function test_hak_riwayat_sales_tidak_punya_entri_sidebar_dan_tidak_membuka_halaman(): void
    {
        $role = $this->role('Hanya Riwayat', 'semua', ['tracking_sales_history_location' => ['lihat']]);
        $akun = $this->akun('pemantau', 'koordinator', null, ['role_id' => $role]);

        $this->assertNotContains('tracking_sales_history_location', $this->slugSidebar($akun));
        $this->assertNotContains('tracking_sales_history_location', $this->slugSidebar($this->admin));
        $this->assertNotContains('tracking_sales_history_location', array_column(Akses::menuPencarian($this->admin), 'slug'));
    }

    public function test_pencarian_sidebar_tetap_menemukan_halaman_riwayat_koordinator(): void
    {
        $cari = collect(Akses::menuPencarian($this->admin))->pluck('title', 'slug');

        $this->assertSame('Tracking History Koordinator Location', $cari['tracking_koordinator_history_location']);
    }

    public function test_matriks_hak_role_tetap_memuat_hak_riwayat_sales(): void
    {
        $this->assertContains('tracking_sales', Akses::semuaSlug());
        $this->assertContains('tracking_sales_history_location', Akses::semuaSlug());
    }

    public function test_halaman_riwayat_sales_lama_dialihkan_ke_lacak_motoris(): void
    {
        $this->get('/tracking_sales_history_location?sales_id=11&tanggal=2026-10-21')->assertRedirect('/tracking_sales');
        $this->get('/tracking_sales_history_location/counts?tanggal=2026-10-21')->assertNotFound();
    }

    public function test_slug_riwayat_dibagikan_sesuai_hak_akun(): void
    {
        $this->get('/home')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('slugRiwayat', ['tracking_sales', 'tracking_sales_history_location', 'tracking_koordinator_history_location']));

        $role = $this->role('Hanya Last Seen', 'semua', ['tracking_sales' => ['lihat']]);
        $this->actingAs($this->akun('pemantau', 'koordinator', null, ['role_id' => $role]));
        $this->get('/tracking_sales')->assertOk()->assertInertia(fn (Assert $p) => $p->where('slugRiwayat', ['tracking_sales']));
    }

    public function test_riwayat_koordinator_menerima_pilihan_awal_dari_query(): void
    {
        $this->get('/tracking_koordinator_history_location?koordinator_id=137&tanggal=2026-10-21')
            ->assertOk()->assertInertia(fn (Assert $p) => $p->where('pilihId', 137));
        $this->get('/tracking_koordinator_history_location')
            ->assertOk()->assertInertia(fn (Assert $p) => $p->where('pilihId', null));
    }
}
