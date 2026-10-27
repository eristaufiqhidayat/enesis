<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class SinkronWilayahResmiTest extends TestCase
{
    use RefreshDatabase;

    private string $cache;

    // Jawaban API relasi BPS–Kemendagri per level + parent (kode BPS).
    private const API = [
        'provinsi|' => [
            ['kode_bps' => '32', 'nama_bps' => 'JAWA BARAT', 'kode_dagri' => '32', 'nama_dagri' => 'JAWA BARAT'],
            ['kode_bps' => '64', 'nama_bps' => 'KALIMANTAN TIMUR', 'kode_dagri' => '64', 'nama_dagri' => 'KALIMANTAN TIMUR'],
        ],
        'kabupaten|32' => [
            ['kode_bps' => '3201', 'nama_bps' => 'BOGOR', 'kode_dagri' => '32.01', 'nama_dagri' => 'KAB. BOGOR'],
            ['kode_bps' => '3273', 'nama_bps' => 'KOTA BANDUNG', 'kode_dagri' => '32.73', 'nama_dagri' => 'KOTA BANDUNG'],
        ],
        'kabupaten|64' => [
            ['kode_bps' => '6407', 'nama_bps' => 'KUTAI BARAT', 'kode_dagri' => '64.07', 'nama_dagri' => 'KAB. KUTAI BARAT'],
            ['kode_bps' => '6411', 'nama_bps' => 'MAHAKAM ULU', 'kode_dagri' => '64.11', 'nama_dagri' => 'KAB. MAHAKAM ULU'],
        ],
        'kecamatan|3201' => [
            ['kode_bps' => '3201010', 'nama_bps' => 'NANGGUNG', 'kode_dagri' => '32.01.01', 'nama_dagri' => 'CIBINONG'],
            ['kode_bps' => '3201020', 'nama_bps' => 'CIAWI', 'kode_dagri' => '32.01.02', 'nama_dagri' => 'CIAWI'],
        ],
        'kecamatan|3273' => [
            ['kode_bps' => '3273010', 'nama_bps' => 'SUKASARI', 'kode_dagri' => '32.73.01', 'nama_dagri' => 'SUKASARI'],
        ],
        'kecamatan|6407' => [
            ['kode_bps' => '6407010', 'nama_bps' => 'MELAK', 'kode_dagri' => '64.07.01', 'nama_dagri' => 'MELAK'],
        ],
        'kecamatan|6411' => [],
        'desa|3201010' => [
            ['kode_bps' => '3201010001', 'nama_bps' => 'PAKANSARI', 'kode_dagri' => '32.01.01.1001', 'nama_dagri' => 'PAKANSARI'],
            ['kode_bps' => '3201010002', 'nama_bps' => 'X', 'kode_dagri' => '', 'nama_dagri' => ''],
        ],
        'desa|3201020' => [
            ['kode_bps' => '3201020001', 'nama_bps' => 'BANJARSARI', 'kode_dagri' => '32.01.02.2001', 'nama_dagri' => 'BANJARSARI'],
        ],
        'desa|6407010' => [
            ['kode_bps' => '6407010001', 'nama_bps' => 'MELAK ULU', 'kode_dagri' => '64.07.01.1001', 'nama_dagri' => 'MELAK ULU'],
        ],
        // desa|3273010 sengaja gagal (HTTP 500).
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->cache = sys_get_temp_dir().'/enesis-bps-'.uniqid();
        config(['wilayah_resmi.bps.cache_dir' => $this->cache, 'wilayah_resmi.laporan_dir' => $this->cache.'/laporan']);

        Http::preventStrayRequests();
        Sleep::fake();
        Http::fake(function (Request $req) {
            parse_str((string) parse_url($req->url(), PHP_URL_QUERY), $q);
            $kunci = ($q['level'] ?? '').'|'.($q['parent'] ?? '');

            return array_key_exists($kunci, self::API) ? Http::response(self::API[$kunci]) : Http::response('galat', 500);
        });

        DB::table('provinsi')->insert([
            ['source_id' => 12, 'kode' => 32, 'nama_provinsi' => 'Jawa Barat'],
            ['source_id' => 23, 'kode' => 64, 'nama_provinsi' => 'Kalimantan Timur'],
        ]);
        DB::table('kota_kabupaten')->insert([
            ['source_id' => 161, 'provinsi' => 'Jawa Barat', 'kode' => 3201, 'nama_kota_kabupaten' => 'Kabupaten Bogor'],
            ['source_id' => 300, 'provinsi' => 'Jawa Barat', 'kode' => 3273, 'nama_kota_kabupaten' => 'Kota Bandung'],
            ['source_id' => 301, 'provinsi' => 'Jawa Barat', 'kode' => 3299, 'nama_kota_kabupaten' => 'Kabupaten Contoh'],
            ['source_id' => 400, 'provinsi' => 'Kalimantan Timur', 'kode' => 6407, 'nama_kota_kabupaten' => 'Kabupaten Kutai Barat'],
            ['source_id' => 401, 'provinsi' => 'Kalimantan Timur', 'kode' => 6411, 'nama_kota_kabupaten' => 'Kabupaten Mahakam Ulu'],
        ]);
        DB::table('kecamatan')->insert([
            // Nama lama berbeda dengan data resmi.
            ['source_id' => 1, 'provinsi' => 'Jawa Barat', 'kota_kabupaten' => 'Kabupaten Bogor', 'kode' => '320101', 'nama_kecamatan' => 'Nanggung'],
            ['source_id' => 2, 'provinsi' => 'Jawa Barat', 'kota_kabupaten' => 'Kota Bandung', 'kode' => '327301', 'nama_kecamatan' => 'SUKASARI'],
            // Salah induk: kecamatan Kutai Barat tercatat di Mahakam Ulu.
            ['source_id' => 3, 'provinsi' => 'Kalimantan Timur', 'kota_kabupaten' => 'Kabupaten Mahakam Ulu', 'kode' => '640701', 'nama_kecamatan' => 'Melak'],
            // Tambahan Marketama tanpa kode.
            ['source_id' => 4, 'provinsi' => 'Jawa Barat', 'kota_kabupaten' => 'Kabupaten Bogor', 'kode' => null, 'nama_kecamatan' => 'Perairan'],
        ]);
        DB::table('kelurahan')->insert([
            ['provinsi' => 'Jawa Barat', 'kota_kabupaten' => 'Kabupaten Bogor', 'kecamatan' => 'Nanggung', 'kode' => '3201011001', 'nama_kelurahan' => 'Pakansari'],
            ['provinsi' => 'Jawa Barat', 'kota_kabupaten' => 'Kabupaten Bogor', 'kecamatan' => 'Nanggung', 'kode' => null, 'nama_kelurahan' => 'Rt.24'],
            ['provinsi' => 'Jawa Barat', 'kota_kabupaten' => 'Kota Bandung', 'kecamatan' => 'SUKASARI', 'kode' => '3273011001', 'nama_kelurahan' => 'Isola'],
            ['provinsi' => 'Kalimantan Timur', 'kota_kabupaten' => 'Kabupaten Mahakam Ulu', 'kecamatan' => 'Melak', 'kode' => '6407011001', 'nama_kelurahan' => 'Melak Ulu'],
        ]);
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg($this->cache));
        parent::tearDown();
    }

    private function laporan(): array
    {
        $berkas = glob($this->cache.'/laporan/*.csv')[0];
        $baris = array_map(fn ($l) => str_getcsv($l, escape: ''), file($berkas, FILE_IGNORE_NEW_LINES));
        $kepala = array_map(fn ($k) => ltrim($k, "\xEF\xBB\xBF"), array_shift($baris));

        return array_map(fn ($b) => array_combine($kepala, $b), $baris);
    }

    public function test_dry_run_reports_differences_without_writing(): void
    {
        $this->artisan('wilayah:sinkron-resmi')->assertSuccessful();

        $this->assertSame('Nanggung', DB::table('kecamatan')->where('kode', '320101')->value('nama_kecamatan'));
        $this->assertSame(4, DB::table('kecamatan')->count());
        $this->assertNull(DB::table('kecamatan')->where('kode', '320101')->value('sumber'));

        $status = collect($this->laporan())->mapWithKeys(fn ($r) => [$r['tingkat'].':'.$r['kode'].':'.$r['nama_enesis'] => $r['status']]);
        $this->assertSame('beda_nama', $status['kecamatan:320101:Nanggung']);
        $this->assertSame('beda_induk', $status['kecamatan:640701:Melak']);
        $this->assertSame('baru', $status['kecamatan:320102:']);
        $this->assertSame('tambahan', $status['kecamatan::Perairan']);
        $this->assertSame('tidak_resmi', $status['kota:3299:Kabupaten Contoh']);
        $this->assertSame('baru', $status['kelurahan:3201022001:']);
        // "SUKASARI" vs "SUKASARI": beda gaya tulis saja → cocok, tidak masuk laporan.
        $this->assertArrayNotHasKey('kecamatan:327301:SUKASARI', $status->all());
        // Desa di Sukasari gagal diambil → kelurahannya tidak dinilai.
        $this->assertArrayNotHasKey('kelurahan:3273011001:Isola', $status->all());
        // Kelurahan Melak Ulu induknya benar menurut kode (kecamatan 640701), salahnya di tingkat kecamatan.
        $this->assertArrayNotHasKey('kelurahan:6407011001:Melak Ulu', $status->all());
    }

    public function test_save_marks_source_and_adds_missing_official_rows_only(): void
    {
        $this->artisan('wilayah:sinkron-resmi', ['--simpan' => true])->assertSuccessful();

        $this->assertSame('Nanggung', DB::table('kecamatan')->where('kode', '320101')->value('nama_kecamatan'));
        $this->assertSame('resmi', DB::table('kecamatan')->where('kode', '320101')->value('sumber'));
        $this->assertSame('3201010', DB::table('kecamatan')->where('kode', '320101')->value('kode_bps'));
        $this->assertSame('tambahan', DB::table('kecamatan')->where('nama_kecamatan', 'Perairan')->value('sumber'));
        $this->assertSame('tidak_resmi', DB::table('kota_kabupaten')->where('kode', 3299)->value('sumber'));
        $this->assertNull(DB::table('kelurahan')->where('kode', '3273011001')->value('sumber'));

        $ciawi = DB::table('kecamatan')->where('kode', '320102')->first();
        $this->assertSame(['Jawa Barat', 'Kabupaten Bogor', 'Ciawi', null], [$ciawi->provinsi, $ciawi->kota_kabupaten, $ciawi->nama_kecamatan, $ciawi->source_id]);
        // Desa baru ikut ke kecamatan baru, nama dirapikan.
        $desa = DB::table('kelurahan')->where('kode', '3201022001')->first();
        $this->assertSame(['Kabupaten Bogor', 'Ciawi', 'Banjarsari'], [$desa->kota_kabupaten, $desa->kecamatan, $desa->nama_kelurahan]);
    }

    public function test_fix_renames_and_moves_parent_carrying_children(): void
    {
        $this->artisan('wilayah:sinkron-resmi', ['--simpan' => true, '--perbaiki' => true])->assertSuccessful();

        $this->assertSame('Cibinong', DB::table('kecamatan')->where('kode', '320101')->value('nama_kecamatan'));
        // Kelurahan berkode & tambahan di kecamatan itu ikut nama baru.
        $this->assertSame(2, DB::table('kelurahan')->where('kecamatan', 'Cibinong')->count());
        $this->assertSame('SUKASARI', DB::table('kecamatan')->where('kode', '327301')->value('nama_kecamatan'));

        $melak = DB::table('kecamatan')->where('kode', '640701')->first();
        $this->assertSame('Kabupaten Kutai Barat', $melak->kota_kabupaten);
        $this->assertSame('Kabupaten Kutai Barat', DB::table('kelurahan')->where('kode', '6407011001')->value('kota_kabupaten'));
        // ID tidak berubah, tidak ada yang dihapus.
        $this->assertSame(3, DB::table('kecamatan')->where('kode', '640701')->value('source_id'));
        $this->assertSame(1, DB::table('kota_kabupaten')->where('kode', 3299)->count());
    }

    public function test_province_filter_limits_requests_and_scope(): void
    {
        $this->artisan('wilayah:sinkron-resmi', ['--provinsi' => ['64'], '--sampai' => 'kecamatan', '--simpan' => true])->assertSuccessful();

        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'parent=32'));
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'level=desa'));
        $this->assertNull(DB::table('kecamatan')->where('kode', '320101')->value('sumber'));
        $this->assertSame('resmi', DB::table('kecamatan')->where('kode', '640701')->value('sumber'));
    }

    public function test_cached_answers_are_reused(): void
    {
        $this->artisan('wilayah:sinkron-resmi', ['--sampai' => 'kota'])->assertSuccessful();
        $jumlah = count(Http::recorded());
        $this->artisan('wilayah:sinkron-resmi', ['--sampai' => 'kota'])->assertSuccessful();

        $this->assertSame($jumlah, count(Http::recorded()));
    }
}
