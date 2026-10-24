<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Tabel API mobile Enesis: token perangkat, kunci idempotensi, unggahan, dan transaksi
// motoris (trx_*) yang memakai ID master mk_* (karyawan, depo, outlet, produk).
// Kolom umum transaksi: client_ref (UUID dari HP), recorded_at (jam HP), received_at
// (jam server), clock_skew, device_id, app_version.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->index();
            $table->string('token_hash', 64)->unique();
            $table->string('device_id');
            $table->string('device_model')->nullable();
            $table->string('app_version', 20)->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoked_reason', 30)->nullable(); // logout | device_replaced | idle | admin
            $table->timestamps();
        });

        Schema::create('mobile_idempotency', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->string('kunci', 100);
            $table->string('endpoint', 150);
            $table->unsignedSmallInteger('status');
            $table->longText('respons');
            $table->timestamp('created_at')->nullable()->index();
            $table->unique(['user_id', 'kunci']);
        });

        Schema::create('mobile_uploads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->index();
            $table->string('jenis', 30); // selfie | toko | rak | lampiran
            $table->string('disk', 30);
            $table->string('path');
            $table->string('mime', 50);
            $table->unsignedInteger('ukuran');
            $table->timestamp('created_at')->nullable();
        });

        $umum = function (Blueprint $table) {
            $table->uuid('client_ref')->nullable()->unique();
            $table->timestamp('recorded_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->boolean('clock_skew')->default(false);
            $table->string('device_id')->nullable();
            $table->string('app_version', 20)->nullable();
        };

        Schema::create('trx_absensi', function (Blueprint $table) use ($umum) {
            $table->id();
            $table->unsignedBigInteger('karyawan_id')->index();
            $table->unsignedBigInteger('depo_id')->nullable()->index();
            $table->date('tanggal');
            $table->timestamp('masuk_at')->nullable();
            $table->decimal('masuk_lat', 10, 7)->nullable();
            $table->decimal('masuk_lng', 11, 7)->nullable();
            $table->unsignedInteger('masuk_akurasi_m')->nullable();
            $table->uuid('masuk_foto_id')->nullable();
            $table->boolean('terlambat')->default(false);
            $table->boolean('mock_location')->default(false);
            $table->timestamp('pulang_at')->nullable();
            $table->decimal('pulang_lat', 10, 7)->nullable();
            $table->decimal('pulang_lng', 11, 7)->nullable();
            $table->uuid('pulang_client_ref')->nullable()->unique();
            $umum($table);
            $table->unique(['karyawan_id', 'tanggal']);
        });

        Schema::create('trx_checkin_depo', function (Blueprint $table) use ($umum) {
            $table->id();
            $table->unsignedBigInteger('karyawan_id')->index();
            $table->unsignedBigInteger('depo_id')->index();
            $table->date('tanggal');
            $table->timestamp('checkin_at')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 11, 7)->nullable();
            $table->unsignedInteger('jarak_m')->nullable();
            $table->timestamp('checkout_at')->nullable();
            $table->uuid('checkout_client_ref')->nullable()->unique();
            $umum($table);
            $table->unique(['karyawan_id', 'tanggal']);
        });

        // Muatan canvassing yang disiapkan staf depo (dari web), diterima/dikembalikan sales.
        Schema::create('trx_canvassing', function (Blueprint $table) use ($umum) {
            $table->id();
            $table->string('nomor', 50)->unique();
            $table->unsignedBigInteger('karyawan_id')->index();
            $table->unsignedBigInteger('depo_id')->index();
            $table->date('tanggal')->index();
            $table->string('status', 20)->default('disiapkan'); // disiapkan | diterima | dikembalikan | selesai
            $table->timestamp('disiapkan_at')->nullable();
            $table->timestamp('diterima_at')->nullable();
            $table->timestamp('dikembalikan_at')->nullable();
            $table->uuid('return_client_ref')->nullable()->unique();
            $umum($table);
        });

        Schema::create('trx_canvassing_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('canvassing_id')->index();
            $table->unsignedBigInteger('produk_id');
            $table->decimal('qty_bawa', 12, 2);
            $table->decimal('qty_diterima', 12, 2)->nullable();
            $table->string('alasan_selisih_terima')->nullable();
            $table->decimal('qty_kembali', 12, 2)->nullable();
            $table->string('alasan_selisih_kembali')->nullable();
            $table->unique(['canvassing_id', 'produk_id']);
        });

        Schema::create('trx_outlet_baru', function (Blueprint $table) use ($umum) {
            $table->id();
            $table->unsignedBigInteger('karyawan_id')->index();
            $table->unsignedBigInteger('depo_id')->nullable();
            $table->unsignedBigInteger('area_id')->nullable();
            $table->string('nama');
            $table->string('pemilik')->nullable();
            $table->string('no_hp', 30)->nullable();
            $table->string('tipe', 30)->nullable();
            $table->text('alamat')->nullable();
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 11, 7);
            $table->uuid('foto_toko_id')->nullable();
            $table->uuid('foto_rak_id')->nullable();
            $table->string('status', 20)->default('menunggu'); // menunggu | disetujui | ditolak
            $table->unsignedBigInteger('outlet_id')->nullable(); // mk_outlet setelah disetujui
            $umum($table);
        });

        Schema::create('trx_kunjungan', function (Blueprint $table) use ($umum) {
            $table->id();
            $table->unsignedBigInteger('karyawan_id')->index();
            $table->unsignedBigInteger('depo_id')->nullable();
            $table->unsignedBigInteger('outlet_id')->nullable()->index();
            $table->unsignedBigInteger('outlet_baru_id')->nullable();
            $table->date('tanggal')->index();
            $table->timestamp('checkin_at');
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 11, 7)->nullable();
            $table->unsignedInteger('jarak_m')->nullable();
            $table->uuid('foto_id')->nullable();
            $table->boolean('di_luar_rute')->default(false);
            $table->boolean('mock_location')->default(false);
            $table->string('tidak_beli_kategori', 50)->nullable();
            $table->string('tidak_beli_keterangan')->nullable();
            $table->timestamp('checkout_at')->nullable();
            $table->unsignedInteger('durasi_detik')->nullable();
            $umum($table);
        });

        Schema::create('trx_stok_outlet', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kunjungan_id')->index();
            $table->unsignedBigInteger('produk_id')->nullable(); // null = produk kompetitor
            $table->string('produk_kompetitor')->nullable();
            $table->decimal('qty', 12, 2);
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('trx_penjualan', function (Blueprint $table) use ($umum) {
            $table->id();
            $table->string('kode', 50)->unique();
            $table->foreignId('kunjungan_id')->index();
            $table->unsignedBigInteger('karyawan_id')->index();
            $table->unsignedBigInteger('depo_id')->nullable()->index();
            $table->unsignedBigInteger('outlet_id')->nullable()->index();
            $table->unsignedBigInteger('canvassing_id')->nullable();
            $table->date('tanggal')->index();
            $table->unsignedInteger('jumlah_sku');
            $table->decimal('total_qty', 12, 2);
            $table->unsignedBigInteger('total');
            $table->unsignedBigInteger('total_diskon')->default(0);
            $table->unsignedInteger('poin')->default(0);
            $table->boolean('is_effective_call');
            $table->unsignedBigInteger('client_total')->nullable();
            $table->timestamp('dibatalkan_at')->nullable();
            $table->string('alasan_batal')->nullable();
            $umum($table);
        });

        Schema::create('trx_penjualan_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('penjualan_id')->index();
            $table->unsignedBigInteger('produk_id');
            $table->decimal('qty', 12, 2);
            $table->unsignedBigInteger('harga');
            $table->unsignedBigInteger('diskon')->default(0);
            $table->unsignedBigInteger('total');
            $table->unsignedInteger('poin')->default(0);
        });

        Schema::create('trx_tracking', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('karyawan_id');
            $table->timestamp('recorded_at');
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 11, 7);
            $table->unsignedInteger('akurasi_m')->nullable();
            $table->boolean('akurasi_rendah')->default(false);
            $table->boolean('mock_location')->default(false);
            $table->unsignedSmallInteger('baterai')->nullable();
            $table->string('device_id')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->unique(['karyawan_id', 'recorded_at']);
        });

        // Pencarian outlet terdekat (saring kotak lintang/bujur).
        Schema::table('mk_outlet', function (Blueprint $table) {
            $table->index(['latitude', 'longitude'], 'mk_outlet_lat_lng_index');
        });
    }

    public function down(): void
    {
        Schema::table('mk_outlet', fn (Blueprint $table) => $table->dropIndex('mk_outlet_lat_lng_index'));

        foreach (['trx_tracking', 'trx_penjualan_item', 'trx_penjualan', 'trx_stok_outlet', 'trx_kunjungan', 'trx_outlet_baru', 'trx_canvassing_item', 'trx_canvassing', 'trx_checkin_depo', 'trx_absensi', 'mobile_uploads', 'mobile_idempotency', 'mobile_tokens'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
