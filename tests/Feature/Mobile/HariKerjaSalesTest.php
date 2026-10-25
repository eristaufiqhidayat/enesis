<?php

namespace Tests\Feature\Mobile;

use App\Models\User;
use App\Support\Outlet\VerifikasiOutletBaru;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// Satu hari kerja sales motoris lewat API, urut seperti mockup: absen → checkin depo →
// terima barang → kunjungan & order → tutup hari.
class HariKerjaSalesTest extends MobileApiTestCase
{
    public function test_satu_hari_kerja_sales_motoris(): void
    {
        $this->sales();
        $this->login()->assertOk()->assertJsonPath('data.user.depot.name', 'Sidoarjo')->assertJsonPath('data.user.employee_id', 1);

        // Konfigurasi hari ini dari data depo.
        $this->api('GET', 'config')->assertOk()
            ->assertJsonPath('data.today.date', '2026-10-26')
            ->assertJsonPath('data.today.is_working_day', true)
            ->assertJsonPath('data.depot.lat', -6.2)
            ->assertJsonPath('data.min_transaction_value', 35000)
            ->assertJsonPath('data.daily_targets.call', 40);
        $this->api('GET', 'master')->assertOk()->assertJsonCount(2, 'data.products')
            ->assertJsonPath('data.products.0.price', 10000)->assertJsonPath('data.products.0.is_focus', true);

        // Belum absen: tidak bisa checkin depo.
        $this->assertError($this->api('POST', 'depot-visits/check-in', $this->diDepo()), 409, 'not_checked_in');

        // 1. Absen masuk: selfie wajib, harus di radius depo; lewat 08:00 ditandai terlambat.
        $this->assertError($this->api('POST', 'attendances/check-in', $this->diDepo()), 422, 'validation_failed')
            ->assertJsonStructure(['error' => ['fields' => ['photo_id']]]);
        $selfie = $this->foto();
        $this->assertError($this->api('POST', 'attendances/check-in', ['lat' => -6.21, 'lng' => 106.8, 'photo_id' => $selfie]), 422, 'outside_depot_radius')
            ->assertJsonPath('error.fields.radius_m', 100);

        $kunci = (string) Str::uuid();
        $absen = $this->api('POST', 'attendances/check-in', $this->diDepo() + ['photo_id' => $selfie, 'recorded_at' => '2026-10-26T08:59:00+07:00'], $kunci)
            ->assertCreated()->assertJsonPath('data.is_late', true);

        // Kirim ulang dari antrian: hasil sama, ditandai duplikat.
        $this->api('POST', 'attendances/check-in', $this->diDepo() + ['photo_id' => $selfie], $kunci)
            ->assertOk()->assertJsonPath('meta.duplicate', true)->assertJsonPath('data.attendance_id', $absen->json('data.attendance_id'));
        $this->assertSame(1, DB::table('trx_absensi')->count());
        $this->assertSame($kunci, DB::table('trx_absensi')->value('client_ref'));

        // 2. Checkin depo.
        $this->api('POST', 'depot-visits/check-in', $this->diDepo())->assertCreated();

        // Belum terima barang: belum boleh kunjungan.
        $this->assertError($this->api('POST', 'visits', ['outlet_id' => 11] + $this->diOutlet(11)), 409, 'no_canvassing');

        // 3. Terima barang canvassing yang disiapkan staf depo.
        $this->api('GET', 'canvassing/today')->assertOk()->assertJsonPath('data', null);
        $this->artisan('enesis:siapkan-canvassing', ['karyawan' => 1, '--produk' => ['3:10', '4:5']])->assertSuccessful();
        $nomor = $this->api('GET', 'canvassing/today')->assertOk()->assertJsonCount(2, 'data.items')->json('data.number');

        $this->assertError($this->api('POST', "canvassing/$nomor/accept", ['items' => [['product_id' => 4, 'qty_received' => 4]]]), 422, 'validation_failed')
            ->assertJsonStructure(['error' => ['fields' => ['items.0.reason']]]);
        $this->api('POST', "canvassing/$nomor/accept", ['items' => [['product_id' => 4, 'qty_received' => 4, 'reason' => 'Kurang 1 karton']]])
            ->assertOk()->assertJsonPath('data.status', 'diterima')
            ->assertJsonPath('data.items.1.qty_carried', 4)->assertJsonPath('data.items.0.qty_carried', 10);
        $this->assertError($this->api('POST', "canvassing/$nomor/accept"), 409, 'already_accepted');

        // 4. Rute hari ini: outlet di area sales.
        $this->api('GET', 'routes/today')->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.visit_status', 'belum')->assertJsonPath('meta.total', 2);

        // Kunjungan: harus dekat titik outlet; tidak boleh dua kunjungan terbuka.
        $this->assertError($this->api('POST', 'visits', ['outlet_id' => 11] + $this->diDepo()), 422, 'too_far_from_outlet');
        $visit = $this->api('POST', 'visits', ['outlet_id' => 11, 'photo_id' => $this->foto('toko')] + $this->diOutlet(11))
            ->assertCreated()->assertJsonPath('data.is_off_route', false)->json('data.visit_id');
        $this->assertError($this->api('POST', 'visits', ['outlet_id' => 12] + $this->diOutlet(12)), 409, 'visit_open');

        $this->api('POST', "visits/$visit/stocks", [
            'items' => [['product_id' => 3, 'qty' => 2]], 'competitors' => [['name' => 'Minuman Merek Lain', 'qty' => 6]],
        ])->assertOk()->assertJsonPath('data.items', 2);

        // Order: tidak boleh melebihi sisa bawaan; total dihitung server.
        $this->assertError($this->api('POST', 'sales', ['visit_id' => $visit, 'items' => [['product_id' => 3, 'qty' => 11]]]), 422, 'exceeds_stock')
            ->assertJsonStructure(['error' => ['fields' => ['items.0.qty']]]);

        $kecil = $this->api('POST', 'sales', ['visit_id' => $visit, 'client_total' => 20000, 'items' => [
            ['product_id' => 3, 'qty' => 2], ['product_id' => 4, 'qty' => 1],
        ]])->assertCreated()
            ->assertJsonPath('data.total', 25000)
            ->assertJsonPath('data.is_effective_call', false) // di bawah minimal Rp35.000
            ->assertJsonPath('data.price_changed', true)
            ->assertJsonPath('data.points', 0);
        $this->assertStringStartsWith('MB20261026-', $kecil->json('data.code'));
        $this->assertError($this->api('POST', 'sales', ['visit_id' => $visit, 'items' => [['product_id' => 4, 'qty' => 1]]]), 409, 'visit_has_sale');

        // Batalkan lalu buat ulang: stok kembali, transaksi baru jadi EC.
        $this->assertError($this->api('POST', 'sales/'.$kecil->json('data.sale_id').'/void', []), 422, 'validation_failed');
        $this->api('POST', 'sales/'.$kecil->json('data.sale_id').'/void', ['reason' => 'Salah input'])->assertOk()->assertJsonPath('data.is_void', true);
        $this->api('POST', 'sales', ['visit_id' => $visit, 'items' => [['product_id' => 3, 'qty' => 3], ['product_id' => 4, 'qty' => 1]]])
            ->assertCreated()->assertJsonPath('data.total', 35000)->assertJsonPath('data.is_effective_call', true);
        $this->api('GET', "canvassing/$nomor/balance")->assertOk()
            ->assertJsonPath('data.items.0.qty_sold', 3)->assertJsonPath('data.items.0.qty_remaining', 7)
            ->assertJsonPath('data.items.1.qty_remaining', 3);

        $this->api('POST', "visits/$visit/check-out", [])->assertOk();
        $this->assertError($this->api('POST', "visits/$visit/stocks", ['items' => []]), 409, 'visit_closed');

        // Outlet di luar rute, tidak beli.
        $luar = $this->api('POST', 'visits', ['outlet_id' => 13] + $this->diOutlet(13))->assertCreated()
            ->assertJsonPath('data.is_off_route', true)->json('data.visit_id');
        $this->assertError($this->api('POST', "visits/$luar/no-sale", ['reason_code' => 'lainnya']), 422, 'validation_failed');
        $this->api('POST', "visits/$luar/no-sale", ['reason_code' => 'toko_tutup'])->assertOk();

        // Tutup hari tidak boleh selagi kunjungan terbuka.
        $this->assertError($this->api('POST', "canvassing/$nomor/return", []), 409, 'visit_open');
        $this->api('POST', "visits/$luar/check-out", [])->assertOk();

        $rute = $this->api('GET', 'routes/today')->assertOk()->assertJsonPath('meta.bought', 1)->assertJsonPath('meta.visited', 1);
        $this->assertSame(['beli', 'belum'], array_column($rute->json('data'), 'visit_status'));

        // Beranda: KPI dan langkah.
        $this->api('GET', 'me/summary')->assertOk()
            ->assertJsonPath('data.kpi.call.value', 2)
            ->assertJsonPath('data.kpi.ec.value', 1)
            ->assertJsonPath('data.kpi.sales_amount.value', 35000)
            ->assertJsonPath('data.kpi.ipt.value', 2)
            ->assertJsonPath('data.steps.2.status', 'done')
            ->assertJsonPath('data.steps.3.status', 'current')
            ->assertJsonPath('data.next_outlet.id', 12);

        // 5. Tutup hari: sisa barang harus cocok, selisih wajib beralasan.
        $this->assertError($this->api('POST', 'depot-visits/check-out', []), 409, 'return_pending');
        $this->assertError($this->api('POST', "canvassing/$nomor/return", ['items' => [['product_id' => 3, 'qty_returned' => 6]]]), 422, 'return_mismatch')
            ->assertJsonStructure(['error' => ['fields' => ['items.0.reason']]]);
        $this->api('POST', "canvassing/$nomor/return", ['items' => [['product_id' => 3, 'qty_returned' => 7]]])
            ->assertOk()->assertJsonPath('data.status', 'dikembalikan')->assertJsonPath('data.cash_to_deposit', 35000);
        $this->assertEquals(3, DB::table('trx_canvassing_item')->where('produk_id', 4)->value('qty_kembali'));

        $this->assertError($this->api('POST', 'visits', ['outlet_id' => 12] + $this->diOutlet(12)), 409, 'day_closed');
        $this->api('POST', 'depot-visits/check-out', [])->assertOk();
        $this->api('POST', 'attendances/check-out', $this->diDepo())->assertOk();
        $this->assertError($this->api('POST', 'attendances/check-out', []), 409, 'already_checked_out');

        $this->api('GET', 'me/summary')->assertOk()->assertJsonPath('data.steps.4.status', 'done');
        $this->api('GET', 'sync/status')->assertOk()
            ->assertJsonPath('data.attendance.check_in', $kunci)
            ->assertJsonCount(2, 'data.visits')->assertJsonCount(2, 'data.sales');
        $this->api('GET', 'sales')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 35000);
        $this->api('GET', 'me/performance')->assertOk()
            ->assertJsonPath('data.totals.call', 2)->assertJsonPath('data.totals.ec', 1)->assertJsonPath('data.totals.sales_amount', 35000);
        $this->api('GET', 'attendances')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.is_late', true);
    }

    public function test_data_sales_lain_tidak_bisa_diakses(): void
    {
        $this->sales();
        DB::table('trx_kunjungan')->insert(['id' => 900, 'karyawan_id' => 2, 'tanggal' => '2026-10-26', 'checkin_at' => now(), 'outlet_id' => 11]);
        DB::table('trx_canvassing')->insert(['nomor' => 'CNV-ORANG-LAIN', 'karyawan_id' => 2, 'depo_id' => 46, 'tanggal' => '2026-10-26']);
        $this->login();

        $this->assertError($this->api('POST', 'visits/900/stocks', ['items' => []]), 404, 'not_found');
        $this->assertError($this->api('POST', 'sales', ['visit_id' => 900, 'items' => [['product_id' => 3, 'qty' => 1]]]), 404, 'not_found');
        $this->assertError($this->api('GET', 'canvassing/CNV-ORANG-LAIN/balance'), 404, 'not_found');
    }

    public function test_absen_ditolak_di_hari_libur_dan_di_luar_jam_aktif(): void
    {
        $this->sales();
        $this->login();
        $selfie = $this->foto();

        DB::table('holiday_dates')->insert(['nama_libur' => 'Cuti Bersama', 'tanggal' => '26-10-2026']);
        $this->assertError($this->api('POST', 'attendances/check-in', $this->diDepo() + ['photo_id' => $selfie]), 409, 'holiday');
        $this->api('GET', 'config')->assertJsonPath('data.today.is_working_day', false)->assertJsonPath('data.today.holiday_name', 'Cuti Bersama');

        DB::table('holiday_dates')->delete();
        $this->travelTo(now()->setTimezone('Asia/Jakarta')->setTime(22, 0));
        $this->assertError($this->api('POST', 'attendances/check-in', $this->diDepo() + ['photo_id' => $selfie]), 409, 'outside_active_hours');
    }

    public function test_outlet_baru_cek_duplikat_dan_bisa_dikunjungi(): void
    {
        $this->sales();
        $this->login();
        $foto = $this->foto('toko');
        $data = ['name' => 'Warung Baru', 'owner_name' => 'Bu Sri', 'phone' => '0812 3456 7890', 'type' => 'Warung', 'photo_store_id' => $foto];

        // Titik 5 m dari Toko Sebelas => kemungkinan duplikat.
        $this->assertError($this->api('POST', 'outlets', $data + $this->diOutlet(11)), 422, 'possible_duplicate')
            ->assertJsonPath('error.fields.candidates.0.id', 11);
        $baru = $this->api('POST', 'outlets', $data + $this->diOutlet(11) + ['confirm_not_duplicate' => true])
            ->assertCreated()->assertJsonPath('data.status', 'menunggu')->json('data.new_outlet_id');

        $this->assertError($this->api('POST', 'outlets', ['photo_store_id' => null] + $data + ['lat' => -6.3, 'lng' => 106.9]), 422, 'validation_failed');

        $dekat = $this->api('GET', 'outlets/nearby?lat=-6.201&lng=106.801&radius=50')->assertOk()->json('data');
        $this->assertSame([11, $baru], array_column($dekat, 'id'));
        $this->assertSame([false, true], array_column($dekat, 'is_new'));

        // Setelah disetujui admin, NOO sudah ada di master: tidak muncul dobel sebagai outlet baru.
        $outletId = app(VerifikasiOutletBaru::class)->setujui($baru, User::factory()->create());
        $dekat = $this->api('GET', 'outlets/nearby?lat=-6.201&lng=106.801&radius=50')->assertOk()->json('data');
        $this->assertEqualsCanonicalizing([11, $outletId], array_column($dekat, 'id'));
        $this->assertSame([false, false], array_column($dekat, 'is_new'));

        $this->api('GET', 'outlets/11')->assertOk()->assertJsonPath('data.name', 'Toko Sebelas')->assertJsonPath('data.last_sale', null);
        $this->assertError($this->api('GET', 'outlets/987654'), 404, 'not_found');
    }

    public function test_tracking_batch_boleh_dikirim_ulang(): void
    {
        $this->sales();
        $this->login();
        $titik = [
            ['recorded_at' => '2026-10-26T08:50:00+07:00', 'lat' => -6.2, 'lng' => 106.8, 'accuracy_m' => 12, 'battery' => 80],
            ['recorded_at' => '2026-10-26T08:53:00+07:00', 'lat' => -6.201, 'lng' => 106.801, 'accuracy_m' => 250, 'is_mock_location' => true],
            ['recorded_at' => '2026-10-20T08:00:00+07:00', 'lat' => -6.2, 'lng' => 106.8],
        ];

        $this->api('POST', 'tracking/batch', ['points' => $titik])->assertOk()
            ->assertJsonPath('data.accepted', 2)->assertJsonPath('data.duplicates', 0)
            ->assertJsonPath('data.rejected.0', ['index' => 2, 'code' => 'too_old']);
        $this->api('POST', 'tracking/batch', ['points' => $titik])->assertOk()
            ->assertJsonPath('data.accepted', 0)->assertJsonPath('data.duplicates', 2);

        $this->assertSame(2, DB::table('trx_tracking')->count());
        $this->assertTrue((bool) DB::table('trx_tracking')->where('akurasi_m', 250)->value('akurasi_rendah'));
        $this->assertTrue((bool) DB::table('trx_tracking')->where('akurasi_m', 250)->value('mock_location'));

        $banyak = array_fill(0, 501, $titik[0]);
        $this->assertError($this->api('POST', 'tracking/batch', ['points' => $banyak]), 422, 'too_many_points');
        $this->api('GET', 'sync/status')->assertJsonPath('data.tracking.points', 2);
    }
}
