<?php

namespace App\Console\Commands;

use App\Support\Wilayah\SinkronWilayah;
use App\Support\Wilayah\WilayahBps;
use Illuminate\Console\Command;
use RuntimeException;

class SinkronWilayahResmi extends Command
{
    protected $signature = 'wilayah:sinkron-resmi
                            {--provinsi=* : Kode provinsi 2 digit (mis. 32); kosong = seluruh Indonesia}
                            {--sampai=kelurahan : Tingkat terakhir yang diambil: provinsi | kota | kecamatan | kelurahan}
                            {--simpan : Tulis ke database: tandai sumber + kode BPS, tambah wilayah resmi yang belum ada}
                            {--perbaiki : Bersama --simpan: ganti nama dan induk yang berbeda dengan data resmi}
                            {--segarkan : Abaikan cache jawaban API, ambil ulang dari BPS}
                            {--laporan= : Lokasi berkas CSV laporan (bawaan storage/app/private/wilayah-resmi/laporan)}';

    protected $description = 'Bandingkan & sinkronkan master provinsi–kelurahan dengan data resmi Kemendagri (API relasi kode BPS–Kemendagri)';

    public function handle(SinkronWilayah $sinkron, WilayahBps $bps): int
    {
        $sampai = (string) $this->option('sampai');
        if (! array_key_exists($sampai, SinkronWilayah::TINGKAT)) {
            $this->error('--sampai harus salah satu: '.implode(', ', array_keys(SinkronWilayah::TINGKAT)));

            return self::FAILURE;
        }
        if ($this->option('perbaiki') && ! $this->option('simpan')) {
            $this->error('--perbaiki hanya berlaku bersama --simpan.');

            return self::FAILURE;
        }

        $provinsi = collect($this->option('provinsi'))->map(fn ($p) => preg_replace('/\D/', '', (string) $p))
            ->filter(fn ($p) => strlen($p) === 2)->unique()->values()->all();
        $bps->segarkan = (bool) $this->option('segarkan');

        $bar = null;
        try {
            $ringkasan = $sinkron->jalankan(
                $provinsi,
                $sampai,
                (bool) $this->option('simpan'),
                (bool) $this->option('perbaiki'),
                function (string $pesan, ?int $total) use (&$bar) {
                    $bar?->finish();
                    $this->newLine();
                    $this->info($pesan);
                    $bar = $this->output->createProgressBar(max(1, (int) $total));
                },
                function () use (&$bar) {
                    $bar?->advance();
                },
            );
        } catch (RuntimeException $e) {
            $this->newLine();
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $bar?->finish();
        $this->newLine(2);

        $kolom = ['cocok', 'beda_nama', 'beda_induk', 'baru', 'tidak_resmi', 'tambahan', 'tidak_dicek', 'ditambah', 'diubah'];
        $this->table(array_merge(['Tingkat'], $kolom), collect($ringkasan)->map(fn ($h, $t) => array_merge(
            [$t], array_map(fn ($k) => number_format($h[$k] ?? 0, 0, ',', '.'), $kolom)
        ))->values()->all());

        $berkas = $this->option('laporan') ?: rtrim(config('wilayah_resmi.laporan_dir'), '/').'/wilayah-resmi-'.now()->format('Ymd-His').'.csv';
        $sinkron->tulisLaporan($berkas);
        $this->line('Laporan: '.$berkas.' ('.number_format(count($sinkron->laporan), 0, ',', '.').' baris)');

        $tidakDicek = array_sum(array_column($ringkasan, 'tidak_dicek'));
        if ($tidakDicek) {
            $this->warn("{$tidakDicek} baris belum dinilai karena induknya gagal diambil dari API. Jalankan ulang (jawaban yang berhasil sudah tersimpan di cache).");
        }
        if (! $this->option('simpan')) {
            $this->comment('Belum ada yang ditulis ke database. Tambahkan --simpan untuk menerapkan, dan --perbaiki untuk mengganti nama/induk yang berbeda.');
        }

        return self::SUCCESS;
    }
}
