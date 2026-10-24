<?php

namespace App\Support\Marketama;

use App\Support\Akses\HierarkiSales;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Menarik data dari aplikasi lama Marketama ke tabel mk_* (baca saja di sisi sumber).
// Setiap daftar ditarik per 100 baris; posisi disimpan di mk_tarikan sehingga perintah
// bisa dihentikan lalu dilanjutkan. Penyimpanan memakai upsert per ID asli (aman diulang).
class Penarik
{
    /** @var Closure(string): void */
    private Closure $log;

    private bool $ulang = false;

    private bool $areaDariMenu = false;

    private ?int $batas = null;

    /** @var array<string, array<string, int>> cache nama (lowercase) => id per tabel */
    private array $petaNama = [];

    public function __construct(private readonly MarketamaClient $client, ?Closure $log = null)
    {
        $this->log = $log ?? fn (string $pesan) => null;
    }

    /** Abaikan posisi tersimpan dan tarik ulang dari awal. */
    public function ulang(bool $ulang = true): self
    {
        $this->ulang = $ulang;

        return $this;
    }

    /** Ambil area dari menu Master Area (lambat) walaupun area_polygon tersedia. */
    public function areaDariMenuSumber(bool $ya = true): self
    {
        $this->areaDariMenu = $ya;

        return $this;
    }

    /** Batasi jumlah baris per daftar / jumlah form edit (untuk uji coba). */
    public function batas(?int $batas): self
    {
        $this->batas = $batas;

        return $this;
    }

    // ------------------------------------------------------------------ master

    public function master(): void
    {
        $this->depo();
        $this->wilayahDepo();
        $this->area();
        $this->distributor();
        $this->produk();
        $this->koordinator();
    }

    public function depo(): void
    {
        $now = now();
        $hasil = $this->client->getJson('/karyawan_sales/get_depo');
        $rows = array_map(fn ($d) => ['id' => (int) $d['id'], 'nama' => trim((string) ($d['text'] ?? $d['name'] ?? '')), 'ditarik_pada' => $now], $hasil['results'] ?? []);
        $this->simpan('mk_depo', $rows, ['id'], ['nama', 'ditarik_pada']);
        $this->lupaPeta('mk_depo');

        $this->daftar('master_depo', function (array $baris) use ($now) {
            $rows = [];

            foreach ($baris as [$id, $data]) {
                $depoId = $this->idDari('mk_depo', $id) ?? $this->idNama('mk_depo', $data['nama'] ?? null);

                if (! $depoId) {
                    continue;
                }

                $rows[] = [
                    'id' => $depoId,
                    'nama' => $data['nama'] ?? '',
                    'kode' => $data['kode'] ?? null,
                    'aktif' => HalamanParser::aktif($data['status'] ?? null),
                    'data' => json_encode($data),
                    'ditarik_pada' => $now,
                ];
            }

            $this->simpan('mk_depo', $rows, ['id'], ['kode', 'aktif', 'data', 'ditarik_pada']);
        });

        ($this->log)(sprintf('mk_depo: %d depo', DB::table('mk_depo')->count()));
    }

    public function wilayahDepo(): void
    {
        $now = now();
        $this->daftar('wilayah_depo', function (array $baris) use ($now) {
            $this->simpan('mk_wilayah_depo', array_map(fn ($b) => [
                'id' => $b[0],
                'nama' => $b[1]['nama'] ?? '',
                'depo_id' => $this->idNama('mk_depo', $b[1]['depo_warehouse'] ?? $b[1]['depo'] ?? null),
                'data' => json_encode($b[1]),
                'ditarik_pada' => $now,
            ], $baris), ['id'], ['nama', 'depo_id', 'data', 'ditarik_pada']);
        });
    }

    public function area(): void
    {
        $now = now();

        // area_polygon (hasil tarikan API area sebelumnya) memakai ID area asli Marketama
        // (source_id), jadi cukup disalin — jauh lebih cepat daripada menu Master Area yang
        // di server lama bisa >60 detik per halaman.
        if (! $this->areaDariMenu && Schema::hasTable('area_polygon') && DB::table('area_polygon')->whereNotNull('source_id')->exists()) {
            $this->areaDariPolygon($now);
        } else {
            $this->areaDariMenu($now);
        }

        // Daftar area untuk form sales (ID + nama) sebagai pelengkap.
        $hasil = $this->client->getJson('/karyawan_sales/get_area_list');
        $ada = DB::table('mk_area')->pluck('id')->flip();
        $baru = [];

        foreach ($hasil['results'] ?? [] as $a) {
            if (! isset($ada[(int) $a['id']])) {
                $baru[] = ['id' => (int) $a['id'], 'nama' => (string) ($a['name'] ?? $a['text'] ?? ''), 'ditarik_pada' => $now];
            }
        }

        $this->simpan('mk_area', $baru, ['id'], ['nama', 'ditarik_pada']);
        $this->lupaPeta('mk_area');
        ($this->log)(sprintf('mk_area: %d area (%d dari daftar area sales)', DB::table('mk_area')->count(), count($baru)));

        // Outlet yang ditarik sebelum area lengkap: sambungkan area_id-nya sekarang.
        $this->sambung();
    }

    private function areaDariPolygon($now): void
    {
        $kolom = ['source_id', 'code', 'name', 'warehouse', 'warehouse_id', 'branch_area', 'branch_area_id', 'province', 'province_id', 'city', 'city_id', 'district', 'district_id', 'village', 'village_id', 'outlet_count', 'salesperson'];
        $jumlah = 0;

        DB::table('area_polygon')->whereNotNull('source_id')->select($kolom)->orderBy('source_id')
            ->chunk(1000, function ($rows) use ($now, &$jumlah) {
                $this->simpan('mk_area', $rows->map(fn ($r) => [
                    'id' => (int) $r->source_id,
                    'kode' => $r->code,
                    'nama' => (string) $r->name,
                    'depo_id' => $r->warehouse_id ? (int) $r->warehouse_id : $this->idNama('mk_depo', $r->warehouse),
                    'data' => json_encode(array_diff_key((array) $r, ['source_id' => 1]) + ['sumber' => 'area_polygon']),
                    'ditarik_pada' => $now,
                ])->all(), ['id'], ['kode', 'nama', 'depo_id', 'data', 'ditarik_pada']);
                $jumlah += $rows->count();
            });

        ($this->log)(sprintf('mk_area: %s area disalin dari area_polygon (ID asli), menu Master Area dilewati', number_format($jumlah, 0, ',', '.')));
    }

    private function areaDariMenu($now): void
    {
        $this->daftar('master_area', function (array $baris) use ($now) {
            $this->simpan('mk_area', array_map(fn ($b) => [
                'id' => $b[0],
                'kode' => $this->ambil($b[1], ['kode', 'kode_area', 'code']),
                'nama' => (string) $this->ambil($b[1], ['nama', 'nama_area', 'name', 'area']),
                'depo_id' => $this->idNama('mk_depo', $this->ambil($b[1], ['depo', 'warehouse', 'depo_warehouse', 'gudang'])),
                'data' => json_encode($b[1]),
                'ditarik_pada' => $now,
            ], $baris), ['id'], ['kode', 'nama', 'depo_id', 'data', 'ditarik_pada']);
        });
    }

    public function distributor(): void
    {
        $now = now();
        $this->daftar('distributor', function (array $baris) use ($now) {
            $this->simpan('mk_distributor', array_map(fn ($b) => [
                'id' => $b[0],
                'kode' => $this->ambil($b[1], ['kode', 'kode_distributor']),
                'nama' => (string) $this->ambil($b[1], ['nama_distributor', 'nama', 'distributor']),
                'depo_id' => $this->idNama('mk_depo', $this->ambil($b[1], ['depo', 'warehouse', 'gudang'])),
                'telepon' => $this->ambil($b[1], ['no_telepon', 'telepon', 'no_hp']),
                'contact_person' => $this->ambil($b[1], ['contact_person']),
                'data' => json_encode($b[1]),
                'ditarik_pada' => $now,
            ], $baris), ['id'], ['kode', 'nama', 'depo_id', 'telepon', 'contact_person', 'data', 'ditarik_pada']);
        });

        $this->detail('distributor', DB::table('mk_distributor')->pluck('id')->all(), function (int $id, array $f) {
            DB::table('mk_distributor')->where('id', $id)->update(array_filter([
                'depo_id' => $this->int($f['warehouse_id'] ?? null),
                'alamat' => $f['address'] ?? null,
            ], fn ($v) => $v !== null));
        });
    }

    public function produk(): void
    {
        $now = now();
        $this->daftar('produk', function (array $baris) use ($now) {
            $this->simpan('mk_produk', array_map(fn ($b) => [
                'id' => $b[0],
                'kode' => $this->ambil($b[1], ['kode', 'kode_produk', 'sku']),
                'nama' => (string) $this->ambil($b[1], ['nama_produk', 'nama', 'produk']),
                'data' => json_encode($b[1]),
                'ditarik_pada' => $now,
            ], $baris), ['id'], ['kode', 'nama', 'data', 'ditarik_pada']);
        });

        $this->detail('produk', DB::table('mk_produk')->pluck('id')->all(), function (int $id, array $f) {
            DB::table('mk_produk')->where('id', $id)->update(array_filter([
                'kode' => $f['code'] ?? null,
                'merk_id' => $this->int($f['brand_id'] ?? null),
                'kategori_id' => $this->int($f['category_id'] ?? null),
                'satuan_id' => $this->int($f['unit_id'] ?? null),
                'harga_beli' => HalamanParser::angka($f['cost'] ?? null),
                'harga_jual' => HalamanParser::angka($f['price'] ?? null),
            ], fn ($v) => $v !== null));
        });
    }

    public function koordinator(): void
    {
        $now = now();
        $hasil = $this->client->getJson('/api/global/get_koordinators');
        $rows = array_map(fn ($k) => [
            'id' => (int) $k['id'],
            'kategori' => 'koordinator',
            'nama' => trim((string) ($k['fullname'] ?? $k['text'] ?? '')),
            'ditarik_pada' => $now,
        ], $hasil['results'] ?? $hasil['data'] ?? []);
        $this->simpan('mk_karyawan', $rows, ['id'], ['kategori', 'nama', 'ditarik_pada']);
        $this->lupaPeta('mk_karyawan');

        // Daftar menu Karyawan Koordinator: depo, no HP, status.
        $this->daftar('karyawan_koordinator', function (array $baris) use ($now) {
            foreach ($baris as [$id, $data]) {
                $karyawanId = $this->idDari('mk_karyawan', $id) ?? $this->idNama('mk_karyawan', $data['nama_lengkap'] ?? null, 'koordinator');

                if (! $karyawanId) {
                    continue;
                }

                DB::table('mk_karyawan')->where('id', $karyawanId)->update([
                    'depo_id' => $this->idNama('mk_depo', $data['depo'] ?? null),
                    'no_hp' => $data['no_hp'] ?? null,
                    'aktif' => HalamanParser::aktif($data['status'] ?? null),
                    'data' => json_encode($data),
                    'ditarik_pada' => $now,
                ]);
            }
        });

        ($this->log)(sprintf('koordinator: %d orang', DB::table('mk_karyawan')->where('kategori', 'koordinator')->count()));
    }

    // ---------------------------------------------------------------- karyawan

    public function karyawan(bool $detail = true): void
    {
        $now = now();

        foreach (['aktif', 'tidak_aktif'] as $mode) {
            $this->daftar('karyawan_sales', function (array $baris) use ($now) {
                $this->simpan('mk_karyawan', array_map(fn ($b) => [
                    'id' => $b[0],
                    'kategori' => 'sales',
                    'nama' => (string) ($b[1]['nama_lengkap'] ?? ''),
                    'depo_id' => $this->idNama('mk_depo', $b[1]['depo'] ?? null),
                    'koordinator_id' => $this->idNama('mk_karyawan', $b[1]['koordinator'] ?? null, 'koordinator'),
                    'no_hp' => $b[1]['no_hp'] ?? null,
                    'aktif' => HalamanParser::aktif($b[1]['status'] ?? null),
                    'data' => json_encode($b[1]),
                    'ditarik_pada' => $now,
                ], $baris), ['id'], ['kategori', 'nama', 'depo_id', 'koordinator_id', 'no_hp', 'aktif', 'data', 'ditarik_pada']);
            }, ['mode' => $mode], 'karyawan_sales:'.$mode, 'tabel_karyawan_sales');
        }

        foreach (['karyawan_supervisor' => 'supervisor', 'karyawan_lainnya' => 'lainnya'] as $menu => $kategori) {
            $this->daftar($menu, function (array $baris) use ($now, $kategori) {
                $this->simpan('mk_karyawan', array_map(fn ($b) => [
                    'id' => $b[0],
                    'kategori' => $kategori,
                    'nama' => (string) ($b[1]['nama_lengkap'] ?? ''),
                    'no_hp' => $b[1]['no_hp'] ?? null,
                    'email' => $b[1]['email'] ?? null,
                    'aktif' => HalamanParser::aktif($b[1]['status'] ?? null),
                    'data' => json_encode($b[1]),
                    'ditarik_pada' => $now,
                ], $baris), ['id'], ['kategori', 'nama', 'no_hp', 'email', 'aktif', 'data', 'ditarik_pada']);
            });
        }

        $this->lupaPeta('mk_karyawan');
        HierarkiSales::terapkanUlang();

        if (! $detail) {
            return;
        }

        // Form edit sales: ID depo, ID koordinator, daftar area. Gaji/tunjangan tidak disimpan.
        $ids = DB::table('mk_karyawan')->where('kategori', 'sales')->where('detail_lengkap', false)->orderBy('id')->pluck('id')->all();
        $this->detail('karyawan_sales', $ids, function (int $id, array $f, string $html) {
            $areas = HalamanParser::areaKaryawan($html);
            DB::transaction(function () use ($id, $f, $areas) {
                DB::table('mk_karyawan')->where('id', $id)->update(array_filter([
                    'depo_id' => $this->int($f['warehouse_id'] ?? null),
                    'koordinator_id' => $this->int($f['koordinator_id'] ?? null),
                    'area_aktif_id' => $this->int($f['area_id'] ?? null),
                    'email' => $f['email'] ?? null,
                    'no_hp' => $f['phone'] ?? null,
                    'tanggal_mulai' => $f['employment_start_date'] ?? null,
                    'aktif' => HalamanParser::aktif($f['status'] ?? null),
                ], fn ($v) => $v !== null) + ['detail_lengkap' => true]);

                DB::table('mk_karyawan_area')->where('karyawan_id', $id)->delete();
                $this->simpan('mk_karyawan_area', array_map(fn ($a) => [
                    'karyawan_id' => $id,
                    'area_id' => $a['area_id'],
                    'aktif' => $a['aktif'],
                ], $areas), ['karyawan_id', 'area_id'], ['aktif']);
            });
        });

        // Koordinator yang diatur dari Enesis menang atas data aplikasi lama.
        HierarkiSales::terapkanUlang();

        ($this->log)(sprintf('mk_karyawan: %d orang, %d relasi area', DB::table('mk_karyawan')->count(), DB::table('mk_karyawan_area')->count()));
    }

    // -------------------------------------------------------------------- user

    public function user(bool $detail = true): void
    {
        $now = now();
        $menus = ['user_sales' => 'sales', 'user_koordinator' => 'koordinator', 'user_lainnya' => 'lainnya', 'super_admin' => 'super_admin'];

        foreach ($menus as $menu => $jenis) {
            $this->daftar($menu, function (array $baris) use ($now, $jenis) {
                $this->simpan('mk_user', array_map(fn ($b) => [
                    'jenis' => $jenis,
                    'id' => $b[0],
                    'username' => (string) ($b[1]['username'] ?? ''),
                    'nickname' => $b[1]['nickname'] ?? null,
                    'nama_karyawan' => $b[1]['karyawan'] ?? null,
                    // Sementara dari nama (unik); diganti ID dari form edit bila tersedia.
                    'karyawan_id' => $this->idNama('mk_karyawan', $b[1]['karyawan'] ?? null),
                    'aktif' => HalamanParser::aktif($b[1]['status'] ?? null),
                    'data' => json_encode($b[1]),
                    'ditarik_pada' => $now,
                ], $baris), ['jenis', 'id'], ['username', 'nickname', 'nama_karyawan', 'karyawan_id', 'aktif', 'data', 'ditarik_pada']);
            });

            if (! $detail) {
                continue;
            }

            $ids = DB::table('mk_user')->where('jenis', $jenis)->where('detail_lengkap', false)->orderBy('id')->pluck('id')->all();
            $this->detail($menu, $ids, function (int $id, array $f) use ($jenis) {
                DB::table('mk_user')->where('jenis', $jenis)->where('id', $id)->update(array_filter([
                    'karyawan_id' => $this->int($f['id_employee'] ?? null),
                ], fn ($v) => $v !== null) + ['detail_lengkap' => true]);
            });
        }

        ($this->log)(sprintf('mk_user: %d akun, %d tersambung ke karyawan', DB::table('mk_user')->count(), DB::table('mk_user')->whereNotNull('karyawan_id')->count()));
    }

    // ------------------------------------------------------------------ outlet

    public function outlet(): void
    {
        $now = now();
        $this->daftar('outlet_all', function (array $baris) use ($now) {
            $this->simpan('mk_outlet', array_map(fn ($b) => [
                'id' => $b[0],
                'kode' => $b[1]['kode_outlet'] ?? null,
                'nama' => $b[1]['nama_outlet'] ?? null,
                'alamat' => $b[1]['alamat'] ?? null,
                'area' => $b[1]['area'] ?? null,
                'area_id' => $this->idNama('mk_area', $b[1]['area'] ?? null),
                'provinsi' => $b[1]['provinsi'] ?? null,
                'kota_kabupaten' => $b[1]['kota_kabupaten'] ?? null,
                'kecamatan' => $b[1]['kecamatan'] ?? null,
                'kelurahan' => $b[1]['kelurahan'] ?? null,
                'longitude' => $this->koordinat($b[1]['longitude'] ?? null, 180),
                'latitude' => $this->koordinat($b[1]['latitude'] ?? null, 90),
                'status_fiktif' => $b[1]['status_fiktif'] ?? null,
                'verifikasi' => $b[1]['verifikasi'] ?? null,
                'dibuat_oleh' => $b[1]['dibuat_oleh'] ?? null,
                'dibuat_pada' => $b[1]['dibuat_pada'] ?? null,
                'ditarik_pada' => $now,
            ], $baris), ['id'], ['kode', 'nama', 'alamat', 'area', 'area_id', 'provinsi', 'kota_kabupaten', 'kecamatan', 'kelurahan', 'longitude', 'latitude', 'status_fiktif', 'verifikasi', 'dibuat_oleh', 'dibuat_pada', 'ditarik_pada']);
        });

        ($this->log)(sprintf('mk_outlet: %d outlet', DB::table('mk_outlet')->count()));
    }

    /**
     * Isi relasi yang masih kosong dari data yang sudah tersimpan (tanpa menarik ulang):
     * mk_outlet.area_id dari nama area, mk_user.karyawan_id dari nama karyawan.
     * Nama yang kembar tidak dipetakan.
     */
    public function sambung(): void
    {
        $this->lupaPeta('mk_area');
        $this->lupaPeta('mk_karyawan');
        $outlet = 0;

        $namaArea = DB::table('mk_outlet')->whereNull('area_id')->whereNotNull('area')->distinct()->pluck('area');

        foreach ($namaArea as $nama) {
            if ($id = $this->idNama('mk_area', $nama)) {
                $outlet += DB::table('mk_outlet')->whereNull('area_id')->where('area', $nama)->update(['area_id' => $id]);
            }
        }

        $user = 0;

        foreach (DB::table('mk_user')->whereNull('karyawan_id')->whereNotNull('nama_karyawan')->get(['jenis', 'id', 'nama_karyawan']) as $u) {
            if ($id = $this->idNama('mk_karyawan', $u->nama_karyawan)) {
                $user += DB::table('mk_user')->where(['jenis' => $u->jenis, 'id' => $u->id])->update(['karyawan_id' => $id]);
            }
        }

        ($this->log)(sprintf(
            'sambung: %s outlet dapat area_id (sisa tanpa area: %s), %d user dapat karyawan_id',
            number_format($outlet, 0, ',', '.'),
            number_format(DB::table('mk_outlet')->whereNull('area_id')->count(), 0, ',', '.'),
            $user,
        ));
    }

    // ------------------------------------------------------------------ mentah

    /** Simpan baris menu apa pun (mis. penjualan_barang) apa adanya ke mk_baris_mentah. */
    public function mentah(string $menu): void
    {
        $now = now();
        $this->daftar($menu, function (array $baris) use ($now, $menu) {
            $this->simpan('mk_baris_mentah', array_map(fn ($b) => [
                'menu' => $menu,
                'id' => $b[0],
                'data' => json_encode($b[1]),
                'ditarik_pada' => $now,
            ], $baris), ['menu', 'id'], ['data', 'ditarik_pada']);
        });
    }

    // ---------------------------------------------------------------- inti

    /**
     * Tarik semua halaman DataTables sebuah menu. Callback menerima list<[id, data]>.
     *
     * @param  Closure(list<array{0:int,1:array}>): void  $simpan
     */
    public function daftar(string $menu, Closure $simpan, array $extra = [], ?string $kunci = null, ?string $tableId = null): void
    {
        $kunci = 'list:'.($kunci ?? $menu);
        $info = $this->client->infoMenu($menu, $tableId);

        if (! $info['url'] || ! $info['token']) {
            ($this->log)("{$menu}: endpoint DataTables tidak ditemukan, dilewati");
            $this->catat($kunci, ['catatan' => 'endpoint tidak ditemukan']);

            return;
        }

        $status = $this->status($kunci);

        if ($status->selesai && ! $this->ulang) {
            ($this->log)("{$menu}: sudah selesai sebelumnya ({$status->tersimpan} baris), dilewati");

            return;
        }

        $start = $this->ulang ? 0 : (int) $status->posisi;
        $tersimpan = $this->ulang ? 0 : (int) $status->tersimpan;
        $per = (int) config('marketama_sumber.per_halaman', 100);
        $total = null;

        do {
            $halaman = $this->halamanTahanTimeout($kunci, $info, $start, $per, $extra);
            $total = $halaman['filtered'];
            $baris = [];

            foreach ($halaman['data'] as $sel) {
                $sel = array_values((array) $sel);
                $id = HalamanParser::idBaris($sel);

                if ($id !== null) {
                    $baris[] = [$id, HalamanParser::baris($info['kolom'], $sel)];
                }
            }

            if ($baris) {
                $simpan($baris);
            }

            $start += $per;
            $tersimpan += count($baris);
            $this->catat($kunci, ['total_sumber' => $total, 'posisi' => $start, 'tersimpan' => $tersimpan, 'mulai_pada' => $status->mulai_pada ?? now()]);

            if ($start % 5000 === 0 || $start >= $total) {
                ($this->log)(sprintf('%s: %s / %s', $kunci, number_format(min($start, $total), 0, ',', '.'), number_format($total, 0, ',', '.')));
            }

            $habis = $halaman['data'] === [] || $start >= $total || ($this->batas !== null && $tersimpan >= $this->batas);
        } while (! $habis);

        $selesai = $start >= $total;
        $this->catat($kunci, ['selesai' => $selesai && $this->batas === null, 'selesai_pada' => $selesai ? now() : null]);
    }

    /**
     * Ambil satu halaman DataTables. Bila server lama timeout (mis. master_area yang menghitung
     * total toko per area), tunggu lalu ulangi dengan halaman lebih kecil: 100 -> 50 -> 25 -> 12 -> 10.
     * Ukuran yang berhasil dipakai untuk halaman berikutnya ($per diubah lewat referensi).
     */
    private function halamanTahanTimeout(string $kunci, array $info, int $start, int &$per, array $extra): array
    {
        $gagalDiUkuranMinimum = 0;

        while (true) {
            try {
                return $this->client->datatable($info['url'], $info['token'], $start, $per, $extra);
            } catch (ConnectionException $e) {
                if (! MarketamaClient::timeout($e) || $gagalDiUkuranMinimum >= 3) {
                    throw $e;
                }

                if ($per > 10) {
                    $per = max(10, intdiv($per, 2));
                } else {
                    $gagalDiUkuranMinimum++;
                }

                ($this->log)(sprintf('%s: timeout di baris %s, coba lagi per %d baris', $kunci, number_format($start, 0, ',', '.'), $per));
                sleep((int) config('marketama_sumber.jeda_gagal_detik', 10));
            }
        }
    }

    /**
     * Baca form edit /{menu}/edit/{id} untuk setiap ID (hanya GET, tidak pernah disimpan).
     *
     * @param  list<int|string>  $ids
     * @param  Closure(int, array, string): void  $proses
     */
    public function detail(string $menu, array $ids, Closure $proses): void
    {
        $kunci = 'edit:'.$menu;
        $total = count($ids);
        $gagal = 0;

        if ($this->batas !== null) {
            $ids = array_slice($ids, 0, $this->batas);
        }

        foreach (array_values($ids) as $i => $id) {
            $html = $this->client->coba("/{$menu}/edit/{$id}");

            if ($html === null) {
                $gagal++;

                // Menu tanpa form edit (HTTP 404) — hentikan setelah beberapa kali gagal beruntun.
                if ($gagal >= 3 && $i + 1 === $gagal) {
                    ($this->log)("{$menu}: form edit tidak tersedia, detail dilewati");
                    $this->catat($kunci, ['catatan' => 'form edit tidak tersedia']);

                    return;
                }

                continue;
            }

            $proses((int) $id, HalamanParser::form($html), $html);

            if (($i + 1) % 100 === 0 || $i + 1 === count($ids)) {
                ($this->log)(sprintf('%s: %d / %d form', $kunci, $i + 1, $total));
                $this->catat($kunci, ['total_sumber' => $total, 'posisi' => $i + 1, 'tersimpan' => $i + 1 - $gagal]);
            }
        }

        $this->catat($kunci, ['selesai' => $this->batas === null, 'selesai_pada' => now(), 'catatan' => $gagal ? "{$gagal} form gagal dibuka" : null]);
    }

    private function status(string $kunci): object
    {
        return DB::table('mk_tarikan')->where('kunci', $kunci)->first()
            ?? (object) ['posisi' => 0, 'tersimpan' => 0, 'selesai' => false, 'mulai_pada' => null];
    }

    private function catat(string $kunci, array $nilai): void
    {
        DB::table('mk_tarikan')->upsert([['kunci' => $kunci] + $nilai], ['kunci'], array_keys($nilai));
    }

    /** Upsert per potongan (aman untuk batas placeholder MySQL/PostgreSQL). */
    private function simpan(string $table, array $rows, array $unik, array $update): void
    {
        if (! $rows) {
            return;
        }

        // Seragamkan kolom antar baris supaya satu perintah upsert valid.
        $kolom = array_keys(array_merge(...array_map(fn ($r) => array_fill_keys(array_keys($r), true), $rows)));
        $rows = array_map(fn ($r) => array_merge(array_fill_keys($kolom, null), $r), $rows);
        $update = array_values(array_intersect($update, $kolom));
        $per = max(1, intdiv(30000, count($kolom)));

        foreach (array_chunk($rows, $per) as $potongan) {
            DB::table($table)->upsert($potongan, $unik, $update);
        }
    }

    private function ambil(array $data, array $calon): ?string
    {
        foreach ($calon as $k) {
            if (isset($data[$k]) && $data[$k] !== '') {
                return $data[$k];
            }
        }

        return null;
    }

    private function int(?string $v): ?int
    {
        return $v !== null && ctype_digit(trim($v)) ? (int) $v : null;
    }

    private function koordinat(?string $v, int $maks): ?float
    {
        $n = HalamanParser::angka($v, desimalTitik: true);

        return $n !== null && abs($n) <= $maks ? $n : null;
    }

    private function idDari(string $table, int $id): ?int
    {
        return DB::table($table)->where('id', $id)->exists() ? $id : null;
    }

    /** ID dari nama (tanpa beda huruf besar/kecil). Nama yang kembar dianggap tidak bisa dipetakan. */
    private function idNama(string $table, ?string $nama, ?string $kategori = null): ?int
    {
        if ($nama === null || trim($nama) === '' || trim($nama) === '-') {
            return null;
        }

        $kunciPeta = $table.'|'.$kategori;

        if (! isset($this->petaNama[$kunciPeta])) {
            $q = DB::table($table)->select('id', 'nama');

            if ($kategori) {
                $q->where('kategori', $kategori);
            }

            $peta = [];
            $kembar = [];

            foreach ($q->cursor() as $r) {
                $k = HalamanParser::kunciNama($r->nama);
                isset($peta[$k]) ? $kembar[$k] = true : $peta[$k] = (int) $r->id;
            }

            $this->petaNama[$kunciPeta] = array_diff_key($peta, $kembar);
        }

        return $this->petaNama[$kunciPeta][HalamanParser::kunciNama($nama)] ?? null;
    }

    private function lupaPeta(string $table): void
    {
        foreach (array_keys($this->petaNama) as $k) {
            if (str_starts_with($k, $table.'|')) {
                unset($this->petaNama[$k]);
            }
        }
    }
}
