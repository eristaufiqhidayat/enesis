<?php

namespace Tests\Feature\Mobile;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

// Dasar uji API mobile: master rekaan (depo, area, outlet, produk), akun sales, dan pemanggil
// API dengan token + Idempotency-Key otomatis. Senin 26 Okt 2026 pukul 09:00 WIB.
abstract class MobileApiTestCase extends TestCase
{
    use RefreshDatabase;

    protected ?string $token = null;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->travelTo(CarbonImmutable::parse('2026-10-26 09:00', 'Asia/Jakarta'));
        $this->isiMaster();
    }

    protected function isiMaster(): void
    {
        DB::table('mk_depo')->insert(['id' => 46, 'nama' => 'Sidoarjo', 'data' => json_encode([
            'latitude' => '-6.2', 'longitude' => '106.8', 'minimal_value_transaksi' => '35.000', 'target_call' => '40', 'target_ec' => '25',
        ])]);
        DB::table('mk_area')->insert([['id' => 10, 'nama' => 'Area Rute'], ['id' => 99, 'nama' => 'Area Lain']]);
        DB::table('mk_karyawan')->insert([
            ['id' => 1, 'kategori' => 'sales', 'nama' => 'Andi Motoris', 'depo_id' => 46, 'koordinator_id' => 137, 'aktif' => true],
            ['id' => 2, 'kategori' => 'sales', 'nama' => 'Budi Motoris', 'depo_id' => 46, 'koordinator_id' => 137, 'aktif' => true],
            ['id' => 137, 'kategori' => 'koordinator', 'nama' => 'Koor Satu', 'depo_id' => 46, 'koordinator_id' => null, 'aktif' => true],
        ]);
        DB::table('mk_karyawan_area')->insert([['karyawan_id' => 1, 'area_id' => 10, 'aktif' => true], ['karyawan_id' => 2, 'area_id' => 10, 'aktif' => true]]);
        DB::table('mk_outlet')->insert([
            ['id' => 11, 'kode' => 'OUT-11', 'nama' => 'Toko Sebelas', 'area_id' => 10, 'latitude' => -6.201, 'longitude' => 106.801],
            ['id' => 12, 'kode' => 'OUT-12', 'nama' => 'Toko Dua Belas', 'area_id' => 10, 'latitude' => -6.205, 'longitude' => 106.805],
            ['id' => 13, 'kode' => 'OUT-13', 'nama' => 'Toko Luar Rute', 'area_id' => 99, 'latitude' => -6.21, 'longitude' => 106.81],
        ]);
        DB::table('mk_produk')->insert([
            ['id' => 3, 'kode' => '8991001', 'nama' => 'Minuman 320 ml', 'harga_jual' => 10000, 'data' => json_encode(['produk_fokus' => 'Ya', 'merk' => 'Segar'])],
            ['id' => 4, 'kode' => '8991002', 'nama' => 'Biskuit 100 g', 'harga_jual' => 5000, 'data' => json_encode(['produk_fokus' => 'Tidak'])],
        ]);
    }

    protected function sales(array $atribut = []): User
    {
        return User::factory()->create($atribut + [
            'username' => 'andi', 'password' => 'rahasia123', 'kategori' => 'sales', 'karyawan_id' => 1,
        ]);
    }

    protected function login(string $username = 'andi', string $device = 'HP-A'): TestResponse
    {
        $r = $this->postJson('/api/mobile/v1/auth/login', [
            'username' => $username, 'password' => 'rahasia123', 'device_id' => $device, 'device_model' => 'Redmi 10', 'app_version' => '1.0.0',
        ], ['X-App-Version' => '1.0.0']);
        $this->token = $r->json('data.token');

        return $r;
    }

    /** Panggil API; POST otomatis membawa Idempotency-Key baru kecuali $kunci = false. */
    protected function api(string $method, string $uri, array $data = [], string|false|null $kunci = null): TestResponse
    {
        $headers = ['X-App-Version' => '1.0.0', 'Accept' => 'application/json'];

        if ($this->token) {
            $headers['Authorization'] = 'Bearer '.$this->token;
        }

        if ($method === 'POST' && $kunci !== false) {
            $headers['Idempotency-Key'] = $kunci ?? (string) Str::uuid();
        }

        $url = '/api/mobile/v1/'.ltrim($uri, '/');

        // Tiap permintaan HTTP sungguhan memulai guard baru; di uji aplikasi dipakai ulang.
        $this->app['auth']->forgetGuards();

        // Unggahan memakai multipart, selain itu JSON.
        return collect($data)->contains(fn ($v) => $v instanceof UploadedFile)
            ? $this->withHeaders($headers)->post($url, $data)
            : $this->json($method, $url, $data, $headers);
    }

    protected function foto(string $jenis = 'selfie'): string
    {
        return $this->api('POST', 'uploads', ['type' => $jenis, 'file' => UploadedFile::fake()->image('foto.jpg', 640, 480)], false)
            ->assertCreated()->json('data.upload_id');
    }

    /** Lokasi di depo / outlet (selisih beberapa meter). */
    protected function diDepo(): array
    {
        return ['lat' => -6.2001, 'lng' => 106.8001, 'accuracy_m' => 8];
    }

    protected function diOutlet(int $id): array
    {
        $o = DB::table('mk_outlet')->find($id);

        return ['lat' => (float) $o->latitude + 0.00005, 'lng' => (float) $o->longitude, 'accuracy_m' => 10];
    }

    protected function assertError(TestResponse $r, int $status, string $kode): TestResponse
    {
        return $r->assertStatus($status)->assertJsonPath('error.code', $kode)->assertJsonStructure(['error' => ['code', 'message']]);
    }
}
