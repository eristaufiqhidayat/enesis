<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Kolom form karyawan Marketama (Karyawan Sales / Koordinator / Supervisor / Lainnya) untuk CRUD karyawan di Enesis:
// jabatan (Karyawan Lainnya: Supervisor, Principal, Auditor), wilayah depo (koordinator), gaji & tunjangan, foto,
// dan jejak perubahan. Karyawan yang sudah diubah di Enesis (diubah_pada terisi) tidak ditimpa marketama:tarik.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mk_karyawan', function (Blueprint $table) {
            $table->string('jabatan', 50)->nullable();
            $table->unsignedBigInteger('wilayah_depo_id')->nullable();
            $table->decimal('gaji_pokok', 18, 2)->nullable();
            $table->decimal('tunjangan_bbm_makan', 18, 2)->nullable();
            $table->decimal('sewa_motor', 18, 2)->nullable();
            $table->decimal('tunjangan_internet', 18, 2)->nullable();
            $table->string('foto')->nullable();
            $table->string('diubah_oleh')->nullable();
            $table->timestamp('diubah_pada')->nullable();
        });

        // Isi dari salinan daftar Marketama (kolom data): jabatan Karyawan Lainnya, wilayah depo koordinator.
        $wilayah = DB::table('mk_wilayah_depo')->get(['id', 'nama', 'depo_id'])
            ->mapWithKeys(fn ($w) => [mb_strtolower(trim($w->nama)).'|'.$w->depo_id => $w->id]);

        DB::table('mk_karyawan')->whereIn('kategori', ['lainnya', 'koordinator', 'supervisor'])->orderBy('id')
            ->chunkById(500, function ($baris) use ($wilayah) {
                foreach ($baris as $k) {
                    $data = json_decode((string) $k->data, true) ?: [];
                    $ubah = [];

                    if ($k->kategori === 'lainnya' && ! empty($data['kategori'])) {
                        $ubah['jabatan'] = trim((string) $data['kategori']);
                    }

                    if ($k->kategori === 'supervisor') {
                        $ubah['jabatan'] = 'Supervisor Nasional';
                    }

                    if ($k->kategori === 'koordinator' && ! empty($data['wilayah_depo']) && $data['wilayah_depo'] !== '-') {
                        $ubah['wilayah_depo_id'] = $wilayah[mb_strtolower(trim((string) $data['wilayah_depo'])).'|'.$k->depo_id] ?? null;
                    }

                    if ($ubah) {
                        DB::table('mk_karyawan')->where('id', $k->id)->update($ubah);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('mk_karyawan', function (Blueprint $table) {
            $table->dropColumn([
                'jabatan', 'wilayah_depo_id', 'gaji_pokok', 'tunjangan_bbm_makan', 'sewa_motor', 'tunjangan_internet',
                'foto', 'diubah_oleh', 'diubah_pada',
            ]);
        });
    }
};
