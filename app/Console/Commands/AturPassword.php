<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AturPassword extends Command
{
    protected $signature = 'enesis:atur-password
                            {username : Username akun Enesis}
                            {--wajib-ganti=1 : Paksa ganti password saat login pertama (1/0)}';

    protected $description = 'Buat password awal acak untuk satu akun Enesis dan tampilkan sekali';

    public function handle(): int
    {
        $user = DB::table('users')->whereRaw('lower(username) = ?', [mb_strtolower($this->argument('username'))])->first();

        if (! $user) {
            $this->error('Username tidak ditemukan.');

            return self::FAILURE;
        }

        if (! $user->aktif) {
            $this->warn('Akun ini nonaktif; password tetap diatur, tapi akun belum bisa dipakai login.');
        }

        // Tanpa huruf/angka yang mirip (0/O, 1/l/I) supaya mudah didiktekan.
        $huruf = 'abcdefghjkmnpqrstuvwxyz23456789';
        $password = '';

        for ($i = 0; $i < 10; $i++) {
            $password .= $huruf[random_int(0, strlen($huruf) - 1)];
        }

        DB::table('users')->where('id', $user->id)->update([
            'password' => Hash::make($password),
            'wajib_ganti_password' => (bool) (int) $this->option('wajib-ganti'),
            'remember_token' => Str::random(60),
            'updated_at' => now(),
        ]);

        $this->info("Password awal untuk {$user->username}: {$password}");
        $this->line('Sampaikan langsung ke pemilik akun; password ini tidak disimpan dalam bentuk yang bisa dibaca.');

        return self::SUCCESS;
    }
}
