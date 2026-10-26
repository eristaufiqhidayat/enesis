<?php

namespace Tests\Feature\Admin;

use App\Jobs\BuatBackup;
use App\Support\Backup\DaftarTabel;
use App\Support\Backup\PembuatBackup;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use ZipArchive;

// Menu Backup Data: pilih tabel + format CSV/SQL, file dibuat di latar belakang, diunduh dari riwayat.
class BackupDataTest extends AdminTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->travelTo(now()->setTimezone('Asia/Jakarta')->setDate(2026, 10, 26)->setTime(17, 35));

        DB::table('trx_absensi')->insert([
            ['karyawan_id' => 1, 'depo_id' => 46, 'tanggal' => '2026-10-01'],
            ['karyawan_id' => 1, 'depo_id' => 46, 'tanggal' => '2026-10-20'],
        ]);
    }

    private function backup(string $format, array $tabel, array $opsi = []): object
    {
        $id = DB::table('backup_data')->insertGetId([
            'format' => $format, 'tabel' => json_encode($tabel), 'status' => 'menunggu',
            'opsi' => json_encode($opsi + ['header' => true, 'pemisah' => 'koma', 'struktur' => true, 'drop' => false, 'per_tabel' => false, 'rahasia' => true]),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        (new PembuatBackup($id))->jalankan();

        return DB::table('backup_data')->find($id);
    }

    public function test_daftar_tabel_berkelompok_tanpa_tabel_sistem(): void
    {
        $semua = DaftarTabel::semua();
        $this->assertContains('trx_penjualan', $semua);
        $this->assertNotContains('sessions', $semua);
        $this->assertNotContains('backup_data', $semua);
        $this->assertSame(['mk_depo'], DaftarTabel::saring(['mk_depo', 'tidak_ada', 'sessions']));
        $this->assertSame(['trx', 'mk', 'lap', 'akun', 'ref', 'menu'], array_map(DaftarTabel::grupDari(...), ['trx_kunjungan', 'mk_outlet', 'lap_join_visit', 'users', 'provinsi', 'karyawan_sales']));

        $this->get('/backup_data')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('Backup/Index')
            ->where('bolehBuat', true)
            ->loadDeferredProps(fn (Assert $p) => $p
                ->where('grup.0.id', 'trx')
                ->where('grup.1.id', 'mk')
                ->where('grup.1.tabel', fn ($t) => collect($t)->firstWhere('nama', 'mk_karyawan')['pribadi'] === true)));
    }

    public function test_simpan_membuat_antrean_dan_mencatat_riwayat_admin(): void
    {
        Bus::fake();

        $this->post('/backup_data', ['format' => 'csv', 'tabel' => ['mk_depo', 'tabel_palsu'], 'pemisah' => 'titikkoma'])
            ->assertRedirect(route('backup-data.index'))->assertSessionHas('sukses');

        $b = DB::table('backup_data')->first();
        $this->assertSame(['csv', 'menunggu', ['mk_depo']], [$b->format, $b->status, json_decode($b->tabel, true)]);
        $this->assertSame('titikkoma', json_decode($b->opsi, true)['pemisah']);
        Bus::assertDispatchedAfterResponse(BuatBackup::class, fn ($j) => $j->backupId === (int) $b->id);
        $this->assertDatabaseHas('riwayat_admin', ['objek' => 'backup', 'objek_id' => $b->id, 'aksi' => 'buat']);

        $this->post('/backup_data', ['format' => 'sql', 'tabel' => []])->assertSessionHasErrors('tabel');
    }

    public function test_sql_berisi_struktur_insert_dan_kolom_rahasia_dikosongkan(): void
    {
        $b = $this->backup('sql', ['mk_depo', 'users'], ['drop' => true]);

        $this->assertSame('selesai', $b->status);
        $this->assertStringEndsWith('.sql.gz', $b->nama_file);
        $sql = gzdecode(Storage::disk('local')->get('backup/'.$b->nama_file));
        $this->assertStringContainsString('DROP TABLE IF EXISTS "mk_depo";', $sql);
        $this->assertStringContainsString('CREATE TABLE "mk_depo"', $sql);
        $this->assertMatchesRegularExpression('/INSERT INTO "mk_depo" \("id", .*\) VALUES\n\(22, NULL, \'Balaraja\'/', $sql);
        $this->assertStringNotContainsString($this->admin->password, $sql);
        $this->assertSame(2 + DB::table('users')->count(), (int) $b->baris);
        $this->assertSame('2026-11-02', substr($b->kedaluwarsa_at, 0, 10));

        // File SQL bisa dijalankan ulang ke database kosong.
        DB::statement('DROP TABLE mk_depo');
        DB::unprepared(explode('-- Tabel users', $sql)[0]);
        $this->assertSame(['Balaraja', 'Sidoarjo'], DB::table('mk_depo')->orderBy('nama')->pluck('nama')->all());
    }

    public function test_csv_per_tabel_dalam_zip_dengan_rentang_tanggal(): void
    {
        $b = $this->backup('csv', ['trx_absensi', 'mk_depo'], ['pemisah' => 'titikkoma', 'dari' => '2026-10-15', 'sampai' => '2026-10-26']);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open(Storage::disk('local')->path('backup/'.$b->nama_file)));
        $this->assertSame(['mk_depo.csv', 'trx_absensi.csv'], [$zip->getNameIndex(0), $zip->getNameIndex(1)]); // urut nama tabel
        $absen = $zip->getFromName('trx_absensi.csv');
        $this->assertStringStartsWith("\xEF\xBB\xBFid;karyawan_id;depo_id;tanggal", $absen);
        $this->assertSame(2, substr_count(trim($absen), "\n") + 1); // judul + 1 baris (tanggal 1 Okt di luar rentang)
        $this->assertStringContainsString('2026-10-20', $absen);
        $this->assertSame(3, (int) $b->baris);
    }

    public function test_unduh_hapus_dan_bersihkan_kedaluwarsa(): void
    {
        $b = $this->backup('sql', ['mk_depo'], ['per_tabel' => true]);
        $this->assertStringEndsWith('_sql.zip', $b->nama_file);

        $this->get("/backup_data/{$b->id}/unduh")->assertOk()->assertDownload($b->nama_file);
        $this->assertDatabaseHas('riwayat_admin', ['objek' => 'backup', 'objek_id' => $b->id, 'aksi' => 'unduh']);

        $this->travel(8)->days();
        $this->assertSame(1, PembuatBackup::bersihkan());
        Storage::disk('local')->assertMissing('backup/'.$b->nama_file);
        $this->get("/backup_data/{$b->id}/unduh")->assertNotFound();
        $this->get('/backup_data')->assertInertia(fn (Assert $p) => $p->where('riwayat.0.status', 'kedaluwarsa'));

        $this->delete("/backup_data/{$b->id}")->assertRedirect(route('backup-data.index'));
        $this->assertDatabaseMissing('backup_data', ['id' => $b->id]);
    }

    public function test_hak_ekspor_dibutuhkan_untuk_buat_dan_unduh(): void
    {
        $b = $this->backup('csv', ['mk_depo']);
        $staf = $this->akun('staf', 'lainnya', 500, ['role_id' => $this->role('Lihat Backup', 'semua', ['backup_data' => ['lihat']])]);
        $this->actingAs($staf);

        $this->get('/backup_data')->assertOk()->assertInertia(fn (Assert $p) => $p->where('bolehBuat', false));
        $this->post('/backup_data', ['format' => 'csv', 'tabel' => ['mk_depo']])->assertForbidden();
        $this->get("/backup_data/{$b->id}/unduh")->assertForbidden();

        // Role bawaan "lainnya" tidak otomatis mendapat menu admin ini.
        $this->actingAs($this->akun('biasa', 'lainnya', 500));
        $this->get('/backup_data')->assertForbidden();
    }

    public function test_perintah_terminal_dan_backup_macet(): void
    {
        DB::table('backup_data')->insert(['format' => 'sql', 'tabel' => '["mk_depo"]', 'opsi' => '{}', 'status' => 'proses',
            'created_at' => now()->subHour(), 'updated_at' => now()->subHour()]);

        $this->artisan('enesis:backup mk_depo --format=csv')->assertSuccessful();

        $this->assertSame('gagal', DB::table('backup_data')->where('id', 1)->value('status'));
        $baru = DB::table('backup_data')->find(2);
        $this->assertSame(['selesai', 'csv'], [$baru->status, $baru->format]);
        $this->artisan('enesis:backup tidak_ada')->assertFailed();
    }
}
