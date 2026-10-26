<?php

namespace App\Support\Backup;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

// Tabel database yang boleh dicadangkan, dikelompokkan seperti di halaman Backup Data, beserta perkiraan
// jumlah baris (MySQL: information_schema, PostgreSQL: pg_class; SQLite: tidak ada perkiraan).
class DaftarTabel
{
    /** Tabel sistem Laravel dan riwayat backup sendiri: tidak ikut. */
    public const SISTEM = ['cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'sessions', 'password_reset_tokens', 'migrations', 'backup_data'];

    /** Tabel yang berisi data pribadi (nama, telepon, lokasi GPS, gaji, akun). */
    public const PRIBADI = [
        'trx_absensi', 'trx_outlet_baru', 'trx_tracking', 'mk_karyawan', 'mk_user', 'mk_outlet', 'users', 'mobile_tokens',
        'tracking_sales', 'tracking_sales_lokasi', 'tracking_koordinator', 'tracking_koordinator_lokasi',
        'lap_karyawan', 'lap_user', 'lap_penggajian_sales', 'lap_penggajian_koordinator', 'lap_absensi_sales', 'lap_absensi_koordinator',
        'karyawan_sales', 'karyawan_koordinator', 'karyawan_supervisor', 'karyawan_lainnya', 'login_session',
        'super_admin', 'user_sales', 'user_koordinator', 'user_lainnya',
    ];

    private const ENESIS = ['master_kuota_motoris', 'pengaturan_admin', 'hierarki_sales', 'area_polygon', 'area_depo',
        'tracking_sales', 'tracking_sales_lokasi', 'tracking_koordinator', 'tracking_koordinator_lokasi'];

    private const AKUN = ['users', 'roles', 'role_hak', 'riwayat_admin', 'mobile_tokens', 'mobile_uploads', 'mobile_idempotency'];

    /** @return array<int, array{id: string, nama: string, ket: string}> urutan grup di halaman */
    public const GRUP = [
        ['id' => 'trx', 'nama' => 'Transaksi aplikasi motoris', 'ket' => 'Data HP sales & koordinator, termasuk riwayat aplikasi lama hasil migrasi'],
        ['id' => 'mk', 'nama' => 'Master hasil tarikan Marketama', 'ket' => 'Depo, area, karyawan, outlet, produk — dipakai aplikasi HP'],
        ['id' => 'enesis', 'nama' => 'Pengaturan Enesis', 'ket' => 'Isian admin: kuota motoris, target, hierarki sales, area polygon, tracking lama'],
        ['id' => 'akun', 'nama' => 'Akun, role & aplikasi HP', 'ket' => 'Login web & HP, hak akses, riwayat admin'],
        ['id' => 'ref', 'nama' => 'Data referensi', 'ket' => 'Master Data & Pengaturan bawaan (wilayah, kategori, rumus insentif, hari kerja)'],
        ['id' => 'lap', 'nama' => 'Laporan aplikasi lama (lap_*)', 'ket' => 'Salinan laporan Marketama'],
        ['id' => 'menu', 'nama' => 'Salinan menu lama lainnya', 'ket' => 'Tabel menu Marketama yang belum dialihkan ke data motoris'],
    ];

    /** @return list<string> semua tabel yang boleh dicadangkan, urut nama */
    public static function semua(): array
    {
        $nama = collect(Schema::getTableListing(schemaQualified: false))
            ->map(fn ($t) => (string) $t)
            ->reject(fn ($t) => in_array($t, self::SISTEM, true) || str_starts_with($t, 'sqlite_'))
            ->unique()->sort()->values();

        return $nama->all();
    }

    /** Hanya nama yang ada di daftar (mencegah nama tabel sembarang dari form). */
    public static function saring(array $tabel): array
    {
        return array_values(array_intersect(self::semua(), array_map('strval', $tabel)));
    }

    public static function grupDari(string $tabel): string
    {
        return match (true) {
            str_starts_with($tabel, 'trx_') => 'trx',
            str_starts_with($tabel, 'mk_') => 'mk',
            str_starts_with($tabel, 'lap_') => 'lap',
            in_array($tabel, self::AKUN, true) => 'akun',
            in_array($tabel, self::ENESIS, true) => 'enesis',
            in_array($tabel, self::referensi(), true) => 'ref',
            default => 'menu',
        };
    }

    /**
     * Grup untuk halaman: tiap grup berisi tabel (nama, perkiraan baris, data pribadi).
     *
     * @return list<array{id: string, nama: string, ket: string, tabel: list<array{nama: string, baris: ?int, pribadi: bool}>}>
     */
    public static function grup(): array
    {
        $baris = self::perkiraanBaris();
        $perGrup = collect(self::semua())->groupBy(fn ($t) => self::grupDari($t));

        return collect(self::GRUP)
            ->map(fn ($g) => $g + ['tabel' => ($perGrup[$g['id']] ?? collect())->map(fn ($t) => [
                'nama' => $t,
                'baris' => $baris[$t] ?? null,
                'pribadi' => in_array($t, self::PRIBADI, true),
            ])->values()->all()])
            ->filter(fn ($g) => $g['tabel'])
            ->values()->all();
    }

    /** @return array<string, int> perkiraan baris per tabel (tanpa COUNT(*) yang berat di tabel besar) */
    public static function perkiraanBaris(): array
    {
        try {
            return match (DB::getDriverName()) {
                'mysql', 'mariadb' => DB::table('information_schema.TABLES')->where('TABLE_SCHEMA', DB::getDatabaseName())
                    ->pluck('TABLE_ROWS', 'TABLE_NAME')->map(fn ($n) => (int) $n)->all(),
                'pgsql' => collect(DB::select("SELECT relname, reltuples FROM pg_class WHERE relkind = 'r' AND relnamespace = 'public'::regnamespace"))
                    ->mapWithKeys(fn ($r) => [$r->relname => max(0, (int) $r->reltuples)])->all(),
                default => [],
            };
        } catch (\Throwable) {
            return [];
        }
    }

    /** Tabel data referensi = tabel yang punya CSV bawaan di database/seeders/data. */
    private static function referensi(): array
    {
        static $daftar = null;

        return $daftar ??= collect(File::glob(database_path('seeders/data/*.csv')))
            ->map(fn ($f) => pathinfo($f, PATHINFO_FILENAME))->values()->all();
    }
}
