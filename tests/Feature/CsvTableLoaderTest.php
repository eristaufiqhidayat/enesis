<?php

namespace Tests\Feature;

use App\Support\CsvTableLoader;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CsvTableLoaderTest extends TestCase
{
    public function test_sel_kosong_di_kolom_not_null_memakai_default_dan_nullable_tetap_null(): void
    {
        Schema::create('uji_loader', function (Blueprint $table) {
            $table->id();
            $table->string('nama')->nullable();
            $table->boolean('aktif')->default(false);
            $table->integer('skor')->default(0);
            $table->integer('peringkat');
            $table->string('catatan')->nullable();
        });

        $path = sys_get_temp_dir().'/uji_loader_'.uniqid().'.csv';
        // BOM di awal, sel kosong di kolom NOT NULL ber-default (aktif, skor), NOT NULL tanpa default (peringkat),
        // dan nullable (catatan).
        file_put_contents($path, "\xEF\xBB\xBFid,nama,aktif,skor,peringkat,catatan\n1,Ani,,,,\n2,Budi,1,5,3,catatan\n");

        try {
            $jumlah = CsvTableLoader::load('uji_loader', $path);
            $baris = DB::table('uji_loader')->orderBy('id')->get();

            $this->assertSame(2, $jumlah);
            $this->assertSame('Ani', $baris[0]->nama);
            $this->assertEquals(0, $baris[0]->aktif);
            $this->assertEquals(0, $baris[0]->skor);
            $this->assertEquals(0, $baris[0]->peringkat);
            $this->assertNull($baris[0]->catatan);
            $this->assertEquals(1, $baris[1]->aktif);
            $this->assertEquals(5, $baris[1]->skor);
            $this->assertSame('catatan', $baris[1]->catatan);
        } finally {
            @unlink($path);
            Schema::dropIfExists('uji_loader');
        }
    }
}
