<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

// Menyiapkan muatan canvassing untuk satu sales (pengganti sementara form web staf depo,
// yang belum dibuat) supaya alur aplikasi mobile bisa diuji.
class SiapkanCanvassing extends Command
{
    protected $signature = 'enesis:siapkan-canvassing
                            {karyawan : ID karyawan sales (mk_karyawan.id)}
                            {--produk=* : produk_id:qty, mis. --produk=3:24 (bawaan: 5 produk berharga pertama, qty 24)}
                            {--tanggal= : Y-m-d (bawaan: hari ini WIB)}';

    protected $description = 'Buat trx_canvassing untuk uji aplikasi mobile (sementara, sebelum form web ada)';

    public function handle(): int
    {
        $karyawan = DB::table('mk_karyawan')->where('id', $this->argument('karyawan'))->first();

        if (! $karyawan || ! $karyawan->depo_id) {
            $this->error('Karyawan tidak ditemukan atau belum punya depo.');

            return self::FAILURE;
        }

        $tanggal = $this->option('tanggal') ?: now(config('mobile.zona_waktu'))->toDateString();
        $produk = collect($this->option('produk'))->mapWithKeys(function ($p) {
            [$id, $qty] = array_pad(explode(':', $p, 2), 2, 24);

            return [(int) $id => (float) $qty];
        });

        if ($produk->isEmpty()) {
            $produk = DB::table('mk_produk')->whereNotNull('harga_jual')->orderBy('id')->limit(5)->pluck('id')->mapWithKeys(fn ($id) => [(int) $id => 24.0]);
        }

        if ($produk->isEmpty()) {
            $this->error('Belum ada produk berharga di mk_produk.');

            return self::FAILURE;
        }

        $nomor = DB::transaction(function () use ($karyawan, $tanggal, $produk) {
            $id = DB::table('trx_canvassing')->insertGetId([
                'nomor' => 'TMP-'.uniqid(),
                'karyawan_id' => $karyawan->id,
                'depo_id' => $karyawan->depo_id,
                'tanggal' => $tanggal,
                'status' => 'disiapkan',
                'disiapkan_at' => now(),
            ]);
            $nomor = 'CNV'.str_replace('-', '', $tanggal).'-'.str_pad((string) $id, 5, '0', STR_PAD_LEFT);
            DB::table('trx_canvassing')->where('id', $id)->update(['nomor' => $nomor]);
            DB::table('trx_canvassing_item')->insert($produk->map(fn ($qty, $pid) => ['canvassing_id' => $id, 'produk_id' => $pid, 'qty_bawa' => $qty])->values()->all());

            return $nomor;
        });

        $this->info("Canvassing $nomor dibuat untuk {$karyawan->nama} ($tanggal), {$produk->count()} produk.");

        return self::SUCCESS;
    }
}
