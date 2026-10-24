<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

// Akun admin untuk mencoba aplikasi di lokal/dev: username "admin", password "password".
// Di production akun ini hanya dibuat bila SEED_ADMIN_PASSWORD diisi (dan tidak memakai
// password bawaan). Akun user sebenarnya berasal dari: php artisan enesis:sinkron-user
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $password = config('auth.seed_admin_password');

        if (! $password && app()->isProduction()) {
            $this->command?->warn('UserSeeder dilewati di production (isi SEED_ADMIN_PASSWORD bila perlu akun admin awal).');

            return;
        }

        User::updateOrCreate(['username' => 'admin'], [
            'name' => 'Administrator',
            'email' => null,
            'password' => $password ?: 'password',
            'kategori' => 'super_admin',
            'aktif' => true,
            // Password bawaan lokal tidak perlu diganti; password dari env wajib diganti saat login pertama.
            'wajib_ganti_password' => (bool) $password,
        ]);
    }
}
