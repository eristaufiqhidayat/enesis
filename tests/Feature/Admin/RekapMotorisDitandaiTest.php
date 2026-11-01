<?php

namespace Tests\Feature\Admin;

use App\Support\Akses\Akses;
use App\Support\Backup\DaftarTabel;
use App\Support\Mobile\Pengaturan;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

// Melihat motoris yang ditandai: lencana "Ditandai" di Lacak Motoris, rekap "Motoris Ditandai" (tab ketiga grup Lacak
// Motoris), dan "Tanda di tanggal lain" pada endpoint tanda untuk drawer Riwayat.
class RekapMotorisDitandaiTest extends AdminTestCase
{
    private string $hari2;

    private string $hari5;

    private string $hari40;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->hari2, $this->hari5, $this->hari40] = [Pengaturan::sekarang()->subDays(2)->toDateString(), Pengaturan::sekarang()->subDays(5)->toDateString(), Pengaturan::sekarang()->subDays(40)->toDateString()];

        // Rudi (1): dua tanda aktif dalam jendela, satu di luar jendela, satu dicabut. Sinta (2): satu tanda aktif.
        $this->tanda(1, $this->hari2, 'lokasi_palsu', 'GPS loncat');
        $this->tanda(1, $this->hari5, 'penjualan_fiktif', null);
        $this->tanda(1, $this->hari40, 'kunjungan_tidak_wajar', 'Terlalu lama');
        $this->tanda(1, Pengaturan::sekarang()->subDays(3)->toDateString(), 'potensi_kecurangan', 'Dicabut', dicabut: true);
        $this->tanda(2, Pengaturan::sekarang()->subDay()->toDateString(), 'lokasi_palsu', 'Sinta ditandai');
    }

    private function tanda(int $sales, string $tanggal, string $kategori, ?string $catatan, bool $dicabut = false, string $oleh = 'koor'): int
    {
        return DB::table('tanda_motoris')->insertGetId([
            'karyawan_id' => $sales, 'tanggal' => $tanggal, 'kategori' => $kategori, 'catatan' => $catatan,
            'dibuat_oleh' => $this->admin->id, 'dibuat_nama' => $oleh, 'dicabut_at' => $dicabut ? '2026-10-30 04:00:00' : null,
            'created_at' => '2026-10-30 03:00:00', 'updated_at' => '2026-10-30 03:00:00',
        ]);
    }

    /** @return array<int, array<string, mixed>> baris tabel pertama sebuah halaman menu */
    private function baris(string $url): array
    {
        $baris = [];
        $this->get($url)->assertOk()->assertInertia(function (Assert $p) use (&$baris) {
            $p->loadDeferredProps(function (Assert $p) use (&$baris) {
                $baris = $p->toArray()['props']['tables'][0]['rows']['data'];
            });
        });

        return $baris;
    }

    // ---------------------------------------------------------------- Lencana "Ditandai"

    public function test_lacak_motoris_memuat_jumlah_dan_tanggal_tanda_aktif_dalam_30_hari(): void
    {
        $per = collect($this->baris('/tracking_sales'))->keyBy('nama');

        // Rudi: hari-2 dan hari-5 saja (hari-40 di luar jendela, satu lagi dicabut).
        $this->assertSame(2, $per['Rudi Hartono']['ditandai']);
        $this->assertSame($this->hari2, $per['Rudi Hartono']['ditandai_tanggal']);
        $this->assertSame(1, $per['Sinta Wulandari']['ditandai']);
        $this->assertSame(0, $per['Tegar Mahendra']['ditandai']);
        $this->assertNull($per['Tegar Mahendra']['ditandai_tanggal']);
    }

    public function test_kolom_ditandai_ada_di_tabel_sales_dan_tidak_di_koordinator(): void
    {
        $this->assertArrayHasKey('ditandai', config('marketama.tables.tracking_last_seen_sales.columns'));
        $this->assertArrayNotHasKey('ditandai', config('marketama.tables.tracking_last_seen_koordinator.columns'));
        foreach ($this->baris('/tracking_koordinator') as $baris) {
            $this->assertArrayNotHasKey('ditandai', $baris);
        }
    }

    public function test_jendela_hari_lencana_dapat_diubah_lewat_config(): void
    {
        config(['marketama.tanda_motoris_hari' => 3]);

        // Jendela 3 hari (hari ini, kemarin, dan hari-2): hanya tanda hari-2 yang masuk; hari-5 tidak.
        $this->assertSame(1, collect($this->baris('/tracking_sales'))->firstWhere('nama', 'Rudi Hartono')['ditandai']);
    }

    public function test_lencana_hanya_untuk_motoris_dalam_cakupan_role(): void
    {
        $koor = $this->akun('koor', 'koordinator', 137);
        $nama = collect($this->actingAs($koor)->baris('/tracking_sales'))->pluck('nama')->all();

        $this->assertContains('Rudi Hartono', $nama);
        $this->assertNotContains('Sinta Wulandari', $nama);
    }

    // ---------------------------------------------------------------- Rekap

    public function test_rekap_memuat_semua_tanda_termasuk_yang_dicabut_terbaru_dulu(): void
    {
        $baris = $this->baris('/motoris_ditandai');

        $this->assertCount(5, $baris);
        $this->assertSame(
            [Pengaturan::sekarang()->subDay()->toDateString(), $this->hari2, Pengaturan::sekarang()->subDays(3)->toDateString(), $this->hari5, $this->hari40],
            array_column($baris, 'tanggal')
        );
        $this->assertSame(['Aktif', 'Aktif', 'Dicabut', 'Aktif', 'Aktif'], array_column($baris, 'status'));
    }

    public function test_rekap_menampilkan_kolom_dengan_label_dan_jam_lokal(): void
    {
        $sinta = $this->baris('/motoris_ditandai')[0];

        $this->assertSame('Sinta Wulandari', $sinta['motoris']);
        $this->assertSame('Balaraja', $sinta['depo']);
        $this->assertNull($sinta['koordinator']);
        $this->assertSame('Lokasi / GPS palsu', $sinta['kategori']);
        $this->assertSame('Sinta ditandai', $sinta['catatan']);
        $this->assertSame('koor', $sinta['ditandai_oleh']);
        // 03:00 UTC = 10:00 WIB.
        $this->assertSame('2026-10-30 10:00', $sinta['ditandai_pada']);
        $this->assertSame(2, (int) $sinta['karyawan_id']);

        $rudi = $this->baris('/motoris_ditandai')[1];
        $this->assertSame('Rudi Hartono', $rudi['motoris']);
        $this->assertSame('Koor Satu', $rudi['koordinator']);
        $this->assertSame('Sidoarjo', $rudi['depo']);
    }

    public function test_rekap_dapat_dicari_dan_disaring_menurut_status(): void
    {
        $this->assertCount(4, $this->baris('/motoris_ditandai?q=Rudi'));
        $this->assertCount(1, $this->baris('/motoris_ditandai?q='.urlencode('GPS loncat')));
        $this->assertCount(1, $this->baris('/motoris_ditandai?q=Balaraja'));
        $this->assertCount(4, $this->baris('/motoris_ditandai?status=aktif'));
        $this->assertCount(1, $this->baris('/motoris_ditandai?status=dicabut'));
        $this->assertCount(5, $this->baris('/motoris_ditandai?status=tidak_dikenal'));
    }

    public function test_rekap_memuat_pilihan_status_untuk_dropdown(): void
    {
        $this->get('/motoris_ditandai')->assertOk()->assertInertia(fn (Assert $p) => $p->loadDeferredProps(fn (Assert $p) => $p
            ->where('tables.0.pilihan_status', ['aktif' => 'Aktif', 'dicabut' => 'Dicabut'])
            ->where('tables.0.columns.status', 'Status')
        ));
    }

    public function test_rekap_dibatasi_cakupan_role(): void
    {
        $koor = $this->akun('koor', 'koordinator', 137);

        $this->assertSame(['Rudi Hartono'], array_values(array_unique(array_column($this->actingAs($koor)->baris('/motoris_ditandai'), 'motoris'))));
        $this->assertCount(4, $this->actingAs($koor)->baris('/motoris_ditandai'));
    }

    public function test_akses_rekap_mengikuti_hak_menu(): void
    {
        $tanpa = $this->akun('tanpa', 'koordinator', 137, ['role_id' => $this->role('Tanpa Rekap', 'semua', ['tracking_sales' => ['lihat']])]);
        $dengan = $this->akun('dengan', 'koordinator', 137, ['role_id' => $this->role('Dengan Rekap', 'semua', ['motoris_ditandai' => ['lihat']])]);

        $this->actingAs($tanpa)->get('/motoris_ditandai')->assertForbidden();
        $this->actingAs($dengan)->get('/motoris_ditandai')->assertOk();
    }

    public function test_rekap_adalah_tab_kedua_dan_tidak_menambah_entri_sidebar(): void
    {
        $this->get('/motoris_ditandai')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('tabHalaman.1', ['slug' => 'motoris_ditandai', 'label' => 'Ditandai'])
            ->has('tabHalaman', 2)
        );

        $sidebar = collect(Akses::menu($this->admin))->flatMap(fn ($g) => $g['items'])->pluck('slug')->all();
        $this->assertContains('tracking_sales', $sidebar);
        $this->assertNotContains('motoris_ditandai', $sidebar);
        $this->assertContains('motoris_ditandai', collect(Akses::menuPencarian($this->admin))->pluck('slug')->all());
    }

    public function test_tabel_tanda_motoris_masuk_daftar_backup_data_pribadi(): void
    {
        $this->assertContains('tanda_motoris', DaftarTabel::PRIBADI);
    }

    // ---------------------------------------------------------------- Migration hak

    public function test_migration_memberi_lihat_rekap_ke_role_yang_boleh_membuka_lacak_motoris(): void
    {
        $boleh = $this->role('Boleh Lacak', 'semua', ['tracking_sales' => ['lihat']]);
        $tidak = $this->role('Tidak Lacak', 'semua', ['home' => ['lihat']]);
        DB::table('role_hak')->where('menu', 'motoris_ditandai')->delete();

        $migrasi = require database_path('migrations/2026_11_01_000003_beri_hak_lihat_motoris_ditandai.php');
        $migrasi->up();
        $migrasi->up(); // aman diulang

        $hak = DB::table('role_hak')->where('role_id', $boleh)->where('menu', 'motoris_ditandai')->get();
        $this->assertCount(1, $hak);
        $this->assertTrue((bool) $hak[0]->lihat);
        $this->assertFalse((bool) $hak[0]->tambah);
        $this->assertFalse(DB::table('role_hak')->where('role_id', $tidak)->where('menu', 'motoris_ditandai')->exists());
        $this->assertTrue(DB::table('role_hak')->where('role_id', $this->roleId('koordinator'))->where('menu', 'motoris_ditandai')->exists());

        $migrasi->down();
        $this->assertSame(0, DB::table('role_hak')->where('menu', 'motoris_ditandai')->count());
    }

    // ---------------------------------------------------------------- Tanda di tanggal lain

    private function lainnya(string $tanggal, int $sales = 1)
    {
        return $this->getJson("/tracking_sales/tanda?sales_id={$sales}&tanggal={$tanggal}")->assertOk();
    }

    public function test_tanda_tanggal_lain_hanya_yang_aktif_dalam_jendela_dan_bukan_tanggal_yang_dilihat(): void
    {
        $res = $this->lainnya($this->hari2);

        // Yang dilihat: tanda hari-2. Tanggal lain: hanya hari-5 (hari-40 di luar jendela, hari-3 dicabut).
        $this->assertCount(1, $res->json('tanda'));
        $this->assertSame([$this->hari5], array_column($res->json('lainnya'), 'tanggal'));
        $res->assertJsonPath('lainnya.0.kategori_label', 'Penjualan fiktif')->assertJsonPath('lainnya.0.oleh', 'koor');
    }

    public function test_tanda_tanggal_lain_urut_terbaru_dan_tidak_memuat_motoris_lain(): void
    {
        $res = $this->lainnya(Pengaturan::sekarang()->subDays(10)->toDateString());

        $this->assertSame([$this->hari2, $this->hari5], array_column($res->json('lainnya'), 'tanggal'));
        $this->assertNotContains('Sinta ditandai', array_column($res->json('lainnya'), 'catatan'));
    }

    public function test_jendela_tanda_tanggal_lain_mengikuti_config(): void
    {
        config(['marketama.tanda_motoris_hari' => 3]);

        // Jendela 3 hari: hanya tanda hari-2 yang tersisa (hari-5 dan hari-40 di luar jendela).
        $this->assertSame([$this->hari2], array_column($this->lainnya(Pengaturan::sekarang()->subDays(10)->toDateString())->json('lainnya'), 'tanggal'));
    }

    public function test_tanda_tanggal_lain_dibatasi_lima_puluh(): void
    {
        foreach (range(1, 60) as $i) {
            $this->tanda(3, Pengaturan::sekarang()->subDays(1 + $i % 20)->toDateString(), 'lokasi_palsu', "Tanda {$i}");
        }

        $this->assertCount(50, $this->lainnya(Pengaturan::sekarang()->subDays(25)->toDateString(), 3)->json('lainnya'));
    }

    public function test_tanda_tanggal_lain_menghormati_cakupan_role(): void
    {
        $koor = $this->akun('koor', 'koordinator', 137);

        // Sinta (2) bukan downline koordinator 137.
        $this->actingAs($koor)->getJson('/tracking_sales/tanda?sales_id=2&tanggal='.$this->hari2)->assertNotFound();
        $this->actingAs($koor)->getJson('/tracking_sales/tanda?sales_id=1&tanggal='.$this->hari2)->assertOk()->assertJsonCount(1, 'lainnya');
    }
}
