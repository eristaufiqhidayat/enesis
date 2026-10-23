<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

// Isi tabel mk_* langsung dari aplikasi lama Marketama (baca saja), memakai akun di
// MARKETAMA_USERNAME / MARKETAMA_PASSWORD. Tidak ikut DatabaseSeeder karena butuh login
// dan bisa memakan waktu berjam-jam. Jalankan manual:
//   php artisan db:seed --class=MarketamaSumberSeeder
// atau per bagian (bisa dilanjutkan bila terputus):
//   php artisan marketama:tarik master karyawan user outlet
class MarketamaSumberSeeder extends Seeder
{
    public function run(): void
    {
        Artisan::call('marketama:tarik', [], $this->command?->getOutput());
    }
}
