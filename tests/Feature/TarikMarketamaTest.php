<?php

namespace Tests\Feature;

use App\Support\Marketama\HalamanParser;
use App\Support\Marketama\MarketamaClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

// Uji penarik data Marketama dengan server tiruan (data rekaan, bukan data asli).
class TarikMarketamaTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = 'https://marketama.test';

    private bool $sudahLogin = false;

    /** @var list<string> */
    private array $postKe = [];

    /** Simulasi server lambat: halaman master_area > N baris timeout. */
    private ?int $areaTimeoutDiAtas = null;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'marketama_sumber.url' => self::BASE,
            'marketama_sumber.username' => 'admin',
            'marketama_sumber.password' => 'rahasia',
            'marketama_sumber.jeda_ms' => 0,
            'marketama_sumber.per_halaman' => 2,
            'marketama_sumber.jeda_gagal_detik' => 0,
        ]);
    }

    // ------------------------------------------------------------ server tiruan

    private function halaman(string $tableId, array $judul, string $url): string
    {
        $th = implode('', array_map(fn ($j) => "<th>{$j}</th>", $judul));

        return <<<HTML
            <html><body>
            <table id="{$tableId}"><thead><tr>{$th}</tr></thead><tbody></tbody></table>
            <script>
            $('#{$tableId}').DataTable({ serverSide: true, ajax: { url: uri + '{$url}', type: 'POST',
              headers: { 'X-CSRF-TOKEN': 'tok123' } } });
            </script></body></html>
            HTML;
    }

    private function aksi(int $id): string
    {
        return '<div class="btn-group"><a class="dropdown-item view-detail" data-id="'.$id.'" href="#">View Detail</a></div>';
    }

    /** @return array<string, array{judul:list<string>, url:string, baris:list<array>}> */
    private function menu(): array
    {
        return [
            'master_depo' => ['judul' => ['', '', 'Kode', 'Nama', 'Status'], 'url' => '/master_depo/datatable_master_depo', 'baris' => [
                [22, $this->aksi(22), 'D01', 'Balaraja', 'Aktif'],
                [46, $this->aksi(46), 'D02', 'Sidoarjo', 'Aktif'],
            ]],
            'wilayah_depo' => ['judul' => ['', '', 'Nama', 'Depo / Warehouse'], 'url' => '/wilayah_depo/datatable', 'baris' => [
                [5, $this->aksi(5), 'Wilayah Timur', 'Sidoarjo'],
            ]],
            'master_area' => ['judul' => ['', '', 'Kode', 'Nama', 'Depo'], 'url' => '/master_area/datatable_master_area', 'baris' => [
                [18685, $this->aksi(18685), 'A-200', 'Area Gresik-200', 'Sidoarjo'],
                [18702, $this->aksi(18702), 'A-217', 'Area Gresik-217', 'Sidoarjo'],
                [30001, $this->aksi(30001), 'B-1', 'Area Balaraja-1', 'Balaraja'],
            ]],
            'distributor' => ['judul' => ['', '', 'Kode', 'Nama Distributor', 'Depo'], 'url' => '/distributor/datatable_distributor', 'baris' => [
                [7, $this->aksi(7), 'DS1', 'PT Distributor Contoh', 'Sidoarjo'],
            ]],
            'produk' => ['judul' => ['', '', 'Kode', 'Nama Produk'], 'url' => '/produk/datatable_produk', 'baris' => [
                [3, $this->aksi(3), 'SKU1', 'Minuman Contoh 320 ml'],
            ]],
            'karyawan_koordinator' => ['judul' => ['', '', 'Nama Lengkap', 'Depo', 'No HP', 'Status'], 'url' => '/karyawan/datatable_karyawan', 'baris' => [
                [137, $this->aksi(137), 'Koor Satu', 'Sidoarjo', '0800001', 'Aktif'],
            ]],
            'karyawan_sales' => ['judul' => ['', '', 'ID', 'Nama Lengkap', 'Koordinator', 'Depo', 'No HP', 'Status'], 'url' => '/karyawan_sales/datatable_karyawan_sales', 'baris' => [
                'aktif' => [
                    [1616, $this->aksi(1616), '1616', 'Sales Satu', 'Koor Satu', 'Sidoarjo', '0811', '<span class="badge">Aktif</span>'],
                    [1615, $this->aksi(1615), '1615', 'Sales Dua', 'Koor Satu', 'Sidoarjo', '0812', '<span class="badge">Aktif</span>'],
                    [1614, $this->aksi(1614), '1614', 'Sales Tiga', 'Koor Satu', 'Balaraja', '0813', '<span class="badge">Aktif</span>'],
                ],
                'tidak_aktif' => [
                    [900, $this->aksi(900), '900', 'Sales Lama', 'Koor Satu', 'Sidoarjo', '0819', 'Tidak Aktif'],
                ],
            ]],
            'karyawan_supervisor' => ['judul' => ['', '', 'Nama Lengkap', 'No HP', 'Email', 'Status'], 'url' => '/karyawan_supervisor/datatable_karyawan_supervisor', 'baris' => [
                [50, $this->aksi(50), 'Supervisor Satu', '0899', 'spv@contoh.test', 'Aktif'],
            ]],
            'karyawan_lainnya' => ['judul' => ['', '', 'Nama Lengkap', 'Status'], 'url' => '/karyawan_lainnya/datatable_karyawan_lainnya', 'baris' => []],
            'user_sales' => ['judul' => ['', '', 'Username', 'Nickname', 'Karyawan', 'Depo', 'Koordinator', 'Status'], 'url' => '/user_sales/datatable_user_sales', 'baris' => [
                [2001, $this->aksi(2001), 'sales1', 'S1', 'Sales Satu', 'Sidoarjo', 'Koor Satu', 'Aktif'],
                [2002, $this->aksi(2002), 'sales_lama', 'SL', 'Sales Lama', 'Sidoarjo', 'Koor Satu', 'Nonaktif'],
            ]],
            'user_koordinator' => ['judul' => ['', '', 'Username', 'Nickname', 'Karyawan', 'Depo', 'Status'], 'url' => '/user_koordinator/datatable_user_koordinator', 'baris' => [
                [301, $this->aksi(301), 'koor1', 'K1', 'Koor Satu', 'Sidoarjo', 'Aktif'],
            ]],
            'user_lainnya' => ['judul' => ['', '', 'Username', 'Karyawan', 'Status'], 'url' => '/user_lainnya/datatable_user_lainnya', 'baris' => []],
            'super_admin' => ['judul' => ['', '', 'Username', 'Karyawan', 'Status'], 'url' => '/super_admin/datatable_super_admin', 'baris' => []],
            'outlet_all' => ['judul' => ['', '', 'Kode Outlet', 'Nama Outlet', 'Area', 'Longitude', 'Latitude'], 'url' => '/outlet_all/datatable_outlet_all', 'baris' => [
                [11, $this->aksi(11), 'OUT-1', 'Toko Satu', 'Area Gresik-200', '112.6', '-7.4'],
                [12, $this->aksi(12), 'OUT-2', 'Toko Dua', 'Area Tidak Ada', '-', '-'],
            ]],
            'penjualan_barang' => ['judul' => ['', '', 'Kode Transaksi', 'Grand Total'], 'url' => '/penjualan_barang/datatable_penjualan_barang', 'baris' => [
                [77, $this->aksi(77), 'SLS-1', 'Rp 16.656.000'],
            ]],
        ];
    }

    private function editKaryawanSales(int $id): string
    {
        $areas = htmlspecialchars(json_encode([
            ['area_id' => 18685, 'active' => 1, 'name' => 'Area Gresik-200'],
            ['area_id' => 18702, 'active' => 0, 'name' => 'Area Gresik-217'],
        ]), ENT_QUOTES);
        $depo = $id === 1614 ? 22 : 46;
        [$aktif, $nonaktif] = $id === 900 ? ['', ' selected'] : [' selected', ''];

        return <<<HTML
            <form><input type="hidden" name="id" value="{$id}"><input name="fullname" value="Nama">
            <select name="warehouse_id"><option value="{$depo}">Depo</option></select>
            <select name="koordinator_id"><option value="137">Koor Satu</option></select>
            <select name="status"><option value="1"{$aktif}>Aktif</option><option value="0"{$nonaktif}>Tidak Aktif</option></select>
            <input type="hidden" name="area_id" id="area_id" value="18685">
            <input type="email" name="email" value="sales{$id}@contoh.test">
            <input name="basic_salary" value="Rp 5.000.000">
            <div id="existing_employee_areas" data-areas="{$areas}"></div></form>
            HTML;
    }

    private function pasangServer(bool $loginBerhasil = true): void
    {
        $menu = $this->menu();

        Http::fake(function (Request $request) use ($menu, $loginBerhasil) {
            $path = parse_url($request->url(), PHP_URL_PATH);

            if ($request->method() === 'POST') {
                $this->postKe[] = $path;
            }

            if ($path === '/login' && $request->method() === 'POST') {
                $this->sudahLogin = $loginBerhasil && $request['username'] === 'admin' && $request['password'] === 'rahasia' && $request['_token'] === 'csrf-login';

                return Http::response('', 302, ['Location' => self::BASE.'/home']);
            }

            if (in_array($path, ['/login', '/home'], true)) {
                return Http::response($this->sudahLogin
                    ? '<html>Beranda</html>'
                    : '<form action="'.self::BASE.'/login" method="post"><input type="hidden" name="_token" value="csrf-login"><input type="text" name="username"><input type="password" name="password"></form>');
            }

            if (! $this->sudahLogin) {
                return Http::response('', 401);
            }

            return match ($path) {
                '/karyawan_sales/get_depo' => Http::response(['results' => [['id' => 22, 'text' => 'Balaraja'], ['id' => 46, 'text' => 'Sidoarjo']]]),
                '/karyawan_sales/get_area_list' => Http::response(['results' => [['id' => 18685, 'name' => 'Area Gresik-200'], ['id' => 40000, 'name' => 'Area Tanpa Menu']]]),
                '/api/global/get_koordinators' => Http::response(['results' => [['id' => 137, 'text' => 'Koor Satu', 'fullname' => 'Koor Satu']]]),
                '/distributor/edit/7' => Http::response('<form><select name="warehouse_id"><option value="46">Sidoarjo</option></select><textarea name="address">Jl. Contoh</textarea></form>'),
                '/produk/edit/3' => Http::response('<form><input name="code" value="SKU1"><select name="brand_id"><option value="2" selected>Merk</option></select><input name="cost" value="9000"><input name="price" value="12500"></form>'),
                '/user_sales/edit/2001' => Http::response('<form><select name="id_employee"><option value="">-</option><option value="1616" selected>Sales Satu</option></select></form>'),
                '/user_sales/edit/2002' => Http::response('<form><select name="id_employee"><option value="900" selected>Sales Lama</option></select></form>'),
                '/user_koordinator/edit/301' => Http::response('<form><select name="id_employee"><option value="137" selected>Koor Satu</option></select></form>'),
                default => $this->rutePola($path, $request, $menu),
            };
        });
    }

    private function rutePola(string $path, Request $request, array $menu)
    {
        if (preg_match('#^/karyawan_sales/edit/(\d+)$#', $path, $m)) {
            return Http::response($this->editKaryawanSales((int) $m[1]));
        }

        foreach ($menu as $nama => $def) {
            $tableId = $nama === 'karyawan_koordinator' ? 'tabel_karyawan' : "tabel_{$nama}";

            if ($path === "/{$nama}") {
                return Http::response($this->halaman($tableId, $def['judul'], $def['url']));
            }

            // master_depo di aplikasi asli hanya menerima GET untuk DataTables.
            if ($path === $def['url'] && $nama === 'master_depo' && $request->method() === 'POST') {
                return Http::response(['message' => 'The POST method is not supported.'], 405);
            }

            if ($path === $def['url']) {
                $this->assertSame('tok123', $request->header('X-CSRF-TOKEN')[0] ?? null);
                parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $param);

                if ($request->method() === 'POST') {
                    $param = $request->data();
                }

                if ($nama === 'master_area' && $this->areaTimeoutDiAtas !== null && (int) $param['length'] > $this->areaTimeoutDiAtas) {
                    return Http::failedConnection('cURL error 28: Operation timed out after 60001 milliseconds with 0 bytes received');
                }

                $baris = $def['baris'];

                if (isset($baris['aktif'])) {
                    $baris = $baris[$param['mode'] ?? 'aktif'] ?? $baris['aktif'];
                }

                return Http::response([
                    'draw' => 1,
                    'recordsTotal' => count($baris),
                    'recordsFiltered' => count($baris),
                    'data' => array_slice($baris, (int) $param['start'], (int) $param['length']),
                ]);
            }
        }

        return Http::response('', 404);
    }

    // ------------------------------------------------------------------ tes

    public function test_tarik_semua_bagian_mengisi_tabel_dengan_id_asli(): void
    {
        $this->pasangServer();

        $this->artisan('marketama:tarik')->assertSuccessful();

        $this->assertSame(['Balaraja', 'Sidoarjo'], DB::table('mk_depo')->orderBy('id')->pluck('nama')->all());
        $this->assertSame('D02', DB::table('mk_depo')->where('id', 46)->value('kode'), 'master_depo lewat GET (405 untuk POST)');
        $this->assertSame(46, (int) DB::table('mk_wilayah_depo')->where('id', 5)->value('depo_id'));

        // Area dari menu + area tambahan dari daftar area sales.
        $this->assertSame(4, DB::table('mk_area')->count());
        $this->assertSame(22, (int) DB::table('mk_area')->where('id', 30001)->value('depo_id'));

        $this->assertSame('Jl. Contoh', DB::table('mk_distributor')->where('id', 7)->value('alamat'));
        $produk = DB::table('mk_produk')->where('id', 3)->first();
        $this->assertSame(2, (int) $produk->merk_id);
        $this->assertEquals(12500, $produk->harga_jual);

        // Semua sales, aktif + tidak aktif, dengan ID depo/koordinator dari form edit.
        $this->assertSame(4, DB::table('mk_karyawan')->where('kategori', 'sales')->count());
        $sales = DB::table('mk_karyawan')->where('id', 1614)->first();
        $this->assertSame(22, (int) $sales->depo_id);
        $this->assertSame(137, (int) $sales->koordinator_id);
        $this->assertSame(18685, (int) $sales->area_aktif_id);
        $this->assertSame('sales1614@contoh.test', $sales->email);
        $this->assertTrue((bool) $sales->detail_lengkap);
        $this->assertStringNotContainsString('5.000.000', (string) $sales->data, 'gaji tidak boleh ikut tersimpan');
        $this->assertFalse((bool) DB::table('mk_karyawan')->where('id', 900)->value('aktif'));
        $this->assertSame(8, DB::table('mk_karyawan_area')->count());
        $this->assertTrue((bool) DB::table('mk_karyawan_area')->where(['karyawan_id' => 1616, 'area_id' => 18685])->value('aktif'));

        $this->assertSame('koordinator', DB::table('mk_karyawan')->where('id', 137)->value('kategori'));
        $this->assertSame(46, (int) DB::table('mk_karyawan')->where('id', 137)->value('depo_id'));
        $this->assertSame('supervisor', DB::table('mk_karyawan')->where('id', 50)->value('kategori'));

        // User tersambung ke karyawan lewat id_employee.
        $this->assertSame(1616, (int) DB::table('mk_user')->where(['jenis' => 'sales', 'id' => 2001])->value('karyawan_id'));
        $this->assertSame(900, (int) DB::table('mk_user')->where(['jenis' => 'sales', 'id' => 2002])->value('karyawan_id'));
        $this->assertSame(137, (int) DB::table('mk_user')->where(['jenis' => 'koordinator', 'id' => 301])->value('karyawan_id'));

        $outlet = DB::table('mk_outlet')->where('id', 11)->first();
        $this->assertSame(18685, (int) $outlet->area_id);
        $this->assertEquals(-7.4, $outlet->latitude);
        $this->assertNull(DB::table('mk_outlet')->where('id', 12)->value('area_id'));

        $this->assertTrue((bool) DB::table('mk_tarikan')->where('kunci', 'list:outlet_all')->value('selesai'));
    }

    public function test_hanya_post_ke_login_dan_datatable(): void
    {
        $this->pasangServer();

        $this->artisan('marketama:tarik')->assertSuccessful();

        foreach (array_unique($this->postKe) as $path) {
            $this->assertTrue($path === '/login' || str_contains($path, '/datatable'), "POST tak terduga ke {$path}");
        }

        $this->assertFalse(MarketamaClient::postDiizinkan('/karyawan_sales/edit_action'));
        $this->assertFalse(MarketamaClient::postDiizinkan('/outlet_all/delete/5'));
        $this->assertTrue(MarketamaClient::postDiizinkan('/wilayah_depo/datatable'));
    }

    public function test_login_gagal_menghentikan_perintah(): void
    {
        $this->pasangServer(loginBerhasil: false);

        $this->artisan('marketama:tarik', ['bagian' => ['master']])
            ->expectsOutputToContain('Login ke Marketama gagal')
            ->assertFailed();

        $this->assertSame(0, DB::table('mk_depo')->count());
    }

    public function test_tanpa_kredensial_tidak_berjalan(): void
    {
        config(['marketama_sumber.password' => null]);

        $this->artisan('marketama:tarik')->assertFailed();
    }

    public function test_bagian_mentah_dan_lanjut_dari_posisi_tersimpan(): void
    {
        $this->pasangServer();

        $this->artisan('marketama:tarik', ['bagian' => ['mentah:penjualan_barang']])->assertSuccessful();

        $data = json_decode(DB::table('mk_baris_mentah')->where(['menu' => 'penjualan_barang', 'id' => 77])->value('data'), true);
        $this->assertSame(['kode_transaksi' => 'SLS-1', 'grand_total' => 'Rp 16.656.000'], $data);

        // Menu yang sudah selesai dilewati pada pemanggilan berikutnya.
        $this->postKe = [];
        $this->artisan('marketama:tarik', ['bagian' => ['mentah:penjualan_barang']])
            ->expectsOutputToContain('sudah selesai sebelumnya')
            ->assertSuccessful();
        $this->assertSame([], array_filter($this->postKe, fn ($p) => str_contains($p, '/datatable')));
    }

    public function test_halaman_timeout_diulang_dengan_ukuran_lebih_kecil(): void
    {
        config(['marketama_sumber.per_halaman' => 100]);
        $this->areaTimeoutDiAtas = 25;
        $this->pasangServer();

        $this->artisan('marketama:tarik', ['bagian' => ['master']])
            ->expectsOutputToContain('timeout di baris 0, coba lagi per 50 baris')
            ->expectsOutputToContain('coba lagi per 25 baris')
            ->assertSuccessful();

        $this->assertSame(4, DB::table('mk_area')->count());
        $this->assertTrue((bool) DB::table('mk_tarikan')->where('kunci', 'list:master_area')->value('selesai'));
    }

    public function test_timeout_terus_menerus_menghentikan_dengan_progres_tersimpan(): void
    {
        $this->areaTimeoutDiAtas = 0;
        $this->pasangServer();

        $this->artisan('marketama:tarik', ['bagian' => ['master']])
            ->expectsOutputToContain('Progres tersimpan')
            ->assertFailed();

        $this->assertTrue((bool) DB::table('mk_tarikan')->where('kunci', 'list:master_depo')->value('selesai'));
        $this->assertFalse((bool) DB::table('mk_tarikan')->where('kunci', 'list:master_area')->value('selesai'));
    }

    public function test_outlet_sebelum_area_disambung_belakangan(): void
    {
        $this->pasangServer();

        // Outlet ditarik lebih dulu: area belum ada, area_id kosong.
        $this->artisan('marketama:tarik', ['bagian' => ['outlet']])->assertSuccessful();
        $this->assertNull(DB::table('mk_outlet')->where('id', 11)->value('area_id'));

        // Setelah master (area) ditarik, sambung otomatis mengisi area_id.
        $this->artisan('marketama:tarik', ['bagian' => ['master']])
            ->expectsOutputToContain('sambung: 1 outlet dapat area_id')
            ->assertSuccessful();
        $this->assertSame(18685, (int) DB::table('mk_outlet')->where('id', 11)->value('area_id'));
    }

    public function test_area_disalin_dari_area_polygon_tanpa_menu_master_area(): void
    {
        DB::table('area_polygon')->insert([
            ['source_id' => 18685, 'code' => 'A-200', 'name' => 'Area Gresik-200', 'polygon' => '[]', 'warehouse' => 'Sidoarjo', 'warehouse_id' => 46, 'branch_area_id' => 5],
            ['source_id' => 50001, 'code' => 'P-1', 'name' => 'Area Dari Polygon', 'polygon' => '[]', 'warehouse' => 'Balaraja', 'warehouse_id' => null, 'branch_area_id' => null],
        ]);
        $this->areaTimeoutDiAtas = 0; // menu Master Area tidak boleh dipakai
        $this->pasangServer();

        $this->artisan('marketama:tarik', ['bagian' => ['master']])
            ->expectsOutputToContain('disalin dari area_polygon')
            ->assertSuccessful();

        $this->assertSame(46, (int) DB::table('mk_area')->where('id', 18685)->value('depo_id'));
        $this->assertSame(22, (int) DB::table('mk_area')->where('id', 50001)->value('depo_id'), 'depo dari nama bila warehouse_id kosong');
        $this->assertSame('P-1', DB::table('mk_area')->where('id', 50001)->value('kode'));
        $this->assertSame(3, DB::table('mk_area')->count(), '2 dari polygon + 1 dari daftar area sales');
        $this->assertNull(DB::table('mk_tarikan')->where('kunci', 'list:master_area')->first());
    }

    public function test_parser_halaman(): void
    {
        $html = $this->halaman('tabel_x', ['', '', 'Kode Outlet', 'Kota/Kabupaten'], '/x/datatable_x');

        $this->assertSame('tok123', HalamanParser::csrfToken($html));
        $this->assertSame('/x/datatable_x', HalamanParser::datatableUrl($html));
        $this->assertSame(['_0', '_1', 'kode_outlet', 'kota_kabupaten'], HalamanParser::kolom($html, 'tabel_x'));
        $this->assertSame(1616, HalamanParser::idBaris(['', $this->aksi(1616)]));
        $this->assertSame(55, HalamanParser::idBaris(['55', 'teks']));
        $this->assertSame('Aktif', HalamanParser::teks('<span class="badge">Aktif</span>'));
        $this->assertSame(16656000.0, HalamanParser::angka('Rp 16.656.000'));
        $this->assertSame(1152.0, HalamanParser::angka('1.152'));
        $this->assertSame(12500.0, HalamanParser::angka('12500'));
        $this->assertFalse(HalamanParser::aktif('Tidak Aktif'));
        $this->assertSame('muhammad tamrin daulay', HalamanParser::kunciNama(' Muhammad  Tamrin Daulay '));
        $this->assertNull(HalamanParser::aktif('-'));
    }
}
