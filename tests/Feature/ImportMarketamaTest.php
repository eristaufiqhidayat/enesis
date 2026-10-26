<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ImportMarketamaTest extends TestCase
{
    use RefreshDatabase;

    public function test_mk_distributor_ikut_diimpor_dari_folder_csv(): void
    {
        $dir = sys_get_temp_dir().'/import_marketama_'.uniqid();
        mkdir($dir);
        file_put_contents($dir.'/mk_distributor.csv', "\xEF\xBB\xBFid,kode,nama,depo_id\n1,DIST001,PT Satu,46\n2,DIST002,PT Dua,22\n");

        try {
            $this->artisan('marketama:import', ['dir' => $dir, '--only' => ['mk_distributor']])
                ->expectsOutputToContain('mk_distributor: 2 baris')
                ->assertSuccessful();

            $this->assertSame(['PT Satu', 'PT Dua'], DB::table('mk_distributor')->orderBy('id')->pluck('nama')->all());
        } finally {
            @unlink($dir.'/mk_distributor.csv');
            @rmdir($dir);
        }
    }
}
