<?php

namespace Tests\Feature\Admin;

use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

// Beranda (home): dashboard interaktif ringkasan nasional data motoris. KPI nasional memakai rumus yang sama
// dengan Summary All Depo (lihat SummaryDepoTest) — fixture berikut sama persis, supaya totalnya bisa
// diverifikasi dari angka yang sudah dibuktikan di sana (kartu Sidoarjo + kartu Balaraja).
class HomeTest extends AdminTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // 1–3 Okt 2026 = Kamis–Sabtu.
        $this->travelTo(now()->setTimezone('Asia/Jakarta')->setDate(2026, 10, 3)->setTime(12, 0));

        DB::table('trx_absensi')->insert([
            ['karyawan_id' => 1, 'depo_id' => 46, 'tanggal' => '2026-10-01'],
            ['karyawan_id' => 1, 'depo_id' => 46, 'tanggal' => '2026-10-02'],
            ['karyawan_id' => 3, 'depo_id' => 46, 'tanggal' => '2026-10-01'],
        ]);

        $kunjungan = fn (int $karyawan, string $tgl) => DB::table('trx_kunjungan')->insertGetId([
            'karyawan_id' => $karyawan, 'tanggal' => $tgl, 'checkin_at' => "{$tgl} 02:00:00",
        ]);
        $k = [$kunjungan(1, '2026-10-02'), $kunjungan(1, '2026-10-03'), $kunjungan(3, '2026-10-01'), $kunjungan(3, '2026-10-01')];

        $jual = fn (string $kode, int $kunj, int $karyawan, string $tgl, int $sku, int $total, int $poin, bool $ec, ?string $batal = null) => [
            'kode' => $kode, 'kunjungan_id' => $kunj, 'karyawan_id' => $karyawan, 'tanggal' => $tgl, 'jumlah_sku' => $sku,
            'total_qty' => $sku, 'total' => $total, 'poin' => $poin, 'is_effective_call' => $ec, 'dibatalkan_at' => $batal,
        ];
        // Tanpa outlet_id (nullable): funnel outlet (NOO/AC/EC/RO/OSS) semuanya tetap 0 pada fixture ini,
        // karena definisi funnel hanya menghitung outlet yang tercatat lewat trx_kunjungan/trx_penjualan.outlet_id.
        DB::table('trx_penjualan')->insert([
            $jual('S1', $k[0], 1, '2026-10-02', 3, 100000, 10, true),
            $jual('S2', $k[1], 1, '2026-10-03', 1, 20000, 2, false),
            $jual('S3', $k[2], 3, '2026-10-01', 2, 50000, 5, true),
            $jual('S4', $k[3], 3, '2026-10-01', 9, 900000, 90, true, '2026-10-01 05:00:00'), // dibatalkan, tidak ikut dihitung
        ]);
    }

    public function test_beranda_dirender_dengan_filter_bawaan_7_hari_dan_ringkasan_ditunda(): void
    {
        $this->get('/home')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Home/Dashboard')
                ->where('filter.date_from', now()->subDays(6)->toDateString())
                ->where('filter.date_to', now()->toDateString())
                // Berat (ringkasanDepo/funnel/tren); belum ada di payload awal, lihat <Deferred> di Home/Dashboard.vue.
                ->missing('ringkasanDepo')->missing('funnel')->missing('tren')
            );
    }

    public function test_kpi_nasional_funnel_dan_tren_dihitung_dari_tabel_motoris(): void
    {
        $this->get('/home?date_from=2026-10-01&date_to=2026-10-03')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Home/Dashboard')
                ->loadDeferredProps(fn (Assert $page) => $page
                    // Sidoarjo (Rudi: hari 3, call 2, ec 1, sales 120000, sku 4; Tegar: hari 1 — 2 kunjungan tanggal
                    // sama digabung UNION, call 2, ec 1, sales 50000, sku 2) + Balaraja (semua 0, Sinta tanpa transaksi).
                    ->where('ringkasanDepo.kpi.depo_aktif', 2)
                    ->where('ringkasanDepo.kpi.motoris_aktif', 3) // Rudi + Tegar (Sidoarjo) + Sinta (Balaraja, aktif tanpa transaksi)
                    ->where('ringkasanDepo.kpi.hari_kerja', 4)
                    ->where('ringkasanDepo.kpi.call', 4)
                    ->where('ringkasanDepo.kpi.ec', 2)
                    ->where('ringkasanDepo.kpi.sales', 170000)
                    ->where('ringkasanDepo.kpi.sku', 6)
                    ->where('ringkasanDepo.kpi.avg_call', 1)
                    ->where('ringkasanDepo.kpi.avg_ec', 0.5)
                    ->where('ringkasanDepo.kpi.avg_ipt', 3)
                    ->where('ringkasanDepo.kpi.ec_achievement', 1.4) // 0.5 × 100 ÷ 35
                    ->where('ringkasanDepo.kpi.ipt_achievement', 100) // 3.0 × 100 ÷ 3
                    ->has('ringkasanDepo.topDepo', 2)
                    // Tidak ada data mk_outlet/trx_outlet_baru/trx_stok_outlet pada fixture ini.
                    ->where('funnel', fn ($funnel) => collect($funnel)->pluck('jumlah')->every(fn ($n) => $n === 0))
                    ->has('tren', 3)
                    ->where('tren.0', ['tanggal' => '2026-10-01', 'sales' => 50000, 'ec' => 1]) // S3 (S4 dibatalkan)
                    ->where('tren.1', ['tanggal' => '2026-10-02', 'sales' => 100000, 'ec' => 1]) // S1
                    ->where('tren.2', ['tanggal' => '2026-10-03', 'sales' => 20000, 'ec' => 0]) // S2, bukan EC
                )
            );
    }

    public function test_akun_tanpa_hak_home_ditolak(): void
    {
        $staf = $this->akun('gudang', 'lainnya', 500, ['role_id' => $this->role('Gudang', 'semua', ['summary_all_depo' => ['lihat']])]);
        $this->actingAs($staf);

        $this->get('/home')->assertForbidden();
    }
}
