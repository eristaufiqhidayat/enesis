<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

// Pemeliharaan harian API mobile: cabut token yang lama tidak dipakai dan hapus kunci
// idempotensi yang sudah melewati masa simpan.
class BersihkanMobile extends Command
{
    protected $signature = 'enesis:bersihkan-mobile';

    protected $description = 'Cabut token mobile yang menganggur dan hapus kunci idempotensi lama';

    public function handle(): int
    {
        $token = DB::table('mobile_tokens')->whereNull('revoked_at')
            ->where('last_used_at', '<', now()->subDays(config('mobile.token_idle_hari')))
            ->update(['revoked_at' => now(), 'revoked_reason' => 'idle', 'updated_at' => now()]);

        $kunci = DB::table('mobile_idempotency')->where('created_at', '<', now()->subDays(config('mobile.idempotensi_hari')))->delete();

        $this->info("Token dicabut: $token, kunci idempotensi dihapus: $kunci.");

        return self::SUCCESS;
    }
}
