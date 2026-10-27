<?php

namespace App\Console\Commands;

use App\Support\Area\AreaMk;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SinkronAreaMk extends Command
{
    protected $signature = 'area:sinkron-mk
                            {--semua : Ikut salin ulang area tarikan Marketama (bawaan hanya area buatan Enesis)}';

    protected $description = 'Salin area_polygon ke mk_area supaya area baru (Generate, Auto Grid, gambar) bisa dipakai HP, outlet, dan penugasan sales';

    public function handle(): int
    {
        $ids = $this->option('semua') ? null : DB::table('area_polygon')->whereNull('source_id')->pluck('id')->all();
        $jumlah = AreaMk::sinkron($ids);

        $this->info(number_format($jumlah, 0, ',', '.').' area disalin ke mk_area.');
        $this->line('ID mk_area area buatan Enesis = '.number_format(AreaMk::ID_AWAL, 0, ',', '.').' + ID area_polygon.');

        return self::SUCCESS;
    }
}
