<?php

namespace App\Jobs;

use App\Support\Backup\PembuatBackup;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

// Membuat file backup satu baris backup_data. Dipanggil setelah respons halaman terkirim (dispatchAfterResponse,
// tidak butuh queue worker di hosting); cadangan: enesis:backup lewat scheduler memproses yang masih menunggu.
class BuatBackup implements ShouldQueue
{
    use Queueable;

    public int $timeout = 0;

    public function __construct(public readonly int $backupId) {}

    public function handle(): void
    {
        (new PembuatBackup($this->backupId))->jalankan();
    }
}
