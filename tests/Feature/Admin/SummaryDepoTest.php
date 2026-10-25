<?php

namespace Tests\Feature\Admin;

use App\Support\Motoris\SummaryDepo;
use Illuminate\Support\Facades\DB;

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

        $this->get('/summary_all_depo/46?date_from=2026-10-01&date_to=2026-10-03')->assertOk()
            ->assertSeeInOrder(['Total keseluruhan depo', 'Koor Satu', 'AVG Visit / Day:', '1,5', 'Rudi Hartono', 'Joko Lama', 'Non-aktif', 'Akumulasi']);
        // Modal memuat potongan HTML tanpa kerangka halaman.
        $this->get('/summary_all_depo/46?date_from=2026-10-01&date_to=2026-10-03&partial=1')->assertOk()
            ->assertSee('Koor Satu')->assertDontSee('<html', false);
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

        $this->get('/summary_all_depo/46/penjualan?date_from=2026-10-01&date_to=2026-10-03&partial=1')->assertOk()
            ->assertSeeInOrder(['Total EC: 2', 'Total Sales: Rp 170.000', 'Total SKU: 6', 'Total Point: 17', '01/10/2026', 'Tegar Mahendra', '02/10/2026', 'OUT-7', 'Toko Rudi'])
            ->assertDontSee('<html', false);
        $this->get('/summary_all_depo/46/penjualan?date_from=2026-10-01&date_to=2026-10-03')->assertOk()->assertSee('Detail EC &amp; Penjualan — Sidoarjo', false);
    }

    public function test_halaman_kartu_dan_cari_depo(): void
    {
        $this->get('/summary_all_depo?date_from=2026-10-01&date_to=2026-10-03')->assertOk()
            ->assertSee('01 Oktober 2026 – 03 Oktober 2026')->assertSee('Call 45, EC 35, IPT 3')
            ->assertSeeInOrder(['Sidoarjo', 'DEPO SUPRALITA 1', 'Total Sales Motoris', 'Rp 170.000', 'AVG Hari Kerja', '2,5', 'Call Achievement', '2,2%', 'Lihat Detail', 'Balaraja'])
            ->assertSee('data-muat="'.e(route('summary-depo.detail', ['depo' => 46, 'date_from' => '2026-10-01', 'date_to' => '2026-10-03'])).'"', false)
            ->assertSee('Chart Per Depo')->assertSee('Setting Target');

        $this->get('/summary_all_depo?date_from=2026-10-01&date_to=2026-10-03&q=bala')->assertOk()
            ->assertSee('Balaraja')->assertDontSee('DEPO SUPRALITA 1');
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
            ->assertSee('Balaraja')->assertDontSee('Sidoarjo')->assertDontSee('Simpan target');
        $this->get('/summary_all_depo/46')->assertNotFound();
        $this->post('/summary_all_depo/target', ['call' => '1', 'ec' => '1', 'ipt' => '1'])->assertForbidden();

        $lain = $this->akun('gudang', 'lainnya', 500, ['role_id' => $this->role('Gudang', 'semua', ['home' => ['lihat']])]);
        $this->actingAs($lain);
        $this->get('/summary_all_depo')->assertForbidden();
    }
}
