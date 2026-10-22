<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Satu tabel untuk setiap menu Marketama yang punya data.
// Kolom mengikuti hasil penarikan data (lihat config/marketama.php untuk label kolom).
return new class extends Migration
{
    public function up(): void
    {
        // Setoran
        Schema::create('pembayaran_pembelian', function (Blueprint $table) {
            $table->id();
            $table->string('no_purchase', 255)->nullable();
            $table->string('tanggal_setoran', 255)->nullable();
            $table->string('koordinator', 255)->nullable();
            $table->string('distributor', 255)->nullable();
            $table->string('diterima_oleh', 255)->nullable();
            $table->string('nominal', 255)->nullable();
            $table->string('lampiran', 255)->nullable();
            $table->string('diupdate_oleh', 255)->nullable();
            $table->string('diupdate_pada', 255)->nullable();
        });

        // Setor Margin
        Schema::create('setor_margin', function (Blueprint $table) {
            $table->id();
            $table->string('koordinator', 255)->nullable();
            $table->string('tanggal_setoran', 255)->nullable();
            $table->string('diterima_oleh', 255)->nullable();
            $table->string('nominal', 255)->nullable();
            $table->string('lampiran', 255)->nullable();
            $table->string('diupdate_oleh', 255)->nullable();
            $table->string('diupdate_pada', 255)->nullable();
        });

        // Lap. Setoran
        Schema::create('lap_pembayaran_tagihan', function (Blueprint $table) {
            $table->id();
            $table->string('kode_loading', 255)->nullable();
            $table->string('tanggal_bayar', 255)->nullable();
            $table->string('depo', 255)->nullable();
            $table->string('distributor', 255)->nullable();
            $table->string('koordinator', 255)->nullable();
            $table->string('diterima_oleh', 255)->nullable();
            $table->string('nominal', 255)->nullable();
            $table->string('lampiran', 255)->nullable();
            $table->string('dibuat_oleh', 255)->nullable();
            $table->string('dibuat_pada', 255)->nullable();
        });

        // Lap. Setor Margin
        Schema::create('lap_setor_margin', function (Blueprint $table) {
            $table->id();
            $table->string('tanggal_bayar', 255)->nullable();
            $table->string('depo', 255)->nullable();
            $table->string('koordinator', 255)->nullable();
            $table->string('diterima_oleh', 255)->nullable();
            $table->string('nominal', 255)->nullable();
            $table->string('lampiran', 255)->nullable();
            $table->string('dibuat_oleh', 255)->nullable();
            $table->string('dibuat_pada', 255)->nullable();
        });

        // Potongan Gaji
        Schema::create('potongan_gaji', function (Blueprint $table) {
            $table->id();
            $table->string('nama_karyawan', 255)->nullable();
            $table->string('koordinator', 255)->nullable();
            $table->string('depo', 255)->nullable();
            $table->string('tanggal', 255)->nullable();
            $table->string('nominal', 255)->nullable();
            $table->string('keterangan', 255)->nullable();
            $table->string('diupdate_oleh', 255)->nullable();
            $table->string('diupdate_pada', 255)->nullable();
        });

        // Penggajian Sales
        Schema::create('penggajian_sales', function (Blueprint $table) {
            $table->id();
            $table->string('no_penggajian', 255)->nullable();
            $table->string('nama_penggajian', 255)->nullable();
            $table->string('koordinator', 255)->nullable();
            $table->string('depo', 255)->nullable();
            $table->string('periode', 255)->nullable();
            $table->string('jml_karyawan', 255)->nullable();
            $table->string('total_gaji', 255)->nullable();
            $table->string('diupdate_oleh', 255)->nullable();
            $table->string('diupdate_pada', 255)->nullable();
        });

        // Penggajian Koordinator
        Schema::create('penggajian_koordinator', function (Blueprint $table) {
            $table->id();
            $table->string('no_penggajian', 255)->nullable();
            $table->string('nama_penggajian', 255)->nullable();
            $table->string('koordinator', 255)->nullable();
            $table->string('depo', 255)->nullable();
            $table->string('periode', 255)->nullable();
            $table->string('hari_kerja', 255)->nullable();
            $table->string('total_gaji', 255)->nullable();
            $table->string('diupdate_oleh', 255)->nullable();
            $table->string('diupdate_pada', 255)->nullable();
        });

        // Lap. Insentif Sales
        Schema::create('lap_insentif_sales', function (Blueprint $table) {
            $table->id();
            $table->string('no_payroll', 255)->nullable();
            $table->string('periode', 255)->nullable();
            $table->string('nama_sales', 255)->nullable();
            $table->string('telepon', 255)->nullable();
            $table->string('koordinator', 255)->nullable();
            $table->string('depo', 255)->nullable();
            $table->string('insentif_ec', 255)->nullable();
            $table->string('insentif_absensi', 255)->nullable();
            $table->string('total_insentif', 255)->nullable();
        });

        // Lap. Insentif Koordinator
        Schema::create('lap_insentif_koordinator', function (Blueprint $table) {
            $table->id();
            $table->string('no_payroll', 255)->nullable();
            $table->string('periode', 255)->nullable();
            $table->string('nama_koordinator', 255)->nullable();
            $table->string('telepon', 255)->nullable();
            $table->string('depo', 255)->nullable();
            $table->string('insentif_ec', 255)->nullable();
            $table->string('insentif_absensi', 255)->nullable();
            $table->string('total_insentif', 255)->nullable();
        });

        // Lap. Penggajian Sales
        Schema::create('lap_penggajian_sales', function (Blueprint $table) {
            $table->id();
            $table->string('no_payroll', 255)->nullable();
            $table->string('periode', 255)->nullable();
            $table->string('nama_sales', 255)->nullable();
            $table->string('koordinator', 255)->nullable();
            $table->string('depo', 255)->nullable();
            $table->string('gaji_pokok', 255)->nullable();
            $table->string('hari_kerja', 255)->nullable();
            $table->string('hari_izin', 255)->nullable();
            $table->string('tunj_bbm_makan', 255)->nullable();
            $table->string('sewa_motor', 255)->nullable();
            $table->string('tunj_internet', 255)->nullable();
            $table->string('tunj_jabatan', 255)->nullable();
            $table->string('potongan', 255)->nullable();
            $table->string('insentif_ec', 255)->nullable();
            $table->string('insentif_absensi', 255)->nullable();
            $table->string('gaji_bersih', 255)->nullable();
        });

        // Lap. Penggajian Koordinator
        Schema::create('lap_penggajian_koordinator', function (Blueprint $table) {
            $table->id();
            $table->string('no_payroll', 255)->nullable();
            $table->string('periode', 255)->nullable();
            $table->string('nama_koordinator', 255)->nullable();
            $table->string('depo', 255)->nullable();
            $table->string('area', 255)->nullable();
            $table->string('gaji_pokok', 255)->nullable();
            $table->string('hari_kerja', 255)->nullable();
            $table->string('hari_izin', 255)->nullable();
            $table->string('tunj_bbm_makan', 255)->nullable();
            $table->string('sewa_motor', 255)->nullable();
            $table->string('tunj_internet', 255)->nullable();
            $table->string('tunj_jabatan', 255)->nullable();
            $table->string('potongan', 255)->nullable();
            $table->string('insentif_ec', 255)->nullable();
            $table->string('insentif_absensi', 255)->nullable();
            $table->string('gaji_bersih', 255)->nullable();
        });

        // Lap. Outlet RO
        Schema::create('lap_outlet_ro', function (Blueprint $table) {
            $table->id();
            $table->string('depo', 255)->nullable();
            $table->string('koordinator', 255)->nullable();
            $table->string('kode_outlet', 255)->nullable();
            $table->string('nama_outlet', 255)->nullable();
            $table->string('contact_person', 255)->nullable();
            $table->string('telepon', 255)->nullable();
            $table->string('alamat', 255)->nullable();
            $table->string('area', 255)->nullable();
            $table->string('provinsi', 255)->nullable();
            $table->string('kota_kab', 255)->nullable();
            $table->string('kecamatan', 255)->nullable();
            $table->string('kelurahan', 255)->nullable();
            $table->string('latitude', 255)->nullable();
            $table->string('longitude', 255)->nullable();
            $table->string('dibuat_oleh', 255)->nullable();
            $table->string('dibuat_pada', 255)->nullable();
        });

        // Kategori Surat Peringatan
        Schema::create('kategori_surat_peringatan', function (Blueprint $table) {
            $table->id();
            $table->string('nama_kategori', 255)->nullable();
            $table->string('total_sp', 255)->nullable();
            $table->string('diupdate_oleh', 255)->nullable();
            $table->string('diupdate_pada', 255)->nullable();
        });

        // Surat Peringatan
        Schema::create('surat_peringatan', function (Blueprint $table) {
            $table->id();
            $table->string('karyawan', 255)->nullable();
            $table->string('kategori_sp', 255)->nullable();
            $table->string('tanggal', 255)->nullable();
            $table->string('keterangan', 255)->nullable();
            $table->string('diupdate_oleh', 255)->nullable();
            $table->string('diupdate_pada', 255)->nullable();
        });

        // Produk Kompetitor
        Schema::create('produk_kompetitor', function (Blueprint $table) {
            $table->id();
            $table->string('nama_produk', 255)->nullable();
            $table->string('satuan', 255)->nullable();
            $table->string('diupdate_oleh', 255)->nullable();
            $table->string('diupdate_pada', 255)->nullable();
        });

        // Kategori Produk
        Schema::create('kategori_produk', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 255)->nullable();
            $table->string('nama', 255)->nullable();
            $table->bigInteger('total_produk')->nullable();
            $table->string('diupdate_oleh', 255)->nullable();
            $table->string('diupdate_pada', 255)->nullable();
        });

        // Karyawan Supervisor
        Schema::create('karyawan_supervisor', function (Blueprint $table) {
            $table->id();
            $table->string('nama_lengkap', 255)->nullable();
            $table->string('no_hp', 255)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('status', 255)->nullable();
            $table->string('diupdate_oleh', 255)->nullable();
            $table->string('diupdate_pada', 255)->nullable();
        });

        // Master Project
        Schema::create('master_project', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('no')->nullable();
            $table->string('kode', 255)->nullable();
            $table->string('nama_project', 255)->nullable();
            $table->string('deskripsi', 255)->nullable();
            $table->bigInteger('total_depo')->nullable();
            $table->string('diupdate_oleh', 255)->nullable();
            $table->string('diupdate_pada', 255)->nullable();
        });

        // Tipe Outlet
        Schema::create('tipe_outlet', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('source_id')->nullable();
            $table->string('kode', 255)->nullable();
            $table->string('tipe_outlet', 255)->nullable();
            $table->index('source_id');
        });

        // Super Admin
        Schema::create('super_admin', function (Blueprint $table) {
            $table->id();
            $table->string('username', 255)->nullable();
            $table->string('nickname', 255)->nullable();
            $table->string('karyawan', 255)->nullable();
            $table->string('status', 255)->nullable();
            $table->string('diupdate_oleh', 255)->nullable();
            $table->string('diupdate_pada', 255)->nullable();
        });

        // Satuan Produk
        Schema::create('satuan_produk', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 255)->nullable();
            $table->bigInteger('total_produk')->nullable();
            $table->string('diupdate_oleh', 255)->nullable();
            $table->string('diupdate_pada', 255)->nullable();
        });

        // Karyawan Lainnya
        Schema::create('karyawan_lainnya', function (Blueprint $table) {
            $table->id();
            $table->string('nama_lengkap', 255)->nullable();
            $table->string('kategori', 255)->nullable();
            $table->string('no_hp', 255)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('status', 255)->nullable();
            $table->string('diupdate_oleh', 255)->nullable();
            $table->string('diupdate_pada', 255)->nullable();
        });

        // User Lainnya
        Schema::create('user_lainnya', function (Blueprint $table) {
            $table->id();
            $table->string('username', 255)->nullable();
            $table->string('nickname', 255)->nullable();
            $table->string('kategori', 255)->nullable();
            $table->string('karyawan', 255)->nullable();
            $table->string('status', 255)->nullable();
            $table->string('diupdate_oleh', 255)->nullable();
            $table->string('diupdate_pada', 255)->nullable();
        });

        // Pengumuman
        Schema::create('pengumuman', function (Blueprint $table) {
            $table->id();
            $table->text('pesan')->nullable();
            $table->string('kategori_user', 255)->nullable();
            $table->bigInteger('total_user')->nullable();
            $table->string('tanggal_notifikasi', 255)->nullable();
            $table->string('diupdate_oleh', 255)->nullable();
            $table->string('diupdate_pada', 255)->nullable();
        });

        // Brand
        Schema::create('merk_produk', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 255)->nullable();
            $table->string('nama', 255)->nullable();
            $table->bigInteger('total_produk')->nullable();
            $table->string('status_aktif', 255)->nullable();
            $table->string('diupdate_oleh', 255)->nullable();
            $table->string('diupdate_pada', 255)->nullable();
        });

        // Parameter Pemotong Gaji
        Schema::create('parameter_pemotong_gaji', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 255)->nullable();
            $table->string('diupdate_oleh', 255)->nullable();
            $table->string('diupdate_pada', 255)->nullable();
        });

        // City Group
        Schema::create('city_group', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('source_id')->nullable();
            $table->string('nama_city_group', 255)->nullable();
            $table->index('source_id');
        });

        // Lap. Barang Terbaik Harian
        Schema::create('lap_penjualan_barang_terbaik_harian', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal_data')->nullable();
            $table->bigInteger('product_id')->nullable();
            $table->string('product_name', 255)->nullable();
            $table->bigInteger('product_sku')->nullable();
            $table->string('category_name', 255)->nullable();
            $table->string('brand_name', 255)->nullable();
            $table->string('unit_name', 255)->nullable();
            $table->decimal('total_qty', 20, 4)->nullable();
            $table->decimal('total_amount', 20, 4)->nullable();
            $table->decimal('total_discount', 20, 4)->nullable();
            $table->bigInteger('total_transaksi')->nullable();
        });

        // Lap. Barang Terbaik Mingguan
        Schema::create('lap_penjualan_barang_terbaik_mingguan', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('product_id')->nullable();
            $table->string('product_name', 255)->nullable();
            $table->bigInteger('product_sku')->nullable();
            $table->string('category_name', 255)->nullable();
            $table->string('brand_name', 255)->nullable();
            $table->string('unit_name', 255)->nullable();
            $table->bigInteger('total_qty')->nullable();
            $table->decimal('total_amount', 20, 4)->nullable();
            $table->decimal('total_discount', 20, 4)->nullable();
            $table->bigInteger('total_transaksi')->nullable();
        });

        // Tanggal Libur
        Schema::create('holiday_dates', function (Blueprint $table) {
            $table->id();
            $table->string('nama_libur', 255)->nullable();
            $table->string('tanggal', 255)->nullable();
            $table->string('keterangan', 255)->nullable();
            $table->string('diupdate_oleh', 255)->nullable();
            $table->string('diupdate_pada', 255)->nullable();
        });

        // Lap. Barang Terbaik Bulanan
        Schema::create('lap_penjualan_barang_terbaik_bulanan', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('product_id')->nullable();
            $table->string('product_name', 255)->nullable();
            $table->bigInteger('product_sku')->nullable();
            $table->string('category_name', 255)->nullable();
            $table->string('brand_name', 255)->nullable();
            $table->string('unit_name', 255)->nullable();
            $table->decimal('total_qty', 20, 4)->nullable();
            $table->decimal('total_amount', 20, 4)->nullable();
            $table->decimal('total_discount', 20, 4)->nullable();
            $table->bigInteger('total_transaksi')->nullable();
        });

        // Produk
        Schema::create('produk', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 255)->nullable();
            $table->string('nama_produk', 255)->nullable();
            $table->string('merk', 255)->nullable();
            $table->string('kategori', 255)->nullable();
            $table->string('satuan', 255)->nullable();
            $table->string('harga_beli', 255)->nullable();
            $table->string('harga_jual', 255)->nullable();
            $table->string('produk_fokus', 255)->nullable();
            $table->decimal('drop_size', 20, 4)->nullable();
            $table->bigInteger('maksimal_poin_per_invoice')->nullable();
            $table->string('diupdate_oleh', 255)->nullable();
            $table->string('diupdate_pada', 255)->nullable();
        });

        // Konversi Poin Per Depo
        Schema::create('sales_point_conversion_per_warehouse', function (Blueprint $table) {
            $table->id();
            $table->string('depo', 255)->nullable();
            $table->bigInteger('poin_insentif_rp')->nullable();
            $table->string('diupdate_oleh', 255)->nullable();
            $table->string('diupdate_pada', 255)->nullable();
        });

        // Master Warehouse / Depo
        Schema::create('master_depo', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 255)->nullable();
            $table->string('nama', 255)->nullable();
            $table->string('projek', 255)->nullable();
            $table->string('kota_besar', 255)->nullable();
            $table->string('minimum_sku', 255)->nullable();
            $table->string('multi_polygon_sales', 255)->nullable();
            $table->string('provinsi', 255)->nullable();
            $table->string('kota_kab', 255)->nullable();
            $table->string('kecamatan', 255)->nullable();
            $table->string('kelurahan_desa', 255)->nullable();
            $table->string('alamat', 255)->nullable();
            $table->decimal('longitude', 20, 4)->nullable();
            $table->decimal('latitude', 20, 4)->nullable();
            $table->bigInteger('total_koordinator')->nullable();
            $table->bigInteger('jumlah_sales_aktif')->nullable();
            $table->bigInteger('jumlah_wilayah_depo')->nullable();
            $table->bigInteger('kuota_koordinator')->nullable();
            $table->bigInteger('kuota_sales_motoris')->nullable();
            $table->bigInteger('target_call')->nullable();
            $table->bigInteger('target_ec')->nullable();
            $table->bigInteger('durasi_tracking_gps')->nullable();
            $table->bigInteger('insentif_per_poin')->nullable();
            $table->bigInteger('outlet_1_sku')->nullable();
            $table->bigInteger('minimal_value_transaksi')->nullable();
            $table->string('diupdate_oleh', 255)->nullable();
            $table->string('diupdate_pada', 255)->nullable();
            $table->string('status', 255)->nullable();
        });

        // Wilayah Depo
        Schema::create('wilayah_depo', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 255)->nullable();
            $table->string('depo_warehouse', 255)->nullable();
            $table->string('diupdate_oleh', 255)->nullable();
            $table->string('diupdate_pada', 255)->nullable();
        });

        // Lap. Izin/Sakit
        Schema::create('lap_izin_sakit', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('source_id')->nullable();
            $table->date('date_leave')->nullable();
            $table->bigInteger('status')->nullable();
            $table->string('reason_for_absence', 255)->nullable();
            $table->string('employee_name', 255)->nullable();
            $table->string('employee_phone', 255)->nullable();
            $table->string('category_name', 255)->nullable();
            $table->string('koordinator_name', 255)->nullable();
            $table->string('warehouse_name', 255)->nullable();
            $table->string('status_label', 255)->nullable();
            $table->string('status_badge', 255)->nullable();
            $table->index('source_id');
        });

        // Distributor
        Schema::create('distributor', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 255)->nullable();
            $table->string('nama_distributor', 255)->nullable();
            $table->string('depo', 255)->nullable();
            $table->string('no_telepon', 255)->nullable();
            $table->string('contact_person', 255)->nullable();
            $table->string('diupdate_oleh', 255)->nullable();
            $table->string('diupdate_pada', 255)->nullable();
        });

        // Provinsi
        Schema::create('provinsi', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('source_id')->nullable();
            $table->bigInteger('kode')->nullable();
            $table->string('nama_provinsi', 255)->nullable();
            $table->index('source_id');
        });

        // Sales Tidak Absen Pagi
        Schema::create('sales_tidak_absen', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal_data')->nullable();
            $table->bigInteger('no')->nullable();
            $table->string('depo', 255)->nullable();
            $table->string('koordinator', 255)->nullable();
            $table->string('nama_sales', 255)->nullable();
        });

        // Sales Tidak Penjualan
        Schema::create('sales_tidak_penjualan', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal_data')->nullable();
            $table->bigInteger('no')->nullable();
            $table->string('depo', 255)->nullable();
            $table->string('koordinator', 255)->nullable();
            $table->string('nama_sales', 255)->nullable();
            $table->string('jumlah_visit_outlet', 255)->nullable();
        });

        // Absensi SSK / Supervisor
        Schema::create('absensi_ssk_supervisor', function (Blueprint $table) {
            $table->id();
            $table->string('nama_karyawan', 255)->nullable();
            $table->string('depo', 255)->nullable();
            $table->string('tanggal', 255)->nullable();
            $table->string('jam_masuk', 255)->nullable();
            $table->string('jam_keluar', 255)->nullable();
            $table->string('diupdate_oleh', 255)->nullable();
            $table->string('diupdate_pada', 255)->nullable();
        });

        // User Koordinator
        Schema::create('user_koordinator', function (Blueprint $table) {
            $table->id();
            $table->string('username', 255)->nullable();
            $table->string('nickname', 255)->nullable();
            $table->string('karyawan', 255)->nullable();
            $table->string('depo', 255)->nullable();
            $table->string('status', 255)->nullable();
            $table->string('diupdate_oleh', 255)->nullable();
            $table->string('diupdate_pada', 255)->nullable();
        });

        // Karyawan Koordinator
        Schema::create('karyawan_koordinator', function (Blueprint $table) {
            $table->id();
            $table->string('nama_lengkap', 255)->nullable();
            $table->string('depo', 255)->nullable();
            $table->string('wilayah_depo', 255)->nullable();
            $table->bigInteger('jumlah_sales')->nullable();
            $table->string('no_hp', 255)->nullable();
            $table->string('status', 255)->nullable();
            $table->string('diupdate_oleh', 255)->nullable();
            $table->string('diupdate_pada', 255)->nullable();
        });

        // checkin_pagi
        Schema::create('checkin_pagi', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal_data')->nullable();
            $table->bigInteger('no')->nullable();
            $table->string('depo', 255)->nullable();
            $table->string('koordinator', 255)->nullable();
            $table->string('nama_sales', 255)->nullable();
            $table->string('checkin_pertama_hari_ini', 255)->nullable();
        });

        // Permintaan Izin
        Schema::create('permintaan_izin', function (Blueprint $table) {
            $table->id();
            $table->string('karyawan', 255)->nullable();
            $table->string('koordinator', 255)->nullable();
            $table->string('tanggal_izin', 255)->nullable();
            $table->string('status', 255)->nullable();
            $table->string('alasan', 255)->nullable();
            $table->string('depo', 255)->nullable();
            $table->string('diupdate_oleh', 255)->nullable();
            $table->string('diupdate_pada', 255)->nullable();
        });

        // Lap. Retur Barang
        Schema::create('lap_retur_barang', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('source_id')->nullable();
            $table->string('trans_code', 255)->nullable();
            $table->string('purchase_trans_code', 255)->nullable();
            $table->date('date_trans')->nullable();
            $table->dateTime('datetime_trans')->nullable();
            $table->string('distributor_name', 255)->nullable();
            $table->string('koordinator_name', 255)->nullable();
            $table->string('warehouse_name', 255)->nullable();
            $table->bigInteger('total_item')->nullable();
            $table->bigInteger('total_qty')->nullable();
            $table->decimal('grand_total', 20, 4)->nullable();
            $table->decimal('total_discount', 20, 4)->nullable();
            $table->string('updated_by_name', 255)->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->index('source_id');
        });

        // Lap. Loading Barang
        Schema::create('lap_loading_barang', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('source_id')->nullable();
            $table->string('trans_code', 255)->nullable();
            $table->date('date_trans')->nullable();
            $table->dateTime('datetime_trans')->nullable();
            $table->string('distributor_name', 255)->nullable();
            $table->string('koordinator_name', 255)->nullable();
            $table->string('warehouse_name', 255)->nullable();
            $table->bigInteger('total_item')->nullable();
            $table->bigInteger('total_qty')->nullable();
            $table->bigInteger('total_qty_sale')->nullable();
            $table->bigInteger('total_qty_return')->nullable();
            $table->decimal('grand_total', 20, 4)->nullable();
            $table->decimal('sale_total', 20, 4)->nullable();
            $table->decimal('return_total', 20, 4)->nullable();
            $table->decimal('margin_total', 20, 4)->nullable();
            $table->bigInteger('paid')->nullable();
            $table->string('updated_by_name', 255)->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->index('source_id');
        });

        // Monitor Absensi Motoris
        Schema::create('monitor_absensi_motoris', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('no')->nullable();
            $table->string('nama_sales_motoris', 255)->nullable();
            $table->string('nama_koordinator', 255)->nullable();
            $table->string('nama_depo', 255)->nullable();
            $table->bigInteger('hari_kerja')->nullable();
            $table->bigInteger('total_kehadiran')->nullable();
            $table->bigInteger('total_tidak_hadir')->nullable();
        });

        // Kota Kabupaten
        Schema::create('kota_kabupaten', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('source_id')->nullable();
            $table->string('provinsi', 255)->nullable();
            $table->bigInteger('kode')->nullable();
            $table->string('nama_kota_kabupaten', 255)->nullable();
            $table->index('source_id');
        });

        // Lap. Absensi Koordinator
        Schema::create('lap_absensi_koordinator', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('source_id')->nullable();
            $table->date('date')->nullable();
            $table->dateTime('time_in')->nullable();
            $table->dateTime('time_out')->nullable();
            $table->string('employee_name', 255)->nullable();
            $table->string('employee_phone', 255)->nullable();
            $table->string('category_name', 255)->nullable();
            $table->string('warehouse_name', 255)->nullable();
            $table->index('source_id');
        });

        // Absensi Sales Terlambat
        Schema::create('absensi_sales_terlambat', function (Blueprint $table) {
            $table->id();
            $table->string('nama_sales', 255)->nullable();
            $table->string('koordinator', 255)->nullable();
            $table->string('depo', 255)->nullable();
            $table->string('tanggal', 255)->nullable();
            $table->string('jam_masuk', 255)->nullable();
            $table->string('jam_wajib', 255)->nullable();
            $table->string('telat', 255)->nullable();
            $table->string('diupdate_oleh', 255)->nullable();
            $table->string('diupdate_pada', 255)->nullable();
        });

        // User Sales
        Schema::create('user_sales', function (Blueprint $table) {
            $table->id();
            $table->string('username', 255)->nullable();
            $table->string('nickname', 255)->nullable();
            $table->string('karyawan', 255)->nullable();
            $table->string('depo', 255)->nullable();
            $table->string('koordinator', 255)->nullable();
            $table->string('status', 255)->nullable();
            $table->string('diupdate_oleh', 255)->nullable();
            $table->string('diupdate_pada', 255)->nullable();
        });

        // Karyawan Sales
        Schema::create('karyawan_sales', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('source_id')->nullable();
            $table->string('nama_lengkap', 255)->nullable();
            $table->string('koordinator', 255)->nullable();
            $table->string('depo', 255)->nullable();
            $table->text('area')->nullable();
            $table->bigInteger('total_area')->nullable();
            $table->string('no_hp', 255)->nullable();
            $table->string('status', 255)->nullable();
            $table->string('diupdate_oleh', 255)->nullable();
            $table->string('diupdate_pada', 255)->nullable();
            $table->index('source_id');
        });

        // Lap. User
        Schema::create('lap_user', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('source_id')->nullable();
            $table->string('nickname', 255)->nullable();
            $table->string('username', 255)->nullable();
            $table->bigInteger('status')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->string('employee_name', 255)->nullable();
            $table->bigInteger('employee_active')->nullable();
            $table->string('employee_phone', 255)->nullable();
            $table->string('user_category_name', 255)->nullable();
            $table->string('warehouse_name', 255)->nullable();
            $table->string('created_by_name', 255)->nullable();
            $table->string('updated_by_name', 255)->nullable();
            $table->index('source_id');
        });

        // Lap. Karyawan
        Schema::create('lap_karyawan', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('source_id')->nullable();
            $table->string('fullname', 255)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('phone', 255)->nullable();
            $table->bigInteger('active')->nullable();
            $table->date('employment_start_date')->nullable();
            $table->decimal('basic_salary', 20, 4)->nullable();
            $table->decimal('fuel_and_meal_allowance', 20, 4)->nullable();
            $table->decimal('motor_rent', 20, 4)->nullable();
            $table->decimal('internet_data_allowance', 20, 4)->nullable();
            $table->decimal('position_allowance', 20, 4)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->string('employee_category_name', 255)->nullable();
            $table->string('warehouse_name', 255)->nullable();
            $table->string('area_name', 255)->nullable();
            $table->string('koordinator_name', 255)->nullable();
            $table->string('created_by_name', 255)->nullable();
            $table->string('updated_by_name', 255)->nullable();
            $table->index('source_id');
        });

        // Lap. Outlet NOO
        Schema::create('lap_outlet_noo', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('source_id')->nullable();
            $table->string('outlet_code', 255)->nullable();
            $table->string('outlet_name', 255)->nullable();
            $table->string('contact_person1', 255)->nullable();
            $table->string('contact_person2', 255)->nullable();
            $table->string('phone1', 255)->nullable();
            $table->string('phone2', 255)->nullable();
            $table->string('address', 255)->nullable();
            $table->decimal('location_lat', 20, 4)->nullable();
            $table->decimal('location_long', 20, 4)->nullable();
            $table->string('area_name', 255)->nullable();
            $table->string('province_name', 255)->nullable();
            $table->string('city_name', 255)->nullable();
            $table->string('district_name', 255)->nullable();
            $table->string('village_name', 255)->nullable();
            $table->string('category_name', 255)->nullable();
            $table->string('created_by_name', 255)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->string('warehouse_name', 255)->nullable();
            $table->string('koordinator_name', 255)->nullable();
            $table->index('source_id');
        });

        // Outlet Duplikat / Fiktif
        Schema::create('outlet_duplikat', function (Blueprint $table) {
            $table->id();
            $table->string('kode_outlet_fiktif', 255)->nullable();
            $table->string('nama_outlet_fiktif', 255)->nullable();
            $table->string('alamat', 255)->nullable();
            $table->string('area', 255)->nullable();
            $table->string('kode_outlet_original', 255)->nullable();
            $table->string('nama_outlet_original', 255)->nullable();
            $table->string('diupdate_pada', 255)->nullable();
        });

        // Lap. Outlet Fiktif
        Schema::create('lap_outlet_fiktif', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('source_id')->nullable();
            $table->string('outlet_code', 255)->nullable();
            $table->string('outlet_name', 255)->nullable();
            $table->string('address', 255)->nullable();
            $table->decimal('location_lat', 20, 4)->nullable();
            $table->decimal('location_long', 20, 4)->nullable();
            $table->string('area_name', 255)->nullable();
            $table->string('province_name', 255)->nullable();
            $table->string('city_name', 255)->nullable();
            $table->string('district_name', 255)->nullable();
            $table->string('village_name', 255)->nullable();
            $table->string('original_outlet_code', 255)->nullable();
            $table->string('original_outlet_name', 255)->nullable();
            $table->string('fiktif_created_by_name', 255)->nullable();
            $table->dateTime('fiktif_created_at')->nullable();
            $table->string('original_created_by_name', 255)->nullable();
            $table->dateTime('original_created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->index('source_id');
        });

        // Lap. Stok Kompetitor
        Schema::create('lap_stok_kompetitor', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('source_id')->nullable();
            $table->bigInteger('quantity')->nullable();
            $table->dateTime('last_update')->nullable();
            $table->string('salesperson_name', 255)->nullable();
            $table->string('koordinator_name', 255)->nullable();
            $table->date('date_checkin')->nullable();
            $table->dateTime('datetime_checkin')->nullable();
            $table->dateTime('datetime_checkout')->nullable();
            $table->bigInteger('duration')->nullable();
            $table->string('outlet_name', 255)->nullable();
            $table->string('outlet_code', 255)->nullable();
            $table->string('outlet_address', 255)->nullable();
            $table->string('area_name', 255)->nullable();
            $table->string('product_name', 255)->nullable();
            $table->index('source_id');
        });

        // Retur Barang
        Schema::create('retur_barang', function (Blueprint $table) {
            $table->id();
            $table->string('kode_retur', 255)->nullable();
            $table->string('tanggal', 255)->nullable();
            $table->string('no_loading', 255)->nullable();
            $table->string('distributor', 255)->nullable();
            $table->string('gudang', 255)->nullable();
            $table->bigInteger('total_item')->nullable();
            $table->bigInteger('total_qty')->nullable();
            $table->string('diupdate_oleh', 255)->nullable();
            $table->string('diupdate_pada', 255)->nullable();
        });

        // Loading Barang
        Schema::create('loading_barang', function (Blueprint $table) {
            $table->id();
            $table->string('kode_transaksi', 255)->nullable();
            $table->string('tanggal', 255)->nullable();
            $table->string('distributor', 255)->nullable();
            $table->string('gudang', 255)->nullable();
            $table->bigInteger('total_item')->nullable();
            $table->string('total_qty', 255)->nullable();
            $table->string('status_retur', 255)->nullable();
            $table->string('grand_total', 255)->nullable();
            $table->string('diupdate_oleh', 255)->nullable();
            $table->string('diupdate_pada', 255)->nullable();
        });

        // Absensi Koordinator
        Schema::create('absensi_koordinator', function (Blueprint $table) {
            $table->id();
            $table->string('nama_karyawan', 255)->nullable();
            $table->string('depo', 255)->nullable();
            $table->string('tanggal', 255)->nullable();
            $table->string('jam_masuk', 255)->nullable();
            $table->string('jam_keluar', 255)->nullable();
            $table->string('diupdate_oleh', 255)->nullable();
            $table->string('diupdate_pada', 255)->nullable();
        });

        // Lap. Retur Barang Detail
        Schema::create('lap_retur_barang_detail', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('source_id')->nullable();
            $table->string('trans_code', 255)->nullable();
            $table->string('purchase_trans_code', 255)->nullable();
            $table->string('distributor_name', 255)->nullable();
            $table->string('koordinator_name', 255)->nullable();
            $table->string('warehouse_name', 255)->nullable();
            $table->date('date_trans')->nullable();
            $table->dateTime('datetime_trans')->nullable();
            $table->string('product_name', 255)->nullable();
            $table->bigInteger('product_sku')->nullable();
            $table->string('category_name', 255)->nullable();
            $table->string('brand_name', 255)->nullable();
            $table->string('unit_name', 255)->nullable();
            $table->string('product_unit', 255)->nullable();
            $table->bigInteger('quantity')->nullable();
            $table->decimal('cost', 20, 4)->nullable();
            $table->decimal('discount', 20, 4)->nullable();
            $table->decimal('total', 20, 4)->nullable();
            $table->decimal('total_discount', 20, 4)->nullable();
            $table->index('source_id');
        });

        // Lap. Loading Barang Detail
        Schema::create('lap_loading_barang_detail', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('source_id')->nullable();
            $table->string('trans_code', 255)->nullable();
            $table->string('distributor_name', 255)->nullable();
            $table->string('koordinator_name', 255)->nullable();
            $table->string('warehouse_name', 255)->nullable();
            $table->date('date_trans')->nullable();
            $table->dateTime('datetime_trans')->nullable();
            $table->string('product_name', 255)->nullable();
            $table->bigInteger('product_sku')->nullable();
            $table->string('category_name', 255)->nullable();
            $table->string('brand_name', 255)->nullable();
            $table->string('unit_name', 255)->nullable();
            $table->string('product_unit', 255)->nullable();
            $table->bigInteger('quantity')->nullable();
            $table->decimal('cost', 20, 4)->nullable();
            $table->decimal('discount', 20, 4)->nullable();
            $table->decimal('total', 20, 4)->nullable();
            $table->decimal('total_discount', 20, 4)->nullable();
            $table->bigInteger('sale_quantity')->nullable();
            $table->bigInteger('return_quantity')->nullable();
            $table->decimal('sale_total', 20, 4)->nullable();
            $table->decimal('return_total', 20, 4)->nullable();
            $table->index('source_id');
        });

        // Lap. Absensi Sales
        Schema::create('lap_absensi_sales', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('source_id')->nullable();
            $table->date('date')->nullable();
            $table->dateTime('time_in')->nullable();
            $table->dateTime('time_out')->nullable();
            $table->string('employee_name', 255)->nullable();
            $table->string('employee_phone', 255)->nullable();
            $table->string('koordinator_name', 255)->nullable();
            $table->string('warehouse_name', 255)->nullable();
            $table->index('source_id');
        });

        // Lap. Checkin Checkout
        Schema::create('lap_checkin_checkout', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('source_id')->nullable();
            $table->string('attendance_no', 255)->nullable();
            $table->date('attendance_date')->nullable();
            $table->dateTime('checkin_time')->nullable();
            $table->dateTime('checkout_time')->nullable();
            $table->string('duration', 255)->nullable();
            $table->bigInteger('attendance_status')->nullable();
            $table->decimal('location_long', 20, 4)->nullable();
            $table->decimal('location_lat', 20, 4)->nullable();
            $table->string('warehouse_name', 255)->nullable();
            $table->string('employee_name', 255)->nullable();
            $table->string('updated_by_name', 255)->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->index('source_id');
        });

        // Kecamatan
        Schema::create('kecamatan', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('source_id')->nullable();
            $table->string('provinsi', 255)->nullable();
            $table->string('kota_kabupaten', 255)->nullable();
            $table->string('kode', 255)->nullable();
            $table->string('nama_kecamatan', 255)->nullable();
            $table->index('source_id');
        });

        // Lap. Join Visit
        Schema::create('lap_join_visit', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('source_id')->nullable();
            $table->string('koordinator_name', 255)->nullable();
            $table->string('sales_name', 255)->nullable();
            $table->dateTime('inspection_date')->nullable();
            $table->dateTime('start_time')->nullable();
            $table->dateTime('end_time')->nullable();
            $table->string('description', 255)->nullable();
            $table->string('outlet_name', 255)->nullable();
            $table->string('outlet_code', 255)->nullable();
            $table->string('outlet_address', 255)->nullable();
            $table->string('area_name', 255)->nullable();
            $table->string('warehouse_name', 255)->nullable();
            $table->string('photo', 255)->nullable();
            $table->string('created_by_name', 255)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->string('updated_by_name', 255)->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->index('source_id');
        });

        // Sesi Login
        Schema::create('login_session', function (Blueprint $table) {
            $table->id();
            $table->string('username_nickname', 255)->nullable();
            $table->string('kategori', 255)->nullable();
            $table->string('depo', 255)->nullable();
            $table->string('nama_karyawan', 255)->nullable();
            $table->bigInteger('id_karyawan')->nullable();
            $table->string('nama_koordinator', 255)->nullable();
            $table->string('device_token', 255)->nullable();
            $table->string('login_pada', 255)->nullable();
            $table->string('terakhir_aktif', 255)->nullable();
        });

        // Master Area
        Schema::create('master_area', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 255)->nullable();
            $table->string('nama', 255)->nullable();
            $table->bigInteger('total_toko')->nullable();
            $table->string('nama_sales', 255)->nullable();
            $table->string('depo', 255)->nullable();
            $table->string('wilayah_depo', 255)->nullable();
            $table->string('provinsi', 255)->nullable();
            $table->string('kota_kabupaten', 255)->nullable();
            $table->string('kecamatan', 255)->nullable();
            $table->string('kelurahan', 255)->nullable();
            $table->string('jarak_terdekat_ke_depo', 255)->nullable();
            $table->string('jarak_terjauh_ke_depo', 255)->nullable();
            $table->string('diupdate_oleh', 255)->nullable();
            $table->string('diupdate_pada', 255)->nullable();
        });

        // Lap. Barang Habis Terjual
        Schema::create('lap_barang_habis_terjual', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('source_id')->nullable();
            $table->string('canvassing_no', 255)->nullable();
            $table->string('warehouse_name', 255)->nullable();
            $table->string('area_name', 255)->nullable();
            $table->string('salesperson_name', 255)->nullable();
            $table->string('koordinator_name', 255)->nullable();
            $table->date('receive_date')->nullable();
            $table->bigInteger('product_sku')->nullable();
            $table->string('product_name', 255)->nullable();
            $table->string('category_name', 255)->nullable();
            $table->string('unit_name', 255)->nullable();
            $table->string('product_unit', 255)->nullable();
            $table->bigInteger('total_qty')->nullable();
            $table->decimal('sale_qty', 20, 4)->nullable();
            $table->bigInteger('return_qty')->nullable();
            $table->decimal('sisa_qty', 20, 4)->nullable();
            $table->string('status_terjual', 255)->nullable();
            $table->decimal('pct_terjual', 20, 4)->nullable();
            $table->index('source_id');
        });

        // Lap. Pengembalian Barang
        Schema::create('lap_pengembalian_barang', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('source_id')->nullable();
            $table->string('canvas_return_no', 255)->nullable();
            $table->string('canvassing_no', 255)->nullable();
            $table->date('return_date')->nullable();
            $table->dateTime('return_datetime')->nullable();
            $table->string('warehouse_name', 255)->nullable();
            $table->string('area_name', 255)->nullable();
            $table->string('salesperson_name', 255)->nullable();
            $table->string('product_sku', 255)->nullable();
            $table->string('product_name', 255)->nullable();
            $table->string('category_name', 255)->nullable();
            $table->string('brand_name', 255)->nullable();
            $table->string('unit_name', 255)->nullable();
            $table->string('product_unit', 255)->nullable();
            $table->bigInteger('total_qty')->nullable();
            $table->decimal('sale_qty', 20, 4)->nullable();
            $table->decimal('return_qty', 20, 4)->nullable();
            $table->index('source_id');
        });

        // Lap. Barang Canvassing
        Schema::create('lap_barang_canvassing', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('source_id')->nullable();
            $table->string('canvassing_no', 255)->nullable();
            $table->string('warehouse_name', 255)->nullable();
            $table->string('area_name', 255)->nullable();
            $table->string('salesperson_name', 255)->nullable();
            $table->string('koordinator_name', 255)->nullable();
            $table->date('receive_date')->nullable();
            $table->dateTime('receive_datetime')->nullable();
            $table->string('product_name', 255)->nullable();
            $table->string('product_sku', 255)->nullable();
            $table->string('category_name', 255)->nullable();
            $table->string('brand_name', 255)->nullable();
            $table->string('unit_name', 255)->nullable();
            $table->string('product_unit', 255)->nullable();
            $table->bigInteger('total_qty')->nullable();
            $table->decimal('sale_qty', 20, 4)->nullable();
            $table->decimal('return_qty', 20, 4)->nullable();
            $table->bigInteger('returned')->nullable();
            $table->index('source_id');
        });

        // Kelurahan
        Schema::create('kelurahan', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('source_id')->nullable();
            $table->string('provinsi', 255)->nullable();
            $table->string('kota_kabupaten', 255)->nullable();
            $table->string('kecamatan', 255)->nullable();
            $table->string('kode', 255)->nullable();
            $table->string('nama_kelurahan', 255)->nullable();
            $table->index('source_id');
        });

        // Lap. Outlet OSS
        Schema::create('lap_outlet_oss', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('source_id')->nullable();
            $table->string('outlet_code', 255)->nullable();
            $table->string('outlet_name', 255)->nullable();
            $table->string('contact_person1', 255)->nullable();
            $table->string('contact_person2', 255)->nullable();
            $table->string('phone1', 255)->nullable();
            $table->string('phone2', 255)->nullable();
            $table->string('address', 255)->nullable();
            $table->decimal('location_lat', 20, 4)->nullable();
            $table->decimal('location_long', 20, 4)->nullable();
            $table->string('area_name', 255)->nullable();
            $table->string('province_name', 255)->nullable();
            $table->string('city_name', 255)->nullable();
            $table->string('district_name', 255)->nullable();
            $table->string('village_name', 255)->nullable();
            $table->string('category_name', 255)->nullable();
            $table->string('created_by_name', 255)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->string('warehouse_name', 255)->nullable();
            $table->string('koordinator_name', 255)->nullable();
            $table->index('source_id');
        });

        // Lap. Efective Call
        Schema::create('lap_effective_call', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('source_id')->nullable();
            $table->string('trans_code', 255)->nullable();
            $table->string('salesperson_name', 255)->nullable();
            $table->string('koordinator_name', 255)->nullable();
            $table->date('date_trans')->nullable();
            $table->dateTime('datetime_trans')->nullable();
            $table->bigInteger('total_item')->nullable();
            $table->decimal('total_qty', 20, 4)->nullable();
            $table->decimal('grand_total', 20, 4)->nullable();
            $table->string('outlet_name', 255)->nullable();
            $table->string('outlet_code', 255)->nullable();
            $table->decimal('outlet_longitude', 20, 4)->nullable();
            $table->decimal('outlet_latitude', 20, 4)->nullable();
            $table->string('outlet_address', 255)->nullable();
            $table->string('area_name', 255)->nullable();
            $table->string('warehouse_name', 255)->nullable();
            $table->dateTime('datetime_checkin')->nullable();
            $table->dateTime('datetime_checkout')->nullable();
            $table->bigInteger('checkin_duration')->nullable();
            $table->decimal('sales_longitude', 20, 4)->nullable();
            $table->decimal('sales_latitude', 20, 4)->nullable();
            $table->string('created_by_name', 255)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->index('source_id');
        });

        // Lap. Penjualan Barang
        Schema::create('lap_penjualan_barang', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('source_id')->nullable();
            $table->string('trans_code', 255)->nullable();
            $table->string('salesperson_name', 255)->nullable();
            $table->string('koordinator_name', 255)->nullable();
            $table->date('date_trans')->nullable();
            $table->dateTime('datetime_trans')->nullable();
            $table->bigInteger('effective_call_status')->nullable();
            $table->bigInteger('total_item')->nullable();
            $table->decimal('total_qty', 20, 4)->nullable();
            $table->decimal('grand_total', 20, 4)->nullable();
            $table->string('outlet_name', 255)->nullable();
            $table->string('outlet_code', 255)->nullable();
            $table->decimal('outlet_longitude', 20, 4)->nullable();
            $table->decimal('outlet_latitude', 20, 4)->nullable();
            $table->string('outlet_address', 255)->nullable();
            $table->string('area_name', 255)->nullable();
            $table->string('warehouse_name', 255)->nullable();
            $table->dateTime('datetime_checkin')->nullable();
            $table->dateTime('datetime_checkout')->nullable();
            $table->bigInteger('checkin_duration')->nullable();
            $table->decimal('sales_longitude', 20, 4)->nullable();
            $table->decimal('sales_latitude', 20, 4)->nullable();
            $table->string('created_by_name', 255)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->index('source_id');
        });

        // Lap. Outlet AC
        Schema::create('lap_outlet_ac', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('source_id')->nullable();
            $table->string('outlet_code', 255)->nullable();
            $table->string('outlet_name', 255)->nullable();
            $table->string('contact_person1', 255)->nullable();
            $table->string('contact_person2', 255)->nullable();
            $table->string('phone1', 255)->nullable();
            $table->string('phone2', 255)->nullable();
            $table->text('address')->nullable();
            $table->decimal('location_lat', 20, 4)->nullable();
            $table->decimal('location_long', 20, 4)->nullable();
            $table->string('area_name', 255)->nullable();
            $table->string('province_name', 255)->nullable();
            $table->string('city_name', 255)->nullable();
            $table->string('district_name', 255)->nullable();
            $table->string('village_name', 255)->nullable();
            $table->string('category_name', 255)->nullable();
            $table->string('created_by_name', 255)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->string('warehouse_name', 255)->nullable();
            $table->string('koordinator_name', 255)->nullable();
            $table->index('source_id');
        });

        // Lap. Checkin Outlet
        Schema::create('lap_checkin_outlet', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('source_id')->nullable();
            $table->string('salesperson_name', 255)->nullable();
            $table->string('koordinator_name', 255)->nullable();
            $table->date('date_checkin')->nullable();
            $table->dateTime('datetime_checkin')->nullable();
            $table->dateTime('datetime_checkout')->nullable();
            $table->bigInteger('duration')->nullable();
            $table->string('description', 255)->nullable();
            $table->bigInteger('category')->nullable();
            $table->string('photo', 255)->nullable();
            $table->decimal('sales_longitude', 20, 4)->nullable();
            $table->decimal('sales_latitude', 20, 4)->nullable();
            $table->string('outlet_name', 255)->nullable();
            $table->string('outlet_code', 255)->nullable();
            $table->decimal('outlet_longitude', 20, 4)->nullable();
            $table->decimal('outlet_latitude', 20, 4)->nullable();
            $table->string('outlet_address', 255)->nullable();
            $table->string('area_name', 255)->nullable();
            $table->string('warehouse_name', 255)->nullable();
            $table->index('source_id');
        });

        // Lap. Outlet EC
        Schema::create('lap_outlet_ec', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('source_id')->nullable();
            $table->string('outlet_code', 255)->nullable();
            $table->string('outlet_name', 255)->nullable();
            $table->string('contact_person1', 255)->nullable();
            $table->string('contact_person2', 255)->nullable();
            $table->string('phone1', 255)->nullable();
            $table->string('phone2', 255)->nullable();
            $table->text('address')->nullable();
            $table->decimal('location_lat', 20, 4)->nullable();
            $table->decimal('location_long', 20, 4)->nullable();
            $table->string('area_name', 255)->nullable();
            $table->string('province_name', 255)->nullable();
            $table->string('city_name', 255)->nullable();
            $table->string('district_name', 255)->nullable();
            $table->string('village_name', 255)->nullable();
            $table->string('category_name', 255)->nullable();
            $table->string('created_by_name', 255)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->string('warehouse_name', 255)->nullable();
            $table->string('koordinator_name', 255)->nullable();
            $table->index('source_id');
        });

        // Lap. Efective Call Detail
        Schema::create('lap_effective_call_barang', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('source_id')->nullable();
            $table->string('trans_code', 255)->nullable();
            $table->string('salesperson_name', 255)->nullable();
            $table->string('koordinator_name', 255)->nullable();
            $table->date('date_trans')->nullable();
            $table->dateTime('datetime_trans')->nullable();
            $table->string('product_name', 255)->nullable();
            $table->bigInteger('product_sku')->nullable();
            $table->string('category_name', 255)->nullable();
            $table->string('brand_name', 255)->nullable();
            $table->string('unit_name', 255)->nullable();
            $table->decimal('quantity', 20, 4)->nullable();
            $table->decimal('price', 20, 4)->nullable();
            $table->decimal('discount', 20, 4)->nullable();
            $table->decimal('total', 20, 4)->nullable();
            $table->decimal('total_discount', 20, 4)->nullable();
            $table->string('outlet_name', 255)->nullable();
            $table->string('outlet_code', 255)->nullable();
            $table->decimal('outlet_longitude', 20, 4)->nullable();
            $table->decimal('outlet_latitude', 20, 4)->nullable();
            $table->string('area_name', 255)->nullable();
            $table->string('warehouse_name', 255)->nullable();
            $table->dateTime('datetime_checkin')->nullable();
            $table->dateTime('datetime_checkout')->nullable();
            $table->bigInteger('checkin_duration')->nullable();
            $table->decimal('sales_longitude', 20, 4)->nullable();
            $table->decimal('sales_latitude', 20, 4)->nullable();
            $table->index('source_id');
        });

        // Lap. Penjualan Barang Detail
        Schema::create('lap_penjualan_barang_detail', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('source_id')->nullable();
            $table->string('trans_code', 255)->nullable();
            $table->bigInteger('effective_call_status')->nullable();
            $table->string('salesperson_name', 255)->nullable();
            $table->string('koordinator_name', 255)->nullable();
            $table->date('date_trans')->nullable();
            $table->dateTime('datetime_trans')->nullable();
            $table->string('product_name', 255)->nullable();
            $table->bigInteger('product_sku')->nullable();
            $table->string('category_name', 255)->nullable();
            $table->string('brand_name', 255)->nullable();
            $table->string('unit_name', 255)->nullable();
            $table->decimal('quantity', 20, 4)->nullable();
            $table->decimal('price', 20, 4)->nullable();
            $table->decimal('discount', 20, 4)->nullable();
            $table->decimal('total', 20, 4)->nullable();
            $table->decimal('total_discount', 20, 4)->nullable();
            $table->string('outlet_name', 255)->nullable();
            $table->string('outlet_code', 255)->nullable();
            $table->decimal('outlet_longitude', 20, 4)->nullable();
            $table->decimal('outlet_latitude', 20, 4)->nullable();
            $table->string('area_name', 255)->nullable();
            $table->string('warehouse_name', 255)->nullable();
            $table->dateTime('datetime_checkin')->nullable();
            $table->dateTime('datetime_checkout')->nullable();
            $table->bigInteger('checkin_duration')->nullable();
            $table->decimal('sales_longitude', 20, 4)->nullable();
            $table->decimal('sales_latitude', 20, 4)->nullable();
            $table->index('source_id');
        });

        // Lap. Outlet All
        Schema::create('lap_outlet_all', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('source_id')->nullable();
            $table->string('outlet_code', 255)->nullable();
            $table->string('outlet_name', 255)->nullable();
            $table->string('contact_person1', 255)->nullable();
            $table->string('contact_person2', 255)->nullable();
            $table->string('phone1', 255)->nullable();
            $table->string('phone2', 255)->nullable();
            $table->text('address')->nullable();
            $table->decimal('location_lat', 20, 4)->nullable();
            $table->decimal('location_long', 20, 4)->nullable();
            $table->string('area_name', 255)->nullable();
            $table->string('province_name', 255)->nullable();
            $table->string('city_name', 255)->nullable();
            $table->string('district_name', 255)->nullable();
            $table->string('village_name', 255)->nullable();
            $table->string('category_name', 255)->nullable();
            $table->string('created_by_name', 255)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->string('warehouse_name', 255)->nullable();
            $table->string('koordinator_name', 255)->nullable();
            $table->index('source_id');
        });

        // Lap. Stok Outlet
        Schema::create('lap_stok_outlet', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('source_id')->nullable();
            $table->bigInteger('quantity')->nullable();
            $table->string('unit_name', 255)->nullable();
            $table->dateTime('last_update')->nullable();
            $table->string('salesperson_name', 255)->nullable();
            $table->string('koordinator_name', 255)->nullable();
            $table->date('date_checkin')->nullable();
            $table->dateTime('datetime_checkin')->nullable();
            $table->dateTime('datetime_checkout')->nullable();
            $table->bigInteger('duration')->nullable();
            $table->bigInteger('category')->nullable();
            $table->string('outlet_name', 255)->nullable();
            $table->string('outlet_code', 255)->nullable();
            $table->string('outlet_address', 255)->nullable();
            $table->string('area_name', 255)->nullable();
            $table->string('warehouse_name', 255)->nullable();
            $table->string('product_code', 255)->nullable();
            $table->string('product_name', 255)->nullable();
            $table->string('category_name', 255)->nullable();
            $table->string('brand_name', 255)->nullable();
            $table->index('source_id');
        });

        // Rekap Pencapaian SKU per Motoris - tableAchieved
        Schema::create('rekap_pencapaian_sku_motoris_tableachieved', function (Blueprint $table) {
            $table->id();
            $table->string('no', 255)->nullable();
            $table->string('depo', 255)->nullable();
            $table->string('motoris', 255)->nullable();
            $table->decimal('total_qty', 20, 4)->nullable();
            $table->bigInteger('hari_kerja')->nullable();
            $table->decimal('avg_day', 20, 4)->nullable();
            $table->string('kategori', 255)->nullable();
        });

        // Rekap Pencapaian SKU per Motoris - tableNotAchieved
        Schema::create('rekap_pencapaian_sku_motoris_tablenotachieved', function (Blueprint $table) {
            $table->id();
            $table->string('no', 255)->nullable();
            $table->string('depo', 255)->nullable();
            $table->string('motoris', 255)->nullable();
            $table->decimal('total_qty', 20, 4)->nullable();
            $table->bigInteger('hari_kerja')->nullable();
            $table->bigInteger('avg_day')->nullable();
            $table->string('kategori', 255)->nullable();
        });

        // Rekap Pencapaian SKU per Depo - tableAchieved
        Schema::create('rekap_pencapaian_depo_sku_tableachieved', function (Blueprint $table) {
            $table->id();
            $table->string('no', 255)->nullable();
            $table->string('depo', 255)->nullable();
            $table->string('total_qty', 255)->nullable();
            $table->decimal('hari_kerja', 20, 4)->nullable();
            $table->decimal('avg_day_motoris', 20, 4)->nullable();
            $table->string('kategori', 255)->nullable();
        });

        // Rekap Pencapaian SKU per Depo - tableNotAchieved
        Schema::create('rekap_pencapaian_depo_sku_tablenotachieved', function (Blueprint $table) {
            $table->id();
            $table->string('no', 255)->nullable();
            $table->string('depo', 255)->nullable();
            $table->bigInteger('total_qty')->nullable();
            $table->bigInteger('hari_kerja')->nullable();
            $table->bigInteger('avg_day_motoris')->nullable();
            $table->string('kategori', 255)->nullable();
        });

        // Motoris Coverage
        Schema::create('motoris_coverage', function (Blueprint $table) {
            $table->id();
            $table->string('11_city', 255)->nullable();
            $table->bigInteger('quota_motoris')->nullable();
            $table->bigInteger('alreadytransaction')->nullable();
            $table->bigInteger('active_end_periode')->nullable();
            $table->bigInteger('vacant')->nullable();
            $table->string('coverage', 255)->nullable();
        });

        // Motoris Performance
        Schema::create('motoris_performance', function (Blueprint $table) {
            $table->id();
            $table->string('11_city', 255)->nullable();
            $table->string('motoris_kuota', 255)->nullable();
            $table->string('motoris_already_transaction', 255)->nullable();
            $table->string('motoris_actual_end_periode', 255)->nullable();
            $table->string('hk_standar', 255)->nullable();
            $table->string('act_hk', 255)->nullable();
            $table->string('target', 255)->nullable();
            $table->string('call', 255)->nullable();
            $table->string('ec', 255)->nullable();
            $table->string('ipt', 255)->nullable();
            $table->string('value', 255)->nullable();
        });

        // Template Barang Canvassing
        Schema::create('template_barang_canvassing', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('source_id')->nullable();
            $table->string('nama_template', 255)->nullable();
            $table->string('deskripsi', 255)->nullable();
            $table->bigInteger('jumlah_item')->nullable();
            $table->string('diubah_oleh', 255)->nullable();
            $table->index('source_id');
        });

        // Panduan
        Schema::create('panduan', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('no')->nullable();
            $table->string('informasi', 255)->nullable();
            $table->string('diupdate_pada', 255)->nullable();
            $table->string('diupdate_oleh', 255)->nullable();
            $table->string('kolom', 255)->nullable();
        });

        // Hari Kerja Aktif
        Schema::create('waktu_aktif_app', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('no')->nullable();
            $table->string('nama_hari', 255)->nullable();
            $table->string('waktu_mulai', 255)->nullable();
            $table->string('waktu_berakhir', 255)->nullable();
            $table->string('sales', 255)->nullable();
            $table->string('koordinator', 255)->nullable();
            $table->string('update_terakhir', 255)->nullable();
            $table->string('kolom', 255)->nullable();
        });

        // Setting Target
        Schema::create('target_setting', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('no')->nullable();
            $table->string('nama_target', 255)->nullable();
            $table->string('kategori', 255)->nullable();
            $table->bigInteger('jumlah_target')->nullable();
            $table->string('kolom', 255)->nullable();
        });

        // Kategori User
        Schema::create('kategori_user', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('source_id')->nullable();
            $table->string('nama_kategori', 255)->nullable();
            $table->string('scope_data', 255)->nullable();
            $table->bigInteger('permissions')->nullable();
            $table->index('source_id');
        });

        // Kategori Karyawan
        Schema::create('kategori_karyawan', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('source_id')->nullable();
            $table->string('kategori', 255)->nullable();
            $table->index('source_id');
        });

        // Kategori Outlet
        Schema::create('kategori_outlet', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('source_id')->nullable();
            $table->string('nama_kategori', 255)->nullable();
            $table->string('deskripsi', 255)->nullable();
            $table->index('source_id');
        });

        // Rumus Insentif Poin Sales - Rumus Insentif Point Sales
        Schema::create('rumus_insentif_point_sales_0', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('source_id')->nullable();
            $table->string('deskripsi', 255)->nullable();
            $table->bigInteger('point')->nullable();
            $table->string('diupdate_oleh', 255)->nullable();
            $table->string('diupdate_pada', 255)->nullable();
            $table->index('source_id');
        });

        // Rumus Insentif Poin Sales - Target Minimal Poin per hari dan Maksimal Poin per hari untuk mendapatkan insentif
        Schema::create('rumus_insentif_point_sales_1', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('source_id')->nullable();
            $table->bigInteger('minimal_poin_per_hari')->nullable();
            $table->bigInteger('maksimal_poin_per_hari')->nullable();
            $table->string('diupdate_oleh', 255)->nullable();
            $table->string('diupdate_pada', 255)->nullable();
            $table->index('source_id');
        });

        // Rumus Insentif Sales
        Schema::create('rumus_insentif_sales', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('source_id')->nullable();
            $table->string('deskripsi', 255)->nullable();
            $table->string('tot_ec_lower_bound', 255)->nullable();
            $table->string('tot_ec_upper_bound', 255)->nullable();
            $table->string('total_insentif', 255)->nullable();
            $table->index('source_id');
        });

        // Rumus Insentif Koordinator
        Schema::create('rumus_insentif_koordinator', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('source_id')->nullable();
            $table->string('deskripsi', 255)->nullable();
            $table->string('tot_ec_lower_bound', 255)->nullable();
            $table->string('tot_ec_upper_bound', 255)->nullable();
            $table->bigInteger('total_sales')->nullable();
            $table->string('total_insentif', 255)->nullable();
            $table->index('source_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rumus_insentif_koordinator');
        Schema::dropIfExists('rumus_insentif_sales');
        Schema::dropIfExists('rumus_insentif_point_sales_1');
        Schema::dropIfExists('rumus_insentif_point_sales_0');
        Schema::dropIfExists('kategori_outlet');
        Schema::dropIfExists('kategori_karyawan');
        Schema::dropIfExists('kategori_user');
        Schema::dropIfExists('target_setting');
        Schema::dropIfExists('waktu_aktif_app');
        Schema::dropIfExists('panduan');
        Schema::dropIfExists('template_barang_canvassing');
        Schema::dropIfExists('motoris_performance');
        Schema::dropIfExists('motoris_coverage');
        Schema::dropIfExists('rekap_pencapaian_depo_sku_tablenotachieved');
        Schema::dropIfExists('rekap_pencapaian_depo_sku_tableachieved');
        Schema::dropIfExists('rekap_pencapaian_sku_motoris_tablenotachieved');
        Schema::dropIfExists('rekap_pencapaian_sku_motoris_tableachieved');
        Schema::dropIfExists('lap_stok_outlet');
        Schema::dropIfExists('lap_outlet_all');
        Schema::dropIfExists('lap_penjualan_barang_detail');
        Schema::dropIfExists('lap_effective_call_barang');
        Schema::dropIfExists('lap_outlet_ec');
        Schema::dropIfExists('lap_checkin_outlet');
        Schema::dropIfExists('lap_outlet_ac');
        Schema::dropIfExists('lap_penjualan_barang');
        Schema::dropIfExists('lap_effective_call');
        Schema::dropIfExists('lap_outlet_oss');
        Schema::dropIfExists('kelurahan');
        Schema::dropIfExists('lap_barang_canvassing');
        Schema::dropIfExists('lap_pengembalian_barang');
        Schema::dropIfExists('lap_barang_habis_terjual');
        Schema::dropIfExists('master_area');
        Schema::dropIfExists('login_session');
        Schema::dropIfExists('lap_join_visit');
        Schema::dropIfExists('kecamatan');
        Schema::dropIfExists('lap_checkin_checkout');
        Schema::dropIfExists('lap_absensi_sales');
        Schema::dropIfExists('lap_loading_barang_detail');
        Schema::dropIfExists('lap_retur_barang_detail');
        Schema::dropIfExists('absensi_koordinator');
        Schema::dropIfExists('loading_barang');
        Schema::dropIfExists('retur_barang');
        Schema::dropIfExists('lap_stok_kompetitor');
        Schema::dropIfExists('lap_outlet_fiktif');
        Schema::dropIfExists('outlet_duplikat');
        Schema::dropIfExists('lap_outlet_noo');
        Schema::dropIfExists('lap_karyawan');
        Schema::dropIfExists('lap_user');
        Schema::dropIfExists('karyawan_sales');
        Schema::dropIfExists('user_sales');
        Schema::dropIfExists('absensi_sales_terlambat');
        Schema::dropIfExists('lap_absensi_koordinator');
        Schema::dropIfExists('kota_kabupaten');
        Schema::dropIfExists('monitor_absensi_motoris');
        Schema::dropIfExists('lap_loading_barang');
        Schema::dropIfExists('lap_retur_barang');
        Schema::dropIfExists('permintaan_izin');
        Schema::dropIfExists('checkin_pagi');
        Schema::dropIfExists('karyawan_koordinator');
        Schema::dropIfExists('user_koordinator');
        Schema::dropIfExists('absensi_ssk_supervisor');
        Schema::dropIfExists('sales_tidak_penjualan');
        Schema::dropIfExists('sales_tidak_absen');
        Schema::dropIfExists('provinsi');
        Schema::dropIfExists('distributor');
        Schema::dropIfExists('lap_izin_sakit');
        Schema::dropIfExists('wilayah_depo');
        Schema::dropIfExists('master_depo');
        Schema::dropIfExists('sales_point_conversion_per_warehouse');
        Schema::dropIfExists('produk');
        Schema::dropIfExists('lap_penjualan_barang_terbaik_bulanan');
        Schema::dropIfExists('holiday_dates');
        Schema::dropIfExists('lap_penjualan_barang_terbaik_mingguan');
        Schema::dropIfExists('lap_penjualan_barang_terbaik_harian');
        Schema::dropIfExists('city_group');
        Schema::dropIfExists('parameter_pemotong_gaji');
        Schema::dropIfExists('merk_produk');
        Schema::dropIfExists('pengumuman');
        Schema::dropIfExists('user_lainnya');
        Schema::dropIfExists('karyawan_lainnya');
        Schema::dropIfExists('satuan_produk');
        Schema::dropIfExists('super_admin');
        Schema::dropIfExists('tipe_outlet');
        Schema::dropIfExists('master_project');
        Schema::dropIfExists('karyawan_supervisor');
        Schema::dropIfExists('kategori_produk');
        Schema::dropIfExists('produk_kompetitor');
        Schema::dropIfExists('surat_peringatan');
        Schema::dropIfExists('kategori_surat_peringatan');
        Schema::dropIfExists('lap_outlet_ro');
        Schema::dropIfExists('lap_penggajian_koordinator');
        Schema::dropIfExists('lap_penggajian_sales');
        Schema::dropIfExists('lap_insentif_koordinator');
        Schema::dropIfExists('lap_insentif_sales');
        Schema::dropIfExists('penggajian_koordinator');
        Schema::dropIfExists('penggajian_sales');
        Schema::dropIfExists('potongan_gaji');
        Schema::dropIfExists('lap_setor_margin');
        Schema::dropIfExists('lap_pembayaran_tagihan');
        Schema::dropIfExists('setor_margin');
        Schema::dropIfExists('pembayaran_pembelian');
    }
};
