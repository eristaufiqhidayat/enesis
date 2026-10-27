<?php

namespace Tests\Feature;

use App\Support\Wilayah\BatasBig;
use App\Support\Wilayah\BatasWilayah;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use RuntimeException;
use Tests\TestCase;

class BatasWilayahBigTest extends TestCase
{
    use RefreshDatabase;

    private const URL_BIG = 'https://geoservices.big.go.id/rbi/rest/services/BATASWILAYAH/Administrasi_AR_KelDesa_10K/MapServer/0/query';

    private const URL_DATASET = 'https://raw.githubusercontent.com/cahyadsn/wilayah_boundaries/main/db/kel/31/wilayah_boundaries_kel_31.71.sql';

    // Kebon Kelapa hanya ada di dataset (dianggap belum ada di peta BIG).
    private const SQL = <<<'SQL'
INSERT INTO wilayah_boundaries(kode,nama,lat,lng,path) VALUES
	 ('31.71.01.1001','Gambir',-6.175,106.825,'[[[-6.17,106.82],[-6.17,106.83],[-6.18,106.83],[-6.18,106.82],[-6.17,106.82]]]'),
	 ('31.71.01.1005','Kebon Kelapa',-6.164,106.824,'[[[-6.162,106.822],[-6.162,106.832],[-6.172,106.832],[-6.172,106.822],[-6.162,106.822]]]');
SQL;

    protected function setUp(): void
    {
        parent::setUp();
        config(['batas_wilayah.sumber' => 'big', 'batas_wilayah.cadangan' => BatasWilayah::SUMBER, 'batas_wilayah.big.halaman' => 2]);
        Http::preventStrayRequests();
        Sleep::fake();

        foreach ([['3171011001', 'Gambir'], ['3171011005', 'Kebon Kelapa'], [null, 'Tambahan']] as [$kode, $nama]) {
            DB::table('kelurahan')->insert(['provinsi' => 'DKI Jakarta', 'kota_kabupaten' => 'Kota Jakarta Pusat', 'kecamatan' => 'Gambir', 'kode' => $kode, 'nama_kelurahan' => $nama]);
        }
    }

    private static function fitur(string $kode, string $nama, array $koordinat, string $tipe = 'Polygon'): array
    {
        return ['type' => 'Feature', 'properties' => ['KDEPUM' => $kode, 'WADMKD' => $nama], 'geometry' => ['type' => $tipe, 'coordinates' => $koordinat]];
    }

    /** Dua halaman: Gambir terpecah jadi dua fitur (pulau), lalu satu fitur kota lain yang harus diabaikan. */
    private function palsukanBig(): void
    {
        Http::fake([self::URL_BIG => function (Request $req) {
            $hal = (int) ($req->data()['resultOffset'] ?? 0) / 2;

            return Http::response(['type' => 'FeatureCollection', 'features' => match ($hal) {
                0 => [
                    self::fitur('31.71.01.1001', 'GAMBIR', [[[106.82, -6.17], [106.83, -6.17], [106.83, -6.18], [106.82, -6.18]]]),
                    self::fitur('31.71.01.1001', 'GAMBIR', [[[[106.9, -6.1], [106.91, -6.1], [106.91, -6.11], [106.9, -6.1]]]], 'MultiPolygon'),
                ],
                1 => [self::fitur('31.72.01.1001', 'LAIN', [[[1, 1], [1, 2], [2, 2], [1, 1]]])],
                default => [],
            }]);
        }, self::URL_DATASET => Http::response(self::SQL)]);
    }

    public function test_big_features_are_merged_per_code_and_missing_codes_come_from_fallback(): void
    {
        $this->palsukanBig();

        $this->assertTrue(app(BatasWilayah::class)->pastikanKota('3171'));

        $gambir = DB::table('batas_wilayah')->where('kode', '3171011001')->first();
        $this->assertSame([BatasBig::SUMBER, 'Gambir'], [$gambir->sumber, $gambir->nama]);
        $multi = json_decode($gambir->geojson, true)['coordinates'];
        $this->assertCount(2, $multi);
        // Cincin ditutup.
        $this->assertSame($multi[0][0][0], end($multi[0][0]));

        $this->assertSame(BatasWilayah::SUMBER, DB::table('batas_wilayah')->where('kode', '3171011005')->value('sumber'));
        $this->assertFalse(DB::table('batas_wilayah')->where('kode', 'like', '3172%')->exists());

        Http::assertSent(fn (Request $r) => $r->url() === self::URL_BIG && str_contains($r->data()['where'], "KDPKAB='31.71'") && $r->data()['f'] === 'geojson');
        // Halaman kedua kurang dari 2 fitur → berhenti, tidak minta halaman ketiga.
        Http::assertSentCount(3);

        // Sudah tersimpan → tidak mengambil lagi.
        $this->assertFalse(app(BatasWilayah::class)->pastikanKota('3171'));
    }

    public function test_falls_back_to_dataset_when_big_reports_error(): void
    {
        Http::fake([
            self::URL_BIG => Http::response(['error' => ['code' => 500, 'message' => 'Unable to complete operation.']]),
            self::URL_DATASET => Http::response(self::SQL),
        ]);

        app(BatasWilayah::class)->pastikanKota('3171');

        $this->assertSame(2, DB::table('batas_wilayah')->where('sumber', BatasWilayah::SUMBER)->count());
    }

    public function test_without_fallback_big_error_is_reported(): void
    {
        config(['batas_wilayah.cadangan' => null]);
        Http::fake([self::URL_BIG => Http::response('', 503)]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('BIG');
        app(BatasWilayah::class)->pastikanKota('3171');
    }

    public function test_command_reports_counts_per_source(): void
    {
        $this->palsukanBig();

        $this->artisan('wilayah:impor-batas', ['--kota' => ['3171']])
            ->expectsTable(['Tingkat', 'Sumber', 'Jumlah batas'], [['kelurahan', BatasBig::SUMBER, '1'], ['kelurahan', BatasWilayah::SUMBER, '1']])
            ->assertSuccessful();
    }
}
