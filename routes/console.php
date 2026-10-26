<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('enesis:bersihkan-mobile')->dailyAt('02:00');

// Backup data: proses yang menunggu (cadangan bila proses setelah respons halaman terhenti) dan hapus file kedaluwarsa.
Schedule::command('enesis:backup')->everyMinute()->withoutOverlapping();
