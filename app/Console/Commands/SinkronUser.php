<?php

namespace App\Console\Commands;

use App\Support\Marketama\SinkronUser as Sinkron;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SinkronUser extends Command
{
    protected $signature = 'enesis:sinkron-user
                            {--daftar : Hanya tampilkan akun yang perlu keputusan admin}';

    protected $description = 'Buat/perbarui akun login Enesis (users) dari mk_user + mk_karyawan hasil marketama:tarik';

    public function handle(): int
    {
        if (! $this->option('daftar')) {
            if (! Schema::hasTable('mk_user') || DB::table('mk_user')->doesntExist()) {
                $this->error('mk_user kosong. Jalankan dulu: php artisan marketama:tarik karyawan user');

                return self::FAILURE;
            }

            $hasil = (new Sinkron)->jalankan();

            $this->table(['Keterangan', 'Jumlah'], [
                ['Akun baru dibuat', $hasil['dibuat']],
                ['Akun diperbarui', $hasil['diperbarui']],
                ['Akun aktif', $hasil['aktif']],
                ['Dinonaktifkan (karyawan sudah tidak aktif)', $hasil['nonaktif_karyawan']],
                ['Username diganti karena kembar', $hasil['username_diganti']],
                ['Perlu keputusan admin', $hasil['perlu_keputusan']],
            ]);
        }

        $perlu = DB::table('users as u')
            ->leftJoin('mk_karyawan as k', 'k.id', '=', 'u.karyawan_id')
            ->where('u.perlu_keputusan', true)
            ->orderBy('u.karyawan_id')->orderBy('u.username')
            ->get(['u.username', 'u.kategori', 'u.karyawan_id', 'k.nama', 'u.catatan_migrasi']);

        if ($perlu->isNotEmpty()) {
            $this->warn('Akun yang perlu keputusan admin (nonaktifkan salah satu lewat tinker/phpPgAdmin: users.aktif = false, perlu_keputusan = false):');
            $this->table(['Username', 'Kategori', 'ID karyawan', 'Nama karyawan', 'Catatan'], $perlu->map(fn ($r) => (array) $r)->all());
        } else {
            $this->info('Tidak ada akun yang perlu keputusan admin.');
        }

        $this->line('Password lama tidak ikut; atur password awal dengan: php artisan enesis:atur-password <username>');

        return self::SUCCESS;
    }
}
