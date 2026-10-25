<?php

namespace Tests\Feature\Admin;

use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

// Monitoring Harian Sales › Sales Checkin Outlet Pagi dari trx_kunjungan (sama dengan aturan Marketama):
// sales aktif tanpa checkin outlet jam 09:00–<11:00 WIB pada tanggal terpilih.
class CheckinPagiTest extends AdminTestCase
{
    private const URL = '/monitoring_harian_sales?tab=checkin_pagi&tanggal=2026-10-22';

    protected function setUp(): void
    {
        parent::setUp();

        // Karyawan dasar (AdminTestCase): Rudi(1) & Tegar(3) depo Sidoarjo koor 137, Sinta(2) Balaraja, Joko(4) nonaktif.
        DB::table('mk_karyawan')->insert([
            ['id' => 5, 'kategori' => 'sales', 'nama' => 'Wati Lestari', 'depo_id' => 46, 'koordinator_id' => 137, 'aktif' => true],
            ['id' => 6, 'kategori' => 'sales', 'nama' => 'Fajar Nugroho', 'depo_id' => 22, 'koordinator_id' => null, 'aktif' => true],
        ]);

        // Jam disimpan UTC; WIB = UTC + 7.
        $kunjungan = fn (int $karyawan, string $tanggal, string $utc) => [
            'karyawan_id' => $karyawan, 'tanggal' => $tanggal, 'checkin_at' => $utc,
        ];
        DB::table('trx_kunjungan')->insert([
            $kunjungan(1, '2026-10-22', '2026-10-22 02:30:00'), // 09:30 dalam rentang
            $kunjungan(3, '2026-10-22', '2026-10-22 05:00:00'), // 12:00
            $kunjungan(3, '2026-10-22', '2026-10-22 01:44:00'), // 08:44 = checkin pertama, di luar rentang
            $kunjungan(5, '2026-10-22', '2026-10-22 04:00:00'), // tepat 11:00 = di luar rentang
            $kunjungan(6, '2026-10-22', '2026-10-22 02:00:00'), // tepat 09:00 = dalam rentang
            $kunjungan(2, '2026-10-21', '2026-10-21 02:30:00'), // hari lain
            $kunjungan(137, '2026-10-22', '2026-10-22 01:00:00'), // koordinator: bukan sales
        ]);
    }

    public function test_daftar_sales_belum_checkin_pagi_dan_kpi(): void
    {
        $this->get(self::URL)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('tables.0.rows.total', 3)
            // Urut depo lalu nama; nomor urut diisi.
            ->where('tables.0.rows.data.0.no', 1)
            ->where('tables.0.rows.data.0.depo', 'Balaraja')
            ->where('tables.0.rows.data.0.nama_sales', 'Sinta Wulandari')
            ->where('tables.0.rows.data.0.checkin_pertama_hari_ini', 'Belum checkin')
            ->where('tables.0.rows.data.1.nama_sales', 'Tegar Mahendra')
            ->where('tables.0.rows.data.1.koordinator', 'Koor Satu')
            ->where('tables.0.rows.data.1.checkin_pertama_hari_ini', '08:44 (di luar rentang waktu)')
            ->where('tables.0.rows.data.2.nama_sales', 'Wati Lestari')
            ->where('tables.0.rows.data.2.checkin_pertama_hari_ini', '11:00 (di luar rentang waktu)')
            ->where('tables.0.kpi', [
                ['label' => 'Sales aktif', 'value' => 5],
                ['label' => 'Checkin 09:00–11:00', 'value' => 2],
                ['label' => 'Checkin di luar rentang', 'value' => 2],
                ['label' => 'Belum checkin', 'value' => 1],
            ])
            ->where('tables.0.keterangan', fn ($teks) => str_contains($teks, 'antara jam 09:00 dan 11:00'))
            ->where('tables.0.filter_pilihan.depo', ['22' => 'Balaraja', '46' => 'Sidoarjo'])
            ->where('tables.0.filter_pilihan.koordinator', ['137' => 'Koor Satu'])
        );
    }

    public function test_filter_depo_koordinator_dan_pencarian(): void
    {
        $this->get(self::URL.'&depo=46')->assertInertia(fn (Assert $page) => $page
            ->where('tables.0.rows.total', 2)->where('filter.depo', 46)
            ->where('tables.0.kpi.0.value', 3)); // Rudi, Tegar, Wati
        $this->get(self::URL.'&koordinator=137')->assertInertia(fn (Assert $page) => $page
            ->where('tables.0.rows.total', 2)->where('tables.0.rows.data.0.nama_sales', 'Tegar Mahendra'));
        $this->get(self::URL.'&q=wati')->assertInertia(fn (Assert $page) => $page
            ->where('tables.0.rows.total', 1)->where('tables.0.rows.data.0.nama_sales', 'Wati Lestari'));
    }

    public function test_rentang_jam_diatur_lewat_config(): void
    {
        config(['mobile.checkin_pagi_mulai' => '08:00', 'mobile.checkin_pagi_selesai' => '09:00']);

        // 08:00–<09:00: hanya Tegar (08:44) yang dalam rentang; Fajar (tepat 09:00) sekarang di luar.
        $this->get(self::URL)->assertInertia(fn (Assert $page) => $page
            ->where('tables.0.rows.total', 4)
            ->where('tables.0.kpi.1', ['label' => 'Checkin 08:00–09:00', 'value' => 1])
            ->where('tables.0.rows.data.0.nama_sales', 'Fajar Nugroho')
            ->where('tables.0.rows.data.0.checkin_pertama_hari_ini', '09:00 (di luar rentang waktu)'));
    }

    public function test_tanggal_bawaan_hari_ini_wib_dan_minggu_tetap_tampil(): void
    {
        // 25 Okt 2026 00:30 UTC = 07:30 WIB hari Minggu: tanggal bawaan ikut WIB, semua sales aktif belum checkin.
        $this->travelTo(now()->setTimezone('UTC')->setDate(2026, 10, 24)->setTime(23, 30));

        $this->get('/monitoring_harian_sales')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('tanggal', '2026-10-25')
            ->where('tables.0.rows.total', 5)
            ->where('tables.0.kpi.3.value', 5));
    }

    public function test_cakupan_role_koordinator(): void
    {
        $koor = $this->akun('koor.satu', 'koordinator', 137, [
            'role_id' => $this->role('Koor Monitoring', 'downline', ['monitoring_harian_sales' => ['lihat']]),
        ]);
        $this->actingAs($koor);

        $this->get(self::URL)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('tables.0.rows.total', 2)
            ->where('tables.0.kpi.0.value', 3)
            ->where('tables.0.filter_pilihan.depo', ['46' => 'Sidoarjo']));
    }

    public function test_tab_lain_tanpa_sumber_bertanggal_tidak_terpengaruh(): void
    {
        DB::table('provinsi')->insert(['kode' => '32', 'nama_provinsi' => 'Jawa Barat']);

        $this->get('/provinsi?tanggal=2026-10-22&depo=46')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('tables.0.rows.total', 1)
            ->where('tables.0.kpi', null)
            ->where('tables.0.filter_pilihan', null)
            ->where('tanggal', null)
            ->where('hasDateFilter', false));
    }
}
