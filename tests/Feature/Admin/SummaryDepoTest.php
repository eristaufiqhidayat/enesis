<?php

namespace Tests\Feature\Admin;

use App\Support\Motoris\SummaryDepo;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

// Summary All Depo dari tabel motoris (absen, kunjungan, penjualan, join visit), rumus mengikuti Marketama.
class SummaryDepoTest extends AdminTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // 1–3 Okt 2026 = Kamis–Sabtu → hari kerja koordinator (Senin–Jumat) = 2.
        $this->travelTo(now()->setTimezone('Asia/Jakarta')->setDate(2026, 10, 3)->setTime(12, 0));
        DB::table('mk_distributor')->insert(['id' => 1, 'nama' => 'DEPO SUPRALITA 1', 'depo_id' => 46]);

        DB::table('trx_absensi')->insert([
            ['karyawan_id' => 1, 'depo_id' => 46, 'tanggal' => '2026-10-01'],
            ['karyawan_id' => 1, 'depo_id' => 46, 'tanggal' => '2026-10-02'],
            ['karyawan_id' => 3, 'depo_id' => 46, 'tanggal' => '2026-10-01'],
            ['karyawan_id' => 1, 'depo_id' => 46, 'tanggal' => '2026-09-30'], // di luar rentang
        ]);

        $kunjungan = fn (int $karyawan, string $tgl) => DB::table('trx_kunjungan')->insertGetId([
            'karyawan_id' => $karyawan, 'tanggal' => $tgl, 'checkin_at' => "{$tgl} 02:00:00",
        ]);
        // Rudi: absen 1–2 Okt + kunjungan 2–3 Okt → 3 hari kerja. Tegar: 1 hari. Joko (tidak aktif): kunjungan tanpa absen → 1 hari.
        $k = [$kunjungan(1, '2026-10-02'), $kunjungan(1, '2026-10-03'), $kunjungan(3, '2026-10-01'), $kunjungan(3, '2026-10-01'), $kunjungan(4, '2026-10-03')];

        $jual = fn (string $kode, int $kunj, int $karyawan, string $tgl, int $sku, int $total, int $poin, bool $ec, ?string $batal = null) => [
            'kode' => $kode, 'kunjungan_id' => $kunj, 'karyawan_id' => $karyawan, 'tanggal' => $tgl, 'jumlah_sku' => $sku,
            'total_qty' => $sku, 'total' => $total, 'poin' => $poin, 'is_effective_call' => $ec, 'dibatalkan_at' => $batal,
        ];
        DB::table('trx_penjualan')->insert([
            $jual('S1', $k[0], 1, '2026-10-02', 3, 100000, 10, true),
            $jual('S2', $k[1], 1, '2026-10-03', 1, 20000, 2, false),
            $jual('S3', $k[2], 3, '2026-10-01', 2, 50000, 5, true),
            $jual('S4', $k[3], 3, '2026-10-01', 9, 900000, 90, true, '2026-10-01 05:00:00'), // dibatalkan
        ]);

        $visit = fn (string $tgl) => ['koordinator_id' => 137, 'sales_id' => 1, 'depo_id' => 46, 'tanggal' => $tgl];
        DB::table('trx_join_visit')->insert([$visit('2026-10-01'), $visit('2026-10-02'), $visit('2026-10-02'), $visit('2026-09-29')]);
    }

    private function summary(): SummaryDepo
    {
        return new SummaryDepo('2026-10-01', '2026-10-03', $this->admin);
    }

    public function test_kartu_per_depo_mengikuti_rumus_marketama(): void
    {
        $kartu = $this->summary()->kartu();

        $this->assertSame(['Sidoarjo', 'Balaraja'], $kartu->pluck('nama')->all()); // urut EC Achievement
        $s = $kartu[0];

        $this->assertSame(['DEPO SUPRALITA 1', 1, 2], [$s->distributor, $s->total_sk, $s->total_motoris]);
        // Hari kerja depo 3 + 1 + 1 (Joko tidak aktif ikut) = 5; call 5; EC 2; SKU 6; point 17; sales Rp170.000.
        $this->assertSame([5, 5, 2, 170000, 6, 17], [$s->hari_kerja, $s->call, $s->ec, $s->sales, $s->sku, $s->point]);
        $this->assertSame([1.0, 0.4, 34000, 3.0, 3.4], [$s->avg_call, $s->avg_ec, $s->avg_sales, $s->avg_ipt, $s->avg_point]);
        $this->assertSame(2.5, $s->avg_hari_kerja); // 5 ÷ 2 sales aktif
        $this->assertSame([2.2, 1.1, 100.0], [$s->call_achievement, $s->ec_achievement, $s->ipt_achievement]);
        $this->assertSame([1, 3, 1.5], [$s->sk_visit, $s->visit_sk, $s->avg_visit_sk]); // 3 visit ÷ 2 hari koordinator

        $this->assertSame([0, 1, 0.0, 0.0], [$kartu[1]->hari_kerja, $kartu[1]->total_motoris, $kartu[1]->avg_call, $kartu[1]->ec_achievement]);
    }

    public function test_hari_kerja_koordinator_senin_sampai_jumat(): void
    {
        $this->assertSame(2, SummaryDepo::hariKerjaKoordinator('2026-10-01', '2026-10-03'));
        $this->assertSame(16, SummaryDepo::hariKerjaKoordinator('2026-10-01', '2026-10-22')); // sama dengan Marketama
    }

    public function test_detail_per_koordinator_dan_sales(): void
    {
        $d = $this->summary()->detail(46);

        $this->assertSame(5, $d['total']->hari_kerja);
        $this->assertCount(1, $d['tim']);
        $tim = $d['tim'][0];
        $this->assertSame(['Koor Satu', 1.5], [$tim->nama, $tim->avg_visit]);
        $this->assertSame(
            [['Rudi Hartono', 3, 2, 1, 120000, 0.7, 4.0], ['Tegar Mahendra', 1, 2, 1, 50000, 2.0, 2.0], ['Joko Lama', 1, 1, 0, 0, 1.0, 0.0]],
            $tim->sales->map(fn ($s) => [$s->nama, $s->hari_kerja, $s->call, $s->ec, $s->sales, $s->avg_call, $s->avg_ipt])->all(),
        );
        $this->assertSame([5, 5, 2], [$tim->akumulasi->hari_kerja, $tim->akumulasi->call, $tim->akumulasi->ec]);

        // Total dan tim ditunda (Inertia defer): tidak ada di respons awal, dimuat lewat partial reload.
        $this->get('/summary_all_depo/46?date_from=2026-10-01&date_to=2026-10-03')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Motoris/SummaryDepoDetail')
                ->where('depo.nama', 'Sidoarjo')
                ->missing('total')->missing('tim')
                ->loadDeferredProps(fn (Assert $page) => $page
                    ->where('total.hari_kerja', 5)
                    ->where('tim.0.nama', 'Koor Satu')
                    ->where('tim.0.avg_visit', 1.5)
                    ->where('tim.0.sales.0.nama', 'Rudi Hartono')
                    ->where('tim.0.sales.2.nama', 'Joko Lama')
                    ->where('tim.0.sales.2.aktif', false)
                    ->where('tim.0.akumulasi.hari_kerja', 5)));
        $this->get('/summary_all_depo/999')->assertNotFound();
    }

    public function test_detail_ec_dan_penjualan(): void
    {
        DB::table('mk_outlet')->insert(['id' => 7, 'kode' => 'OUT-7', 'nama' => 'Toko Rudi']);
        DB::table('trx_penjualan')->where('kode', 'S1')->update(['outlet_id' => 7]);

        $j = $this->summary()->penjualan(46);
        $this->assertSame(['ec' => 2, 'sales' => 170000, 'sku' => 6, 'point' => 17], (array) $j['total']);
        $this->assertSame(3, $j['baris']->total()); // S4 dibatalkan tidak tampil
        $this->assertSame(1, $this->summary()->penjualan(46, 'toko rudi')['baris']->total());

        // Total dan baris ditunda (Inertia defer), dimuat lewat partial reload.
        $this->get('/summary_all_depo/46/penjualan?date_from=2026-10-01&date_to=2026-10-03')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Motoris/SummaryDepoPenjualan')
                ->where('judul', 'Detail EC & Penjualan — Sidoarjo')
                ->missing('total')->missing('baris')
                ->loadDeferredProps(fn (Assert $page) => $page
                    ->where('total.ec', 2)->where('total.sales', 170000)->where('total.sku', 6)->where('total.point', 17)
                    ->where('baris.data.0.tanggal', '2026-10-01')->where('baris.data.0.sales', 'Tegar Mahendra')
                    ->where('baris.data.1.kode_outlet', 'OUT-7')->where('baris.data.1.nama_outlet', 'Toko Rudi')));
    }

    public function test_halaman_kartu_dan_cari_depo(): void
    {
        // Kartu ditunda (Inertia defer): halaman tampil dulu, kartu dimuat lewat partial reload.
        $this->get('/summary_all_depo?date_from=2026-10-01&date_to=2026-10-03')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Motoris/SummaryDepo')
                ->where('periode.dari', '01 Oktober 2026')->where('periode.sampai', '03 Oktober 2026')->where('periode.sama', false)
                ->where('target', ['call' => 45, 'ec' => 35, 'ipt' => 3])
                ->missing('kartu')
                ->where('bolehUbah', true)
                ->loadDeferredProps(fn (Assert $page) => $page
                    ->where('kartu.0.nama', 'Sidoarjo')->where('kartu.0.distributor', 'DEPO SUPRALITA 1')
                    ->where('kartu.0.sales', 170000)->where('kartu.0.avg_hari_kerja', 2.5)->where('kartu.0.call_achievement', 2.2)
                    ->where('kartu.1.nama', 'Balaraja')));

        $this->get('/summary_all_depo?date_from=2026-10-01&date_to=2026-10-03&q=bala')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->missing('kartu')
                ->loadDeferredProps(fn (Assert $page) => $page
                    ->where('kartu', fn ($kartu) => collect($kartu)->pluck('nama')->all() === ['Balaraja'])));
    }

    public function test_admin_mengubah_target_nasional(): void
    {
        $this->post('/summary_all_depo/target', ['call' => '2', 'ec' => '35', 'ipt' => '3', 'date_from' => '2026-10-01', 'date_to' => '2026-10-03'])
            ->assertRedirect(route('summary-depo', ['date_from' => '2026-10-01', 'date_to' => '2026-10-03']))
            ->assertSessionHas('sukses', 'Target nasional disimpan: Call 2, EC 35, IPT 3.');

        $this->assertSame(['call' => 2.0, 'ec' => 35.0, 'ipt' => 3.0], SummaryDepo::target());
        $this->assertSame(50.0, $this->summary()->kartu()->firstWhere('nama', 'Sidoarjo')->call_achievement);
        $this->assertDatabaseHas('riwayat_admin', ['objek' => 'target_nasional', 'aksi' => 'ubah']);

        $this->post('/summary_all_depo/target', ['call' => '2', 'ec' => '35', 'ipt' => '3'])->assertSessionHas('sukses', 'Target tidak berubah.');
        $this->post('/summary_all_depo/target', ['call' => '0', 'ec' => '35', 'ipt' => '3'])->assertSessionHasErrors('call');
    }

    public function test_cakupan_role_dan_hak_ubah_target(): void
    {
        $staf = $this->akun('staf.balaraja', 'lainnya', 2, [
            'role_id' => $this->role('Admin Depo', 'depo', ['summary_all_depo' => ['lihat']]),
        ]);
        $this->actingAs($staf);

        $this->get('/summary_all_depo?date_from=2026-10-01&date_to=2026-10-03')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('bolehUbah', false)
                ->missing('kartu')
                ->loadDeferredProps(fn (Assert $page) => $page
                    ->where('kartu', fn ($kartu) => collect($kartu)->pluck('nama')->all() === ['Balaraja'])));
        $this->get('/summary_all_depo/46')->assertNotFound();
        $this->post('/summary_all_depo/target', ['call' => '1', 'ec' => '1', 'ipt' => '1'])->assertForbidden();

        $lain = $this->akun('gudang', 'lainnya', 500, ['role_id' => $this->role('Gudang', 'semua', ['home' => ['lihat']])]);
        $this->actingAs($lain);
        $this->get('/summary_all_depo')->assertForbidden();
    }
}
