<?php

namespace Tests\Feature\Admin;

use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

// Kolom Status/Lokasi dan filter status kebaruan di Tracking Last Seen Sales & Koordinator.
class TrackingLastSeenStatusTest extends AdminTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Sekarang: 7 Okt 2026 12:00 WIB. Awal hari ini = 7 Okt 00:00 WIB; 7 hari sebelumnya = 30 Sep 00:00 WIB.
        $this->travelTo(now()->setTimezone('Asia/Jakarta')->setDate(2026, 10, 7)->setTime(12, 0));

        DB::table('mk_karyawan')->insert([
            ['id' => 5, 'kategori' => 'sales', 'nama' => 'Lama Sekali', 'depo_id' => 46, 'koordinator_id' => 137, 'aktif' => true],
            ['id' => 6, 'kategori' => 'sales', 'nama' => 'Lama Ada Di Aplikasi Lama', 'depo_id' => 46, 'koordinator_id' => 137, 'aktif' => true],
            ['id' => 7, 'kategori' => 'sales', 'nama' => 'HP Baru Lama Lawas', 'depo_id' => 46, 'koordinator_id' => 137, 'aktif' => true],
        ]);
        // 1 Rudi: HP 11:00 WIB hari ini. 3 Tegar: hanya aplikasi lama 5 Okt (minggu). 2 Sinta: tanpa data (belum).
        DB::table('trx_tracking')->insert([
            ['karyawan_id' => 1, 'recorded_at' => '2026-10-07 04:00:00', 'lat' => -7.2, 'lng' => 112.2, 'baterai' => 75],
            // 7: HP hari ini, riwayat lama sudah usang -> waktu efektif dari HP.
            ['karyawan_id' => 7, 'recorded_at' => '2026-10-07 04:00:00', 'lat' => -7.5, 'lng' => 112.5, 'baterai' => 50],
            // 6: HP usang, riwayat lama hari ini -> waktu efektif dari aplikasi lama.
            ['karyawan_id' => 6, 'recorded_at' => '2026-09-01 04:00:00', 'lat' => -7.6, 'lng' => 112.6, 'baterai' => 10],
            // Koordinator 137: kemarin pagi.
            ['karyawan_id' => 137, 'recorded_at' => '2026-10-06 01:00:00', 'lat' => -7.3, 'lng' => 112.3, 'baterai' => null],
        ]);
        DB::table('tracking_sales_lokasi')->insert([
            ['sales_id' => 3, 'tanggal' => '2026-10-05', 'waktu' => '2026-10-05 15:00:00', 'lat' => -7.4, 'lng' => 112.4],
            ['sales_id' => 5, 'tanggal' => '2026-09-01', 'waktu' => '2026-09-01 15:00:00', 'lat' => -7.7, 'lng' => 112.7],
            ['sales_id' => 6, 'tanggal' => '2026-10-07', 'waktu' => '2026-10-07 09:00:00', 'lat' => -7.8, 'lng' => 112.8],
            ['sales_id' => 7, 'tanggal' => '2026-09-01', 'waktu' => '2026-09-01 15:00:00', 'lat' => -7.9, 'lng' => 112.9],
        ]);
    }

    /** Nama baris tabel pertama menurut ?status=, diurutkan supaya tidak bergantung urutan tabel. */
    private function namaDenganStatus(string $slug, ?string $status): array
    {
        $res = $this->get("/{$slug}".($status ? "?status={$status}" : ''))->assertOk();
        $nama = [];
        $res->assertInertia(function (Assert $p) use (&$nama) {
            $p->loadDeferredProps(function (Assert $p) use (&$nama) {
                $nama = collect($p->toArray()['props']['tables'][0]['rows']['data'])->pluck('nama')->sort()->values()->all();
            });
        });

        return $nama;
    }

    public function test_label_status_per_baris_dan_kolom_tabel(): void
    {
        $this->get('/tracking_sales')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->loadDeferredProps(fn (Assert $p) => $p
                ->where('tables.0.columns', [
                    'nama' => 'Nama', 'telepon' => 'Telepon', 'depo' => 'Depo', 'koordinator' => 'Koordinator',
                    'terakhir_terlihat' => 'Terakhir Terlihat', 'lokasi' => 'Lokasi', 'riwayat' => 'Riwayat', 'ditandai' => 'Ditandai', 'lokasi_palsu' => 'Lokasi Palsu',
                ])
                ->where('tables.0.rows.data.0.nama', 'HP Baru Lama Lawas')
                ->where('tables.0.rows.data.0.status', 'Hari ini')
                ->where('tables.0.rows.data.0.sejak', '1 jam yang lalu')
                ->where('tables.0.rows.data.0.latitude', -7.5)
                ->where('tables.0.pilihan_status', ['hari_ini' => 'Hari ini', 'minggu' => '1–7 hari lalu', 'lama' => 'Lebih dari 7 hari', 'belum' => 'Belum ada data'])
            ));
    }

    public function test_filter_status_menyaring_menurut_waktu_efektif(): void
    {
        $this->assertSame(
            ['HP Baru Lama Lawas', 'Lama Ada Di Aplikasi Lama', 'Rudi Hartono'],
            $this->namaDenganStatus('tracking_sales', 'hari_ini')
        );
        $this->assertSame(['Tegar Mahendra'], $this->namaDenganStatus('tracking_sales', 'minggu'));
        $this->assertSame(['Lama Sekali'], $this->namaDenganStatus('tracking_sales', 'lama'));
        $this->assertSame(['Sinta Wulandari'], $this->namaDenganStatus('tracking_sales', 'belum'));
    }

    public function test_status_tak_dikenal_atau_kosong_tidak_menyaring(): void
    {
        $this->assertCount(6, $this->namaDenganStatus('tracking_sales', null));
        $this->assertCount(6, $this->namaDenganStatus('tracking_sales', 'sembarang'));
    }

    public function test_filter_status_berlaku_untuk_koordinator(): void
    {
        $this->assertSame(['Koor Satu'], $this->namaDenganStatus('tracking_koordinator', 'minggu'));
        $this->assertSame([], $this->namaDenganStatus('tracking_koordinator', 'hari_ini'));
    }

    public function test_peta_sebaran_masih_menerima_baterai_dan_sumber(): void
    {
        $sales = collect($this->getJson(route('peta-sebaran-sales.data'))->assertOk()->json('sales'))->firstWhere('id', 1);

        $this->assertSame('75%', $sales['baterai']);
        $this->assertSame('Aplikasi HP', $sales['sumber']);
        $this->assertSame('hari_ini', $sales['status']);
    }
}
