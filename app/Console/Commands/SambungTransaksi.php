<?php

namespace App\Console\Commands;

use App\Support\Marketama\SambungTransaksi as Sambung;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SambungTransaksi extends Command
{
    protected $signature = 'enesis:sambung-transaksi
                            {--tabel=* : Hanya tabel tertentu, mis. --tabel=lap_penjualan_barang}
                            {--kolom=* : Hanya kolom ID tertentu, mis. --kolom=karyawan_id --kolom=koordinator_id}';

    protected $description = 'Isi kolom *_id (karyawan, koordinator, depo, area, outlet, produk) di tabel transaksi lap_* dari master mk_*';

    public function handle(): int
    {
        if (DB::table('mk_karyawan')->doesntExist() || DB::table('mk_depo')->doesntExist()) {
            $this->error('Master mk_* masih kosong. Jalankan dulu: php artisan marketama:tarik master karyawan outlet');

            return self::FAILURE;
        }

        $mulai = microtime(true);
        $laporan = (new Sambung(fn (string $pesan) => $this->line($pesan)))->jalankan($this->option('tabel'), $this->option('kolom'));

        $this->table(['Tabel', 'Kolom', 'Tersambung', 'Baris bersumber', '%'], array_map(fn ($r) => [
            $r['tabel'],
            $r['kolom'],
            number_format($r['terisi'], 0, ',', '.'),
            number_format($r['sumber'], 0, ',', '.'),
            $r['sumber'] ? number_format(100 * $r['terisi'] / $r['sumber'], 1, ',', '.') : '-',
        ], $laporan));

        $this->line(sprintf('<fg=gray>Selesai dalam %.0f detik</>', microtime(true) - $mulai));

        return self::SUCCESS;
    }
}
