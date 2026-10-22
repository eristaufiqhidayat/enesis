<?php

namespace Database\Seeders;

use App\Support\CsvTableLoader;
use Illuminate\Database\Seeder;

// Data referensi tanpa data pribadi (wilayah, produk, depo, kategori, rumus, dll).
// File CSV ada di database/seeders/data.
class ReferenceDataSeeder extends Seeder
{
    public const TABLES = [
        'provinsi',
        'city_group',
        'kota_kabupaten',
        'kecamatan',
        'kelurahan',
        'produk',
        'produk_kompetitor',
        'kategori_produk',
        'merk_produk',
        'satuan_produk',
        'master_depo',
        'wilayah_depo',
        'master_project',
        'holiday_dates',
        'tipe_outlet',
        'sales_point_conversion_per_warehouse',
        'parameter_pemotong_gaji',
        'kategori_surat_peringatan',
        'template_barang_canvassing',
        'waktu_aktif_app',
        'target_setting',
        'kategori_user',
        'kategori_karyawan',
        'kategori_outlet',
        'rumus_insentif_point_sales_0',
        'rumus_insentif_point_sales_1',
        'rumus_insentif_sales',
        'rumus_insentif_koordinator',
        'panduan',
    ];

    public function run(): void
    {
        foreach (self::TABLES as $table) {
            $rows = CsvTableLoader::load($table, database_path("seeders/data/{$table}.csv"));
            $this->command?->info("{$table}: {$rows} baris");
        }
    }
}
