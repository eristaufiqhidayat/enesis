<?php

namespace Tests\Feature\Admin;

use App\Support\Motoris\RekapPencapaianSku;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

// Rekap Pencapaian SKU (Per Motoris & Per Depo) dari penjualan aplikasi motoris, rumus mengikuti Marketama.
class RekapPencapaianSkuTest extends AdminTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tanpa waktu_aktif_app & libur → 1–3 Okt 2026 = 3 hari kerja.
        $this->travelTo(now()->setTimezone('Asia/Jakarta')->setDate(2026, 10, 3)->setTime(12, 0));
        DB::table('mk_produk')->insert([['id' => 10, 'nama' => 'Adem Sari Can'], ['id' => 11, 'nama' => 'Plossa Sachet']]);

        $no = 0;
        $jual = function (int $karyawan, string $tgl, array $item, ?string $batal = null) use (&$no) {
            $id = DB::table('trx_penjualan')->insertGetId([
                'kode' => 'S'.(++$no), 'kunjungan_id' => $no, 'karyawan_id' => $karyawan, 'tanggal' => $tgl,
                'jumlah_sku' => count($item), 'total_qty' => array_sum($item), 'total' => 1000, 'is_effective_call' => true, 'dibatalkan_at' => $batal,
            ]);
            foreach ($item as $produk => $qty) {
                DB::table('trx_penjualan_item')->insert(['penjualan_id' => $id, 'produk_id' => $produk, 'qty' => $qty, 'harga' => 100, 'total' => 100]);
            }
        };

        $jual(1, '2026-10-01', [10 => 20, 11 => 5.5]);   // Rudi
        $jual(1, '2026-10-02', [10 => 30]);
        $jual(1, '2026-09-30', [10 => 50]);              // di luar rentang
        $jual(3, '2026-10-01', [10 => 3]);               // Tegar
        $jual(3, '2026-10-02', [10 => 100], '2026-10-02 05:00:00'); // dibatalkan
        $jual(4, '2026-10-03', [11 => 9]);               // Joko (tidak aktif) tetap dihitung karena menjual
    }

    private function rekap(array $depo = [], array $produk = []): RekapPencapaianSku
    {
        return new RekapPencapaianSku('2026-10-01', '2026-10-03', $depo, $produk, $this->admin);
    }

    public function test_per_motoris_qty_dibagi_hari_kerja_standar(): void
    {
        $r = $this->rekap();
        $hasil = $r->perMotoris();

        $this->assertSame(3, $r->hkStandar);
        $this->assertSame(4, $hasil['jumlah']);
        $this->assertSame([['Rudi Hartono', 55.5, 18.5]], $hasil['tercapai']['baris']->map(fn ($b) => [$b->nama, $b->qty, $b->avg])->all());
        $this->assertSame(
            [['Joko Lama', 9.0, 3.0], ['Tegar Mahendra', 3.0, 1.0], ['Sinta Wulandari', 0.0, 0.0]],
            $hasil['belum']['baris']->map(fn ($b) => [$b->nama, $b->qty, $b->avg])->all(),
        );
        // TOTAL = Σ qty ÷ (jumlah motoris di tabel × HK).
        $this->assertSame(18.5, $hasil['tercapai']['total']->avg);
        $this->assertSame([12.0, 1.33], [$hasil['belum']['total']->qty, $hasil['belum']['total']->avg]);
    }

    public function test_per_depo_qty_dibagi_hari_motoris_bertransaksi(): void
    {
        $hasil = $this->rekap()->perDepo();

        // Sidoarjo: 67,5 ÷ 4 hari-motoris (Rudi 2, Tegar 1, Joko 1); Balaraja punya sales aktif tanpa penjualan.
        $this->assertSame([['Sidoarjo', 67.5, 4, 16.88]], $hasil['tercapai']['baris']->map(fn ($b) => [$b->nama, $b->qty, $b->hari_motoris, $b->avg])->all());
        $this->assertSame([['Balaraja', 0.0, 0, 0.0]], $hasil['belum']['baris']->map(fn ($b) => [$b->nama, $b->qty, $b->hari_motoris, $b->avg])->all());
        $this->assertSame(3, $hasil['tercapai']['baris'][0]->hari_kerja);
        $this->assertSame(16.88, $hasil['tercapai']['total']->avg);
    }

    public function test_filter_produk_dan_depo(): void
    {
        $depo = $this->rekap([], [11])->perDepo();
        $this->assertSame([14.5, 2, 7.25], [$depo['tercapai']['baris'][0]->qty, $depo['tercapai']['baris'][0]->hari_motoris, $depo['tercapai']['baris'][0]->avg]);

        $motoris = $this->rekap([], [11])->perMotoris();
        $this->assertSame([], $motoris['tercapai']['baris']->all());
        $this->assertSame(1.83, $motoris['belum']['baris']->firstWhere('nama', 'Rudi Hartono')->avg);

        $balaraja = $this->rekap([22])->perMotoris();
        $this->assertSame(['Sinta Wulandari'], $balaraja['belum']['baris']->pluck('nama')->all());
        $this->assertSame(1, $balaraja['jumlah']);
    }

    public function test_halaman_dua_tab_dan_slug_lama(): void
    {
        // Hasil rekap ditunda (Inertia defer): tidak ada di respons awal, dimuat lewat partial reload.
        $this->get('/rekap_pencapaian_sku?date_from=2026-10-01&date_to=2026-10-03')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Motoris/RekapSku')
                ->where('tab', 'motoris')
                ->where('periode.hkStandar', 3)
                ->where('periode.target', 5)
                ->missing('hasil')
                ->loadDeferredProps(fn (Assert $page) => $page
                    ->where('hasil.tercapai.baris.0.nama', 'Rudi Hartono')
                    ->where('hasil.tercapai.baris.0.qty', 55.5)
                    ->where('hasil.tercapai.baris.0.avg', 18.5)
                    ->where('hasil.belum.baris.0.nama', 'Joko Lama')
                    ->where('hasil.belum.baris.0.aktif', false)));

        $this->get('/rekap_pencapaian_sku?tab=depo&date_from=2026-10-01&date_to=2026-10-03')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Motoris/RekapSku')
                ->where('tab', 'depo')
                ->missing('hasil')
                ->loadDeferredProps(fn (Assert $page) => $page
                    ->where('hasil.tercapai.baris.0.nama', 'Sidoarjo')
                    ->where('hasil.tercapai.baris.0.qty', 67.5)
                    ->where('hasil.tercapai.baris.0.avg', 16.88)
                    ->where('hasil.belum.baris.0.nama', 'Balaraja')));

        // Tautan lama Marketama (nama parameter sama) diarahkan ke tab yang sesuai.
        $this->get('/rekap_pencapaian_depo_sku?date_from=2026-10-01&warehouse_id%5B0%5D=46')
            ->assertRedirect('/rekap_pencapaian_sku?date_from=2026-10-01&warehouse_id%5B0%5D=46&tab=depo');
    }

    public function test_admin_mengubah_target_global(): void
    {
        $this->post('/rekap_pencapaian_sku/target', ['target' => '20', 'tab' => 'depo', 'date_from' => '2026-10-01', 'date_to' => '2026-10-03'])
            ->assertRedirect(route('rekap-sku', ['tab' => 'depo', 'date_from' => '2026-10-01', 'date_to' => '2026-10-03']))
            ->assertSessionHas('sukses', 'Target disimpan: 20 /hari.');

        $this->assertSame(20.0, RekapPencapaianSku::target());
        $this->assertDatabaseHas('riwayat_admin', ['objek' => 'target_rekap_sku', 'aksi' => 'ubah']);
        // Sidoarjo 16,88 kini di bawah target; berlaku juga untuk tab motoris.
        $this->assertSame([], $this->rekap()->perDepo()['tercapai']['baris']->all());
        $this->assertSame([], $this->rekap()->perMotoris()['tercapai']['baris']->all());

        $this->post('/rekap_pencapaian_sku/target', ['target' => '20'])->assertSessionHas('sukses', 'Target tidak berubah.');
        $this->post('/rekap_pencapaian_sku/target', ['target' => '0'])->assertSessionHasErrors('target');
    }

    public function test_cakupan_role_dan_hak_ubah_target(): void
    {
        $staf = $this->akun('staf.balaraja', 'lainnya', 2, [
            'role_id' => $this->role('Admin Depo', 'depo', ['rekap_pencapaian_sku' => ['lihat']]),
        ]);
        $this->actingAs($staf);

        $this->get('/rekap_pencapaian_sku?tab=depo&date_from=2026-10-01&date_to=2026-10-03')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('bolehUbah', false)
                ->missing('hasil')
                ->loadDeferredProps(fn (Assert $page) => $page
                    ->where('hasil.belum.baris', fn ($baris) => collect($baris)->pluck('nama')->contains('Balaraja')
                        && ! collect($baris)->pluck('nama')->contains('Sidoarjo'))));
        // Depo di luar cakupan diabaikan walau dikirim lewat filter.
        $this->get('/rekap_pencapaian_sku?date_from=2026-10-01&date_to=2026-10-03&warehouse_id[]=46')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->missing('hasil')
                ->loadDeferredProps(fn (Assert $page) => $page
                    ->where('hasil.tercapai.baris', fn ($baris) => ! collect($baris)->pluck('nama')->contains('Rudi Hartono'))
                    ->where('hasil.belum.baris', fn ($baris) => ! collect($baris)->pluck('nama')->contains('Rudi Hartono'))));
        $this->post('/rekap_pencapaian_sku/target', ['target' => '3'])->assertForbidden();

        $lain = $this->akun('gudang', 'lainnya', 500, ['role_id' => $this->role('Gudang', 'semua', ['home' => ['lihat']])]);
        $this->actingAs($lain);
        $this->get('/rekap_pencapaian_sku')->assertForbidden();
    }
}
