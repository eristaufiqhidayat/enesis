<?php

namespace App\Console\Commands;

use App\Support\Marketama\MarketamaClient;
use App\Support\Marketama\Penarik;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class TarikMarketama extends Command
{
    protected $signature = 'marketama:tarik
                            {bagian?* : master, karyawan, user, outlet, sambung, atau mentah:<menu> (default: master karyawan user outlet sambung)}
                            {--tanpa-detail : Jangan buka form edit per karyawan/user (lebih cepat, relasi dari nama)}
                            {--ulang : Abaikan posisi tersimpan, tarik dari awal}
                            {--area-dari-menu : Tarik area dari menu Master Area (lambat) walaupun area_polygon sudah berisi}
                            {--batas= : Batasi jumlah baris/form per menu (uji coba)}
                            {--status : Tampilkan progres tarikan saja}';

    protected $description = 'Tarik data langsung dari aplikasi lama Marketama (baca saja) ke tabel mk_*';

    public function handle(): int
    {
        if ($this->option('status')) {
            return $this->tampilkanStatus();
        }

        $cfg = config('marketama_sumber');

        if (! $cfg['username'] || ! $cfg['password']) {
            $this->error('Isi MARKETAMA_USERNAME dan MARKETAMA_PASSWORD di .env terlebih dahulu.');

            return self::FAILURE;
        }

        $bagian = $this->argument('bagian') ?: ['master', 'karyawan', 'user', 'outlet', 'sambung'];
        $detail = ! $this->option('tanpa-detail');
        $client = MarketamaClient::dariConfig();
        $penarik = (new Penarik($client, fn (string $pesan) => $this->line($pesan)))
            ->ulang((bool) $this->option('ulang'))
            ->areaDariMenuSumber((bool) $this->option('area-dari-menu'))
            ->batas($this->option('batas') !== null ? (int) $this->option('batas') : null);

        try {
            $this->info("Login ke {$cfg['url']} ...");
            $client->login($cfg['username'], $cfg['password']);

            foreach ($bagian as $b) {
                $mulai = microtime(true);
                $this->info("== {$b}");

                match (true) {
                    $b === 'master' => $penarik->master(),
                    $b === 'karyawan' => $penarik->karyawan($detail),
                    $b === 'user' => $penarik->user($detail),
                    $b === 'outlet' => $penarik->outlet(),
                    $b === 'sambung' => $penarik->sambung(),
                    str_starts_with($b, 'mentah:') => $penarik->mentah(substr($b, 7)),
                    default => $this->warn("Bagian tidak dikenal: {$b}"),
                };

                $this->line(sprintf('<fg=gray>%s selesai dalam %.0f detik</>', $b, microtime(true) - $mulai));
            }
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            $this->line('Progres tersimpan; jalankan perintah yang sama untuk melanjutkan.');

            return self::FAILURE;
        }

        return $this->tampilkanStatus();
    }

    private function tampilkanStatus(): int
    {
        $rows = DB::table('mk_tarikan')->orderBy('kunci')->get()->map(fn ($r) => [
            $r->kunci,
            $r->total_sumber === null ? '-' : number_format($r->total_sumber, 0, ',', '.'),
            number_format($r->tersimpan, 0, ',', '.'),
            $r->selesai ? 'ya' : 'belum',
            $r->catatan,
        ]);

        $this->table(['Menu', 'Total di sumber', 'Tersimpan', 'Selesai', 'Catatan'], $rows);

        return self::SUCCESS;
    }
}
