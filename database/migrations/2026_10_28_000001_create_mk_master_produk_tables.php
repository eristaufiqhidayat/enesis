<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Master produk ber-ID asli Marketama (satuan, kategori, merk, produk kompetitor) + kolom lengkap mk_produk.
// Dikelola menu CRUD Master Data (MasterProdukController) dan dibaca aplikasi HP (/api/mobile/v1/master).
// Isi awal: php artisan db:seed --class=MasterProdukSeeder (data Marketama 28 Okt 2026).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mk_satuan_produk', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary(); // ID Marketama; data baru = ID terbesar + 1
            $table->string('nama', 50)->unique();
            $table->string('diubah_oleh')->nullable();
            $table->timestamp('diubah_pada')->nullable();
        });

        Schema::create('mk_kategori_produk', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('kode', 30)->unique();
            $table->string('nama', 100);
            $table->string('diubah_oleh')->nullable();
            $table->timestamp('diubah_pada')->nullable();
        });

        Schema::create('mk_merk_produk', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('kode', 30)->unique();
            $table->string('nama', 100);
            $table->boolean('aktif')->default(true);
            $table->string('diubah_oleh')->nullable();
            $table->timestamp('diubah_pada')->nullable();
        });

        Schema::create('mk_produk_kompetitor', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('nama', 150)->unique();
            $table->unsignedBigInteger('satuan_id')->nullable()->index();
            $table->string('foto')->nullable(); // path di disk "local"
            $table->string('diubah_oleh')->nullable();
            $table->timestamp('diubah_pada')->nullable();
        });

        Schema::table('mk_produk', function (Blueprint $table) {
            $table->string('packsize', 50)->nullable();
            // null = belum diisi; aplikasi HP lalu memakai nilai lama di kolom JSON "data".
            $table->boolean('produk_fokus')->nullable();
            $table->decimal('drop_size', 12, 2)->nullable();
            $table->unsignedInteger('maks_poin_per_invoice')->nullable();
            $table->decimal('harga_spreading', 18, 2)->nullable();
            $table->decimal('het', 18, 2)->nullable();
            $table->decimal('harga_pokok', 18, 2)->nullable();
            $table->text('keterangan')->nullable();
            $table->string('foto')->nullable();
            $table->string('foto_lama')->nullable(); // path foto di server Marketama
            $table->string('diubah_oleh')->nullable();
            $table->timestamp('diubah_pada')->nullable();
            $table->index('merk_id');
            $table->index('kategori_id');
            $table->index('satuan_id');
        });
    }

    public function down(): void
    {
        Schema::table('mk_produk', function (Blueprint $table) {
            $table->dropIndex(['merk_id']);
            $table->dropIndex(['kategori_id']);
            $table->dropIndex(['satuan_id']);
            $table->dropColumn([
                'packsize', 'produk_fokus', 'drop_size', 'maks_poin_per_invoice', 'harga_spreading', 'het', 'harga_pokok',
                'keterangan', 'foto', 'foto_lama', 'diubah_oleh', 'diubah_pada',
            ]);
        });

        Schema::dropIfExists('mk_produk_kompetitor');
        Schema::dropIfExists('mk_merk_produk');
        Schema::dropIfExists('mk_kategori_produk');
        Schema::dropIfExists('mk_satuan_produk');
    }
};
