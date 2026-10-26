<?php

namespace App\Console\Commands;

use App\Support\Backup\DaftarTabel;
use App\Support\Backup\PembuatBackup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class Backup extends Command
{
    protected $signature = 'enesis:backup
                            {tabel?* : Buat backup baru untuk tabel ini (kosong = proses backup yang menunggu dari halaman web)}
                            {--format=sql : csv atau sql}
                            {--semua : Semua tabel}';

    protected $description = 'Proses backup data yang menunggu (dari menu Backup Data), atau buat backup baru dari terminal';

    public function handle(): int
    {
        // Backup yang macet (proses tanpa kemajuan 30 menit, mis. PHP dihentikan hosting) ditandai gagal.
        DB::table('backup_data')->where('status', 'proses')->where('updated_at', '<', now()->subMinutes(30))
            ->update(['status' => 'gagal', 'pesan' => 'Proses berhenti di tengah jalan (tanpa kemajuan 30 menit). Coba lagi, atau jalankan php artisan enesis:backup dari terminal.', 'updated_at' => now()]);

        $tabel = $this->option('semua') ? DaftarTabel::semua() : $this->argument('tabel');

        if ($tabel) {
            $saring = DaftarTabel::saring($tabel);
            $salah = array_diff($tabel, $saring);

            if ($salah) {
                $this->error('Tabel tidak dikenal: '.implode(', ', $salah));

                return self::FAILURE;
            }

            $format = $this->option('format') === 'csv' ? 'csv' : 'sql';
            $id = DB::table('backup_data')->insertGetId([
                'format' => $format, 'tabel' => json_encode($saring),
                'opsi' => json_encode(['header' => true, 'pemisah' => 'koma', 'struktur' => true, 'drop' => false, 'per_tabel' => false, 'rahasia' => true]),
                'status' => 'menunggu', 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->line("Backup #{$id}: ".count($saring)." tabel, format {$format}…");
        }

        $diproses = 0;

        foreach (DB::table('backup_data')->where('status', 'menunggu')->orderBy('id')->pluck('id') as $id) {
            (new PembuatBackup((int) $id))->jalankan();
            $b = DB::table('backup_data')->find($id);
            $this->line(sprintf('#%d %s%s', $id, $b->status, $b->nama_file ? ' → storage/app/private/backup/'.$b->nama_file : ($b->pesan ? ': '.$b->pesan : '')));
            $diproses++;
        }

        $hapus = PembuatBackup::bersihkan();

        if ($diproses || $hapus || $tabel) {
            $this->info("Selesai: {$diproses} backup diproses, {$hapus} file kedaluwarsa dihapus.");
        }

        return self::SUCCESS;
    }
}
