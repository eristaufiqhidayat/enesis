<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

// Master produk dari Marketama (ditarik 28 Okt 2026, ID asli): satuan, kategori, merk, produk kompetitor, produk.
// Aman diulang: baris baru ditambahkan; baris yang sudah ada hanya diperbarui bila belum pernah diubah di Enesis
// sesudah tanggal data ini (diubah_pada kosong atau lebih lama), jadi perubahan admin tidak tertimpa.
// mk_produk hasil marketama:tarik tetap memakai kolom "data" & "ditarik_pada"-nya.
class MasterProdukSeeder extends Seeder
{
    /** tabel => file CSV di database/seeders/data */
    public const TABEL = [
        'mk_satuan_produk' => 'mk_satuan_produk.csv',
        'mk_kategori_produk' => 'mk_kategori_produk.csv',
        'mk_merk_produk' => 'mk_merk_produk.csv',
        'mk_produk_kompetitor' => 'mk_produk_kompetitor.csv',
        'mk_produk' => 'mk_produk.csv',
    ];

    public function run(): void
    {
        foreach (self::TABEL as $tabel => $file) {
            [$baru, $diperbarui] = $this->muat($tabel, database_path('seeders/data/'.$file));
            $this->command?->info("{$tabel}: {$baru} baru, {$diperbarui} diperbarui");
        }
    }

    /** @return array{0:int,1:int} */
    public function muat(string $tabel, string $path): array
    {
        $baru = $diperbarui = 0;
        $fh = fopen($path, 'r');
        $kolom = fgetcsv($fh, escape: '');

        while (($nilai = fgetcsv($fh, escape: '')) !== false) {
            if ($nilai === [null]) {
                continue;
            }

            $baris = array_map(fn ($v) => $v === '' ? null : $v, array_combine($kolom, $nilai));
            $ada = DB::table($tabel)->where('id', $baris['id'])->first(['id', 'diubah_pada']);

            if (! $ada) {
                DB::table($tabel)->insert($baris);
                $baru++;
            } elseif ($ada->diubah_pada === null || $ada->diubah_pada < $baris['diubah_pada']) {
                DB::table($tabel)->where('id', $baris['id'])->update($baris);
                $diperbarui++;
            }
        }

        fclose($fh);

        return [$baru, $diperbarui];
    }
}
