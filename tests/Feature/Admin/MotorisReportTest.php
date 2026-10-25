<?php

namespace Tests\Feature\Admin;

use App\Support\Motoris\KuotaMotoris;
use App\Support\Motoris\LaporanMotoris;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

// Motoris Report (Coverage, Performance, Absensi) dari tabel motoris + master Kuota Motoris isian admin.
class MotorisReportTest extends AdminTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // 1–10 Okt 2026; tanpa waktu_aktif_app & libur → semua hari = hari kerja (HK standar 10).
        $this->travelTo(now()->setTimezone('Asia/Jakarta')->setDate(2026, 10, 10)->setTime(12, 0));

        $data = fn (string $kota, int $kuota, ?int $call = null) => json_encode(array_filter(
            ['kota_besar' => $kota, 'kuota_sales_motoris' => (string) $kuota, 'target_call' => $call === null ? null : (string) $call],
            fn ($v) => $v !== null,
        ));
        DB::table('mk_depo')->where('id', 46)->update(['aktif' => true, 'data' => $data('Surabaya', 30, 45)]);
        DB::table('mk_depo')->where('id', 22)->update(['aktif' => true, 'data' => $data('Bodetabekten', 15, 40)]);
        DB::table('mk_depo')->insert([
            ['id' => 23, 'nama' => 'Gresik', 'aktif' => true, 'data' => $data('Surabaya', 15)],
            ['id' => 24, 'nama' => 'Plossa Kopo', 'aktif' => false, 'data' => json_encode(['kota_besar' => '-', 'kuota_sales_motoris' => '0'])],
        ]);

        DB::table('trx_absensi')->insert([
            ...array_map(fn ($d) => ['karyawan_id' => 1, 'depo_id' => 46, 'tanggal' => "2026-10-0{$d}"], [1, 2, 3]),
            ['karyawan_id' => 3, 'depo_id' => 46, 'tanggal' => '2026-10-01'],
            ['karyawan_id' => 2, 'depo_id' => 22, 'tanggal' => '2026-10-05'],
            ['karyawan_id' => 137, 'depo_id' => 46, 'tanggal' => '2026-10-01'], // koordinator: tidak dihitung
            ['karyawan_id' => 1, 'depo_id' => 46, 'tanggal' => '2026-09-30'],  // bulan lalu
        ]);

        $kunjungan = fn (int $karyawan, string $tgl) => DB::table('trx_kunjungan')->insertGetId([
            'karyawan_id' => $karyawan, 'tanggal' => $tgl, 'checkin_at' => "{$tgl} 02:00:00",
        ]);
        $k = [$kunjungan(1, '2026-10-01'), $kunjungan(1, '2026-10-02'), $kunjungan(1, '2026-10-02'), $kunjungan(1, '2026-10-03'), $kunjungan(2, '2026-10-05')];
        $kunjungan(1, '2026-09-30');

        $jual = fn (string $kode, int $kunj, int $karyawan, string $tgl, int $sku, int $total, bool $ec, ?string $batal = null) => [
            'kode' => $kode, 'kunjungan_id' => $kunj, 'karyawan_id' => $karyawan, 'tanggal' => $tgl, 'jumlah_sku' => $sku,
            'total_qty' => $sku, 'total' => $total, 'is_effective_call' => $ec, 'dibatalkan_at' => $batal,
        ];
        DB::table('trx_penjualan')->insert([
            $jual('S1', $k[0], 1, '2026-10-01', 3, 100000, true),
            $jual('S2', $k[1], 1, '2026-10-02', 1, 50000, false),
            $jual('S3', $k[2], 1, '2026-10-02', 5, 999000, true, '2026-10-02 05:00:00'), // dibatalkan
            $jual('S4', $k[4], 2, '2026-10-05', 2, 40000, true),
        ]);
    }

    public function test_coverage_per_kota_dengan_baris_total(): void
    {
        $c = (new LaporanMotoris('2026-10', $this->admin))->coverage();

        $this->assertSame(['Bodetabekten', 'Surabaya'], $c['baris']->pluck('kota')->all());
        [$bodetabek, $surabaya] = $c['baris']->all();

        // Surabaya = Sidoarjo (30) + Gresik (15); aktif Rudi & Tegar (Joko nonaktif); transaksi hanya Rudi.
        $this->assertEquals((object) ['kota' => 'Surabaya', 'kuota' => 45, 'sudah_transaksi' => 1, 'aktif' => 2, 'vacant' => 43, 'coverage' => 4.4], $surabaya);
        $this->assertSame([15, 1, 1, 14, 6.7], [$bodetabek->kuota, $bodetabek->sudah_transaksi, $bodetabek->aktif, $bodetabek->vacant, $bodetabek->coverage]);

        // Depo nonaktif tanpa kota & kuota tidak ikut; total = penjumlahan kota yang tampil.
        $this->assertEquals((object) ['kota' => 'TOTAL 2 CITY', 'kuota' => 60, 'sudah_transaksi' => 2, 'aktif' => 3, 'vacant' => 57, 'coverage' => 5.0], $c['total']);
    }

    public function test_performance_memakai_hk_dan_target_depo_aplikasi_hp(): void
    {
        $laporan = new LaporanMotoris('2026-10', $this->admin);
        $p = $laporan->performance();
        $surabaya = $p['baris']->firstWhere('kota', 'Surabaya');
        $bodetabek = $p['baris']->firstWhere('kota', 'Bodetabekten');

        $this->assertSame(10, $laporan->hkStandar);
        // Target = target call depo (45) × HK 10 × 2 motoris aktif di Sidoarjo; Gresik tanpa sales = 0.
        $this->assertSame(
            ['hk_standar' => 10, 'act_hk' => 2.0, 'target' => 900, 'call' => 4, 'ec' => 1, 'ipt' => 2.0, 'value' => 150000],
            array_intersect_key((array) $surabaya, array_flip(['hk_standar', 'act_hk', 'target', 'call', 'ec', 'ipt', 'value'])),
        );
        $this->assertSame([1.0, 400, 1, 1, 2.0, 40000], [$bodetabek->act_hk, $bodetabek->target, $bodetabek->call, $bodetabek->ec, $bodetabek->ipt, $bodetabek->value]);
        $this->assertSame([1300, 5, 2, 2.0, 190000], [$p['total']->target, $p['total']->call, $p['total']->ec, $p['total']->ipt, $p['total']->value]);
        $this->assertSame(1.7, $p['total']->act_hk); // 5 hari hadir ÷ 3 motoris yang absen
    }

    public function test_halaman_motoris_report_tiga_tab(): void
    {
        $this->get('/motoris_report?bulan=2026-10')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Motoris/Report')
                ->where('tab', 'coverage')
                ->missing('laporan')
                ->loadDeferredProps(fn (Assert $page) => $page
                    ->where('laporan.baris.0.kota', 'Bodetabekten')
                    ->where('laporan.baris.1.kota', 'Surabaya')
                    ->where('laporan.baris.0.coverage', 6.7)
                    ->where('laporan.total.kota', 'TOTAL 2 CITY')));

        $this->get('/motoris_report?tab=performance&bulan=2026-10')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Motoris/Report')
                ->where('tab', 'performance')
                ->missing('laporan')
                ->loadDeferredProps(fn (Assert $page) => $page
                    ->where('laporan.baris.1.value', 150000)
                    ->where('laporan.total.value', 190000)));

        $this->get('/motoris_report?tab=absensi&bulan=2026-10')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Motoris/Report')
                ->where('tab', 'absensi')
                ->missing('laporan')
                ->loadDeferredProps(fn (Assert $page) => $page
                    ->where('laporan.baris.data', function ($baris) {
                        $nama = collect($baris)->pluck('nama')->all();

                        return array_values(array_intersect($nama, ['Sinta Wulandari', 'Rudi Hartono', 'Tegar Mahendra'])) === ['Sinta Wulandari', 'Rudi Hartono', 'Tegar Mahendra']
                            && ! in_array('Joko Lama', $nama, true);
                    })));

        // Slug lama tetap diarahkan ke tab yang sesuai.
        $this->get('/motoris_performance')->assertRedirect('/motoris_report?tab=performance');
    }

    public function test_absensi_hari_kerja_hadir_dan_tidak_hadir(): void
    {
        $baris = (new LaporanMotoris('2026-10', $this->admin))->absensi(['depo' => 46, 'koordinator' => null, 'q' => ''])->getCollection();

        $this->assertSame(
            [['Rudi Hartono', 10, 3, 7], ['Tegar Mahendra', 10, 1, 9]],
            $baris->map(fn ($b) => [$b->nama, $b->hari_kerja, $b->hadir, $b->tidak_hadir])->all(),
        );
    }

    public function test_admin_mengisi_kuota_berlaku_mulai_bulan(): void
    {
        $this->get('/kuota_motoris?bulan=2026-10')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Motoris/Kuota')
                ->where('bolehUbah', true)
                ->missing('depo')->missing('riwayat')
                ->loadDeferredProps(fn (Assert $page) => $page
                    ->where('depo', fn ($depo) => collect($depo)->contains(fn ($d) => $d['id'] === 46 && $d['sumber'] === 'marketama'))
                    ->has('riwayat')));

        $this->post('/kuota_motoris', ['bulan' => '2026-10', 'kuota' => [46 => '5', 22 => '15', 23 => '15']])
            ->assertRedirect(route('kuota-motoris.index', ['bulan' => '2026-10']))
            ->assertSessionHas('sukses', 'Kuota 3 depo disimpan, berlaku mulai Oktober 2026.');

        $this->assertSame(5, KuotaMotoris::untukBulan('2026-12')->firstWhere('id', 46)->kuota);   // berlaku ke bulan berikutnya
        $this->assertSame(30, KuotaMotoris::untukBulan('2026-09')->firstWhere('id', 46)->kuota);  // bulan sebelumnya tetap Marketama
        $this->assertDatabaseHas('riwayat_admin', ['objek' => 'kuota_motoris', 'objek_id' => 46, 'aksi' => 'ubah']);

        $c = (new LaporanMotoris('2026-10', $this->admin))->coverage();
        $this->assertSame(20, $c['baris']->firstWhere('kota', 'Surabaya')->kuota);

        // Simpan lagi tanpa perubahan; lalu kosongkan Sidoarjo → kembali ke kuota Marketama.
        $this->post('/kuota_motoris', ['bulan' => '2026-10', 'kuota' => [46 => '5']])->assertSessionHas('sukses', 'Tidak ada kuota yang berubah.');
        $this->post('/kuota_motoris', ['bulan' => '2026-10', 'kuota' => [46 => '']]);
        $this->assertSame(30, KuotaMotoris::untukBulan('2026-10')->firstWhere('id', 46)->kuota);

        $this->post('/kuota_motoris', ['bulan' => '2026-10', 'kuota' => [46 => '-1']])->assertSessionHasErrors('kuota.46');
    }

    public function test_cakupan_role_dan_hak_ubah_kuota(): void
    {
        // Admin depo Balaraja: hanya kota depo-nya; boleh lihat kuota tapi tidak mengubah.
        $staf = $this->akun('staf.balaraja', 'lainnya', 2, [
            'role_id' => $this->role('Admin Depo', 'depo', ['motoris_report' => ['lihat'], 'kuota_motoris' => ['lihat']]),
        ]);
        $this->actingAs($staf);

        $this->get('/motoris_report?bulan=2026-10')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->missing('laporan')
                ->loadDeferredProps(fn (Assert $page) => $page
                    ->where('laporan.total.kota', 'TOTAL 1 CITY')
                    ->where('laporan.baris', fn ($baris) => ! collect($baris)->contains(fn ($b) => $b['kota'] === 'Surabaya'))));
        $this->get('/kuota_motoris?bulan=2026-10')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('bolehUbah', false)
                ->missing('depo')
                ->loadDeferredProps(fn (Assert $page) => $page
                    ->where('depo', fn ($depo) => collect($depo)->pluck('nama')->contains('Balaraja')
                        && ! collect($depo)->contains(fn ($d) => $d['id'] === 46))));
        $this->post('/kuota_motoris', ['bulan' => '2026-10', 'kuota' => [22 => '3']])->assertForbidden();

        $lain = $this->akun('gudang', 'lainnya', 500, ['role_id' => $this->role('Gudang', 'semua', ['home' => ['lihat']])]);
        $this->actingAs($lain);
        $this->get('/motoris_report')->assertForbidden();
        $this->get('/kuota_motoris')->assertForbidden();
    }
}
