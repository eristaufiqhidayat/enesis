<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Kolom outlet yang ada di Marketama (New Open Outlet / Outlet All) tetapi belum ada di Enesis:
// - trx_outlet_baru: contact person & no HP kedua, wilayah (provinsi s/d kelurahan + kode Kemendagri), tanggal daftar
//   (tanggal lokal, untuk menu New Open Outlet = outlet yang didaftarkan pada tanggal terpilih);
// - mk_outlet: contact person 1/2, no HP 1/2, tipe outlet, foto toko, sales pembuat, kode kelurahan.
// Wilayah data lama diisi dengan: php artisan enesis:wilayah-outlet
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trx_outlet_baru', function (Blueprint $table) {
            $table->string('pemilik2', 150)->nullable();
            $table->string('no_hp2', 30)->nullable();
            $table->string('provinsi')->nullable();
            $table->string('kota_kabupaten')->nullable();
            $table->string('kecamatan')->nullable();
            $table->string('kelurahan')->nullable();
            $table->string('kode_kelurahan', 13)->nullable();
            $table->date('tanggal')->nullable()->index();
        });

        Schema::table('mk_outlet', function (Blueprint $table) {
            $table->string('kontak1', 150)->nullable();
            $table->string('kontak2', 150)->nullable();
            $table->string('no_hp1', 30)->nullable();
            $table->string('no_hp2', 30)->nullable();
            $table->string('tipe', 30)->nullable();
            $table->uuid('foto_id')->nullable(); // mobile_uploads (foto toko dari HP)
            $table->unsignedBigInteger('karyawan_id')->nullable(); // sales yang mendaftarkan (outlet dari HP)
            $table->string('kode_kelurahan', 13)->nullable();
        });

        // Tanggal daftar (zona waktu aplikasi) untuk data yang sudah ada.
        $zona = config('mobile.zona_waktu', 'Asia/Jakarta');
        DB::table('trx_outlet_baru')->whereNull('tanggal')->orderBy('id')->chunkById(500, function ($baris) use ($zona) {
            foreach ($baris as $b) {
                $waktu = $b->recorded_at ?? $b->received_at ?? $b->created_at ?? null;

                if ($waktu) {
                    DB::table('trx_outlet_baru')->where('id', $b->id)
                        ->update(['tanggal' => CarbonImmutable::parse($waktu, 'UTC')->setTimezone($zona)->toDateString()]);
                }
            }
        });

        // Outlet hasil persetujuan: salin kontak, tipe, foto, dan sales dari pendaftarannya.
        DB::table('trx_outlet_baru')->where('status', 'disetujui')->whereNotNull('outlet_id')->orderBy('id')
            ->chunkById(500, function ($baris) {
                foreach ($baris as $b) {
                    DB::table('mk_outlet')->where('id', $b->outlet_id)->update([
                        'kontak1' => $b->pemilik, 'no_hp1' => $b->no_hp, 'tipe' => $b->tipe,
                        'foto_id' => $b->foto_toko_id, 'karyawan_id' => $b->karyawan_id,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('mk_outlet', function (Blueprint $table) {
            $table->dropColumn(['kontak1', 'kontak2', 'no_hp1', 'no_hp2', 'tipe', 'foto_id', 'karyawan_id', 'kode_kelurahan']);
        });

        Schema::table('trx_outlet_baru', function (Blueprint $table) {
            $table->dropIndex(['tanggal']);
            $table->dropColumn(['pemilik2', 'no_hp2', 'provinsi', 'kota_kabupaten', 'kecamatan', 'kelurahan', 'kode_kelurahan', 'tanggal']);
        });
    }
};
