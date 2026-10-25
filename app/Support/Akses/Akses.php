<?php

namespace App\Support\Akses;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Hak akses berbasis role: menu yang terlihat, aksi yang boleh (lihat/tambah/ubah/hapus/ekspor),
// cakupan data, dan tempat login (web / aplikasi HP). Setiap akun punya satu role; akun lama
// tanpa role_id memakai role bawaan sesuai kategorinya. Akun tanpa kategori dan tanpa role
// (akun teknis buatan tangan) tidak dibatasi.
class Akses
{
    public const AKSI = ['lihat' => 'Lihat', 'tambah' => 'Tambah', 'ubah' => 'Ubah', 'hapus' => 'Hapus', 'ekspor' => 'Ekspor'];

    public const CAKUPAN = [
        'semua' => ['Semua', 'Seluruh depo dan karyawan'],
        'depo' => ['Depo sendiri', 'Depo dari data karyawan akun'],
        'downline' => ['Per downline', 'Dirinya dan sales di bawahnya'],
        'diri' => ['Diri sendiri', 'Hanya data akun itu'],
    ];

    /** Menu kelola akun & role: hanya untuk role yang diberi hak eksplisit. */
    public const MENU_ADMIN = ['super_admin', 'user_koordinator', 'user_sales', 'user_lainnya', 'role', 'login_session'];

    /** Hapus cache role permintaan ini (setelah role diubah). */
    public static function lupakan(): void
    {
        if (app()->bound('request')) {
            request()->attributes->remove('akses_cache');
        }
    }

    /** Cache per permintaan HTTP (bukan statis, supaya perubahan role langsung berlaku). */
    private static function ingat(string $kunci, callable $buat): mixed
    {
        $req = app()->bound('request') ? request() : null;
        $semua = $req?->attributes->get('akses_cache', []) ?? [];

        if (array_key_exists($kunci, $semua)) {
            return $semua[$kunci];
        }

        $nilai = $buat();

        if ($req) {
            $semua[$kunci] = $nilai;
            $req->attributes->set('akses_cache', $semua);
        }

        return $nilai;
    }

    /** Role efektif akun beserta hak per menu (null = tidak dibatasi). */
    public static function role(?User $user): ?object
    {
        if (! $user) {
            return null;
        }

        if (! $user->role_id && ! $user->kategori) {
            return null;
        }

        $kunci = $user->role_id ? 'id:'.$user->role_id : 'kode:'.$user->kategori;

        return self::ingat($kunci, function () use ($user) {
            if (! Schema::hasTable('roles')) {
                return null;
            }

            $role = $user->role_id
                ? DB::table('roles')->where('id', $user->role_id)->first()
                : DB::table('roles')->where('kode', $user->kategori)->first();

            if ($role) {
                $role->hak = self::hakRole((int) $role->id);
            }

            return $role;
        });
    }

    /** @return array<string, array<string, bool>> menu => [aksi => boleh] */
    public static function hakRole(int $roleId): array
    {
        $hak = [];

        foreach (DB::table('role_hak')->where('role_id', $roleId)->get() as $h) {
            foreach (array_keys(self::AKSI) as $aksi) {
                $hak[$h->menu][$aksi] = (bool) $h->{$aksi};
            }
        }

        return $hak;
    }

    public static function penuh(?User $user): bool
    {
        $role = self::role($user);

        return $role === null || $role->kode === 'super_admin';
    }

    public static function bisa(?User $user, string $menu, string $aksi = 'lihat'): bool
    {
        if (! $user) {
            return false;
        }

        if (self::penuh($user)) {
            return true;
        }

        return self::role($user)->hak[$menu][$aksi] ?? false;
    }

    public static function bolehWeb(?User $user): bool
    {
        $role = self::role($user);

        return $role === null || (bool) $role->login_web;
    }

    public static function bolehMobile(?User $user): bool
    {
        $role = self::role($user);

        return $role === null || (bool) $role->login_mobile;
    }

    public static function cakupan(?User $user): string
    {
        return self::penuh($user) ? 'semua' : (self::role($user)->cakupan ?: 'semua');
    }

    /**
     * Batasi query data karyawan sesuai cakupan role. $k = alias tabel mk_karyawan pada query.
     */
    public static function batasiKaryawan(Builder $q, ?User $user, string $k = 'k'): Builder
    {
        $karyawan = $user?->karyawan_id;

        return match (self::cakupan($user)) {
            'depo' => $q->where("{$k}.depo_id", DB::table('mk_karyawan')->where('id', $karyawan ?? 0)->value('depo_id') ?? -1),
            'downline' => $q->where(fn ($w) => $w->where("{$k}.koordinator_id", $karyawan ?? -1)->orWhere("{$k}.id", $karyawan ?? -1)),
            'diri' => $q->where("{$k}.id", $karyawan ?? -1),
            default => $q,
        };
    }

    /** Menu sidebar yang boleh dilihat akun (grup kosong disembunyikan). */
    public static function menu(?User $user): array
    {
        // Sidebar hanya tampil setelah login; tanpa user (mis. render komponen di tes) tampil utuh.
        if (! $user) {
            return self::tandaiMotoris(config('marketama.menu'));
        }

        $hasil = [];

        foreach (config('marketama.menu') as $grup) {
            $items = [];

            foreach ($grup['items'] as $item) {
                if (isset($item['children'])) {
                    $anak = array_values(array_filter($item['children'], fn ($c) => self::bisa($user, $c['slug'])));

                    if ($anak) {
                        $items[] = ['children' => $anak] + $item;
                    }
                } elseif (self::bisa($user, $item['slug'])) {
                    $items[] = $item;
                }
            }

            if ($items) {
                $hasil[] = ['items' => $items] + $grup;
            }
        }

        return self::tandaiMotoris($hasil);
    }

    /** Menu sudah terhubung ke data aplikasi motoris (config marketama.menu_motoris)? */
    public static function terhubungMotoris(string $slug): bool
    {
        return in_array($slug, config('marketama.menu_motoris', []), true);
    }

    /** Beri awalan "* " pada judul menu (bukan grup) yang belum terhubung ke data motoris. */
    private static function tandaiMotoris(array $menu): array
    {
        $tandai = function (array $item) use (&$tandai) {
            if (isset($item['children'])) {
                $item['children'] = array_map($tandai, $item['children']);
            } elseif (! self::terhubungMotoris($item['slug'])) {
                $item['title'] = '* '.$item['title'];
            }

            return $item;
        };

        return array_map(fn ($grup) => ['items' => array_map($tandai, $grup['items'])] + $grup, $menu);
    }

    /**
     * Menu untuk pencarian sidebar: sama dengan menu() yang diratakan (termasuk children), ditambah
     * satu entri per tab untuk halaman bertab (config('marketama.pages.*.tabs')) — supaya menu lama
     * yang sudah digabung jadi tab (mis. "Sales Tidak Penjualan") tetap bisa dicari lewat judul
     * lamanya dan langsung membuka tab yang sesuai.
     *
     * @return array<int, array{title: string, slug: string}>
     */
    public static function menuPencarian(?User $user): array
    {
        $hasil = [];

        foreach (self::menu($user) as $grup) {
            foreach ($grup['items'] as $item) {
                foreach ($item['children'] ?? [$item] as $m) {
                    $hasil[] = ['title' => $m['title'], 'slug' => $m['slug']];

                    if (config("marketama.pages.{$m['slug']}.tabs") === true) {
                        foreach (config("marketama.pages.{$m['slug']}.tables") as $grupTab) {
                            $hasil[] = ['title' => $grupTab['tab_label'], 'slug' => "{$m['slug']}?tab={$grupTab['tab_key']}"];
                        }
                    }
                }
            }
        }

        return $hasil;
    }

    /**
     * Semua menu dalam urutan sidebar, dikelompokkan per grup (untuk matriks hak role).
     *
     * @return array<int, array{label: string, menu: array<int, array{slug: string, nama: string, title: string}>}>
     */
    public static function daftarMenu(): array
    {
        $hasil = [];

        foreach (config('marketama.menu') as $grup) {
            $menu = [];

            foreach ($grup['items'] as $item) {
                foreach ($item['children'] ?? [$item] as $m) {
                    $menu[] = [
                        'slug' => $m['slug'],
                        'nama' => $m['title'],
                        'title' => isset($item['children']) ? $item['title'].' › '.$m['title'] : $m['title'],
                    ];
                }
            }

            $hasil[] = ['label' => $grup['label'] ?? 'Utama', 'menu' => $menu];
        }

        return $hasil;
    }

    /** @return array<int, string> */
    public static function semuaSlug(): array
    {
        return array_merge(...array_map(fn ($g) => array_column($g['menu'], 'slug'), self::daftarMenu()));
    }

    /** Catat aksi admin (siapa, apa, kapan) untuk riwayat akun dan role. */
    public static function catat(?User $aktor, string $objek, int $objekId, string $aksi, ?string $keterangan = null): void
    {
        DB::table('riwayat_admin')->insert([
            'aktor_id' => $aktor?->id,
            'aktor_nama' => $aktor?->username ?? $aktor?->name,
            'objek' => $objek,
            'objek_id' => $objekId,
            'aksi' => $aksi,
            'keterangan' => $keterangan === null ? null : mb_substr($keterangan, 0, 255),
            'created_at' => now(),
        ]);
    }
}
