<?php

namespace App\Console\Commands;

use App\Support\Marketama\MigrasiTransaksi as Migrasi;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrasiTransaksi extends Command
{
    protected $signature = 'enesis:migrasi-transaksi
                            {jenis?* : absensi, checkin_depo, kunjungan, join_visit, absensi_koordinator, penjualan, stok_outlet, stok_kompetitor, canvassing (kosong = semua)}
                            {--tanpa-sambung : Lewati pengisian ID karyawan/depo di tabel lama (sudah dijalankan)}';

    protected $description = 'Salin data aplikasi lama (lap_*) ke tabel motoris (trx_*); aman diulang, data motoris tidak ditimpa';

    public function handle(): int
    {
        $jenis = $this->argument('jenis');
        $salah = array_diff($jenis, array_keys(Migrasi::JENIS));

        if ($salah) {
            $this->error('Jenis tidak dikenal: '.implode(', ', $salah).'. Pilihan: '.implode(', ', array_keys(Migrasi::JENIS)));

            return self::FAILURE;
        }

        if (DB::table('mk_karyawan')->doesntExist()) {
            $this->error('Master karyawan masih kosong. Jalankan dulu: php artisan marketama:tarik master karyawan');

            return self::FAILURE;
        }

        $mulai = microtime(true);
        $laporan = (new Migrasi(fn (string $pesan) => $this->line($pesan)))->jalankan($jenis, ! $this->option('tanpa-sambung'));
        $angka = fn (int $n) => number_format($n, 0, ',', '.');

        $this->table(['Jenis', 'Baris lama', 'Dipindah', 'Sudah ada (migrasi sebelumnya)', 'Dilewati'], array_map(fn ($r) => [
            $r['jenis'],
            $angka($r['sumber']),
            $angka($r['dipindah']),
            $angka($r['sudah']),
            collect($r['lewati'])->map(fn ($n, $alasan) => $alasan.': '.$angka($n))->join(', ') ?: '-',
        ], $laporan));

        $this->line(sprintf('<fg=gray>Selesai dalam %.0f detik</>', microtime(true) - $mulai));

        return self::SUCCESS;
    }
}
