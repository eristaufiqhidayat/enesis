<?php

namespace Tests\Feature\Mobile;

use Illuminate\Support\Facades\DB;

// Muatan canvassing yang dibatalkan dari web tidak terlihat dan tidak bisa diterima di aplikasi HP.
class MuatanDibatalkanTest extends MobileApiTestCase
{
    public function test_muatan_dibatalkan_bukan_muatan_hari_ini_dan_tidak_bisa_diterima(): void
    {
        $this->sales();
        $this->login()->assertOk();
        $this->artisan('enesis:siapkan-canvassing', ['karyawan' => 1, '--produk' => ['3:10']])->assertSuccessful();
        $nomor = $this->api('GET', 'canvassing/today')->assertOk()->json('data.number');
        $this->assertNotNull($nomor);

        DB::table('trx_canvassing')->where('nomor', $nomor)->update(['status' => 'dibatalkan', 'dibatalkan_at' => now(), 'dibatalkan_oleh' => 1]);

        $this->api('GET', 'canvassing/today')->assertOk()->assertJsonPath('data', null);
        $this->assertError($this->api('POST', "canvassing/$nomor/accept"), 404, 'not_found');
        $this->assertError($this->api('GET', "canvassing/$nomor/balance"), 404, 'not_found');
    }

    public function test_muatan_pengganti_menjadi_muatan_hari_ini(): void
    {
        $this->sales();
        $this->login()->assertOk();
        DB::table('trx_canvassing')->insert(['nomor' => 'CNV-BATAL', 'karyawan_id' => 1, 'depo_id' => 46, 'tanggal' => '2026-10-26',
            'status' => 'dibatalkan', 'dibatalkan_at' => now(), 'dibatalkan_oleh' => 1]);
        $this->artisan('enesis:siapkan-canvassing', ['karyawan' => 1, '--produk' => ['3:10']])->assertSuccessful();

        $nomor = $this->api('GET', 'canvassing/today')->assertOk()->json('data.number');

        $this->assertNotNull($nomor);
        $this->assertNotSame('CNV-BATAL', $nomor);
    }
}
