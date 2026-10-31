<?php

namespace App\Support\Tabel;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;

/**
 * Sort & filter per kolom untuk tabel yang query-nya dikelola langsung oleh controller (bukan lewat
 * MenuController@muatTabel, lihat juga organism DataTable.vue). Dipakai oleh KehadiranController,
 * KaryawanController, MasterProdukController, dan SummaryDepoController — masing-masing satu
 * tabel/paginator per halaman, jadi query string tidak perlu namespace per tabel seperti di Menu/Show.vue.
 */
class SortFilterKolom
{
    /**
     * Urutan dari query ?sort=<kolom>&arah=asc|desc. Null bila kolom kosong atau tidak ada di whitelist $kolomQuery.
     *
     * @param  array<string, string>  $kolomQuery  peta nama kolom tampilan => ekspresi kolom asli untuk orderBy/where
     * @return array{kolom: string, arah: 'asc'|'desc'}|null
     */
    public static function sort(Request $request, array $kolomQuery): ?array
    {
        $kolom = $request->query('sort');

        if (! is_string($kolom) || ! array_key_exists($kolom, $kolomQuery)) {
            return null;
        }

        return ['kolom' => $kolom, 'arah' => $request->query('arah') === 'desc' ? 'desc' : 'asc'];
    }

    /** Terapkan hasil {@see self::sort()} ke query, lalu `id` sebagai pengurut kedua supaya urutan antarhalaman stabil. */
    public static function terapkanSort(Builder $query, array $sort, array $kolomQuery, string $kolomId = 'id'): void
    {
        $query->orderBy($kolomQuery[$sort['kolom']], $sort['arah'])->orderBy($kolomId);
    }

    /**
     * Nilai filter per kolom dari query ?filter[<kolom>]=nilai. Hanya kolom di whitelist $kolomQuery dengan nilai tidak kosong.
     *
     * @param  array<string, string>  $kolomQuery
     * @return array<string, string>
     */
    public static function filter(Request $request, array $kolomQuery): array
    {
        $input = $request->input('filter');

        if (! is_array($input)) {
            return [];
        }

        $aktif = [];

        foreach ($kolomQuery as $kolom => $ekspresi) {
            $nilai = $input[$kolom] ?? null;

            if (is_string($nilai) && trim($nilai) !== '') {
                $aktif[$kolom] = trim($nilai);
            }
        }

        return $aktif;
    }

    /**
     * Terapkan filter LIKE (teks, AND antar kolom) untuk tiap kolom aktif dari {@see self::filter()}.
     * PostgreSQL: LIKE peka huruf besar/kecil, jadi pakai CAST + ILIKE.
     *
     * @param  array<string, string>  $filterAktif
     * @param  array<string, string>  $kolomQuery
     */
    public static function terapkanFilter(Builder $query, array $filterAktif, array $kolomQuery): void
    {
        $pgsql = $query->getConnection()->getDriverName() === 'pgsql';

        foreach ($filterAktif as $kolom => $nilai) {
            $ekspresi = $kolomQuery[$kolom];

            $pgsql
                ? $query->whereRaw('CAST('.$query->getGrammar()->wrap($ekspresi).' AS TEXT) ILIKE ?', ["%{$nilai}%"])
                : $query->where($ekspresi, 'like', "%{$nilai}%");
        }
    }
}
