<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Tabel "mk_*" = data master Marketama hasil tarikan langsung dari aplikasi lama
// (php artisan marketama:tarik). Kunci utama memakai ID asli Marketama (data-id / value
// dropdown di form edit), sehingga relasi antar tabel memakai ID, bukan teks nama.
// Kolom "data" menyimpan baris asli dari tabel layar (judul kolom => teks) sebagai jejak.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mk_depo', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary(); // warehouse_id
            $table->string('kode')->nullable();
            $table->string('nama');
            $table->boolean('aktif')->nullable();
            $table->json('data')->nullable();
            $table->timestamp('ditarik_pada')->nullable();
        });

        Schema::create('mk_wilayah_depo', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('nama');
            $table->unsignedBigInteger('depo_id')->nullable()->index();
            $table->json('data')->nullable();
            $table->timestamp('ditarik_pada')->nullable();
        });

        Schema::create('mk_area', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('kode')->nullable()->index();
            $table->string('nama')->index();
            $table->unsignedBigInteger('depo_id')->nullable()->index();
            $table->json('data')->nullable();
            $table->timestamp('ditarik_pada')->nullable();
        });

        Schema::create('mk_distributor', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('kode')->nullable();
            $table->string('nama');
            $table->unsignedBigInteger('depo_id')->nullable()->index();
            $table->string('telepon')->nullable();
            $table->string('contact_person')->nullable();
            $table->text('alamat')->nullable();
            $table->json('data')->nullable();
            $table->timestamp('ditarik_pada')->nullable();
        });

        Schema::create('mk_produk', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('kode')->nullable()->index();
            $table->string('nama');
            $table->unsignedBigInteger('merk_id')->nullable();
            $table->unsignedBigInteger('kategori_id')->nullable();
            $table->unsignedBigInteger('satuan_id')->nullable();
            $table->decimal('harga_beli', 18, 2)->nullable();
            $table->decimal('harga_jual', 18, 2)->nullable();
            $table->json('data')->nullable();
            $table->timestamp('ditarik_pada')->nullable();
        });

        // Semua karyawan (sales aktif + tidak aktif, koordinator, supervisor, lainnya).
        // Gaji & tunjangan sengaja TIDAK ditarik (data minimal).
        Schema::create('mk_karyawan', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary(); // employee id Marketama
            $table->string('kategori', 30)->index(); // sales | koordinator | supervisor | lainnya
            $table->string('nama');
            $table->unsignedBigInteger('depo_id')->nullable()->index();
            $table->unsignedBigInteger('koordinator_id')->nullable()->index();
            $table->unsignedBigInteger('area_aktif_id')->nullable();
            $table->string('no_hp')->nullable();
            $table->string('email')->nullable();
            $table->string('tanggal_mulai')->nullable();
            $table->boolean('aktif')->nullable();
            $table->boolean('detail_lengkap')->default(false); // form edit sudah dibaca
            $table->json('data')->nullable();
            $table->timestamp('ditarik_pada')->nullable();
        });

        Schema::create('mk_karyawan_area', function (Blueprint $table) {
            $table->unsignedBigInteger('karyawan_id');
            $table->unsignedBigInteger('area_id')->index();
            $table->boolean('aktif')->default(false);
            $table->primary(['karyawan_id', 'area_id']);
        });

        // Akun login aplikasi lama (tanpa password: tidak bisa diambil).
        Schema::create('mk_user', function (Blueprint $table) {
            $table->string('jenis', 20); // sales | koordinator | lainnya | super_admin
            $table->unsignedBigInteger('id'); // id user Marketama (per menu)
            $table->string('username')->index();
            $table->string('nickname')->nullable();
            $table->unsignedBigInteger('karyawan_id')->nullable()->index();
            $table->string('nama_karyawan')->nullable();
            $table->boolean('aktif')->nullable();
            $table->boolean('detail_lengkap')->default(false);
            $table->json('data')->nullable();
            $table->timestamp('ditarik_pada')->nullable();
            $table->primary(['jenis', 'id']);
        });

        Schema::create('mk_outlet', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('kode')->nullable()->index();
            $table->string('nama')->nullable();
            $table->text('alamat')->nullable();
            $table->unsignedBigInteger('area_id')->nullable()->index();
            $table->string('area')->nullable();
            $table->string('provinsi')->nullable();
            $table->string('kota_kabupaten')->nullable();
            $table->string('kecamatan')->nullable();
            $table->string('kelurahan')->nullable();
            $table->decimal('longitude', 11, 7)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->string('status_fiktif')->nullable();
            $table->string('verifikasi')->nullable();
            $table->string('dibuat_oleh')->nullable();
            $table->string('dibuat_pada')->nullable();
            $table->timestamp('ditarik_pada')->nullable();
        });

        // Baris mentah menu lain (mis. transaksi penjualan_barang) untuk diolah belakangan.
        Schema::create('mk_baris_mentah', function (Blueprint $table) {
            $table->string('menu', 60);
            $table->unsignedBigInteger('id');
            $table->json('data');
            $table->timestamp('ditarik_pada')->nullable();
            $table->primary(['menu', 'id']);
        });

        // Catatan per menu: posisi terakhir (untuk melanjutkan) dan jumlah di sumber.
        Schema::create('mk_tarikan', function (Blueprint $table) {
            $table->string('kunci', 80)->primary(); // mis. "list:outlet_all" atau "edit:karyawan_sales"
            $table->unsignedBigInteger('total_sumber')->nullable();
            $table->unsignedBigInteger('posisi')->default(0);
            $table->unsignedBigInteger('tersimpan')->default(0);
            $table->boolean('selesai')->default(false);
            $table->text('catatan')->nullable();
            $table->timestamp('mulai_pada')->nullable();
            $table->timestamp('selesai_pada')->nullable();
        });
    }

    public function down(): void
    {
        foreach (['mk_tarikan', 'mk_baris_mentah', 'mk_outlet', 'mk_user', 'mk_karyawan_area', 'mk_karyawan', 'mk_produk', 'mk_distributor', 'mk_area', 'mk_wilayah_depo', 'mk_depo'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
