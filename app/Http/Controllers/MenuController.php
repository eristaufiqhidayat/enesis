<?php

namespace App\Http\Controllers;

use App\Support\Akses\Akses;
use App\Support\Menu\SumberBertanggal;
use App\Support\Mobile\Pengaturan;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class MenuController extends Controller
{
    // Operator yang boleh dipakai untuk filter kolom angka (whitelist, tidak pernah dari mentah input).
    private const OPERATOR_FILTER = ['=', '!=', '<', '<=', '>', '>='];

    public function show(Request $request, string $slug)
    {
        $page = config("marketama.pages.{$slug}");
        abort_unless($page, 404);

        $search = trim((string) $request->query('q'));
        // Tabel dikelompokkan per grup tab (tab_key, tab_label, tables). Halaman tanpa tab: tiap tabel jadi grup sendiri.
        // Filter tanggal dan KPI diatur per tabel; tanggal query string dipakai bersama oleh tabel yang punya filter.
        $konfigDefault = ['date_filter_column' => null, 'kpi' => null];
        $konfigTabel = [];

        foreach ($page['tables'] as $item) {
            $grup = is_array($item) && isset($item['tables'])
                ? $item
                : ['tab_key' => $item, 'tab_label' => null, 'tables' => [$item]];

            foreach ($grup['tables'] as $tabel) {
                $konfigTabel[] = (is_array($tabel) ? $tabel : ['table' => $tabel]) + $konfigDefault
                    + ['tab_key' => $grup['tab_key'], 'tab_label' => $grup['tab_label']];
            }
        }
        // Tab aktif dari query ?tab=, harus salah satu tab_key yang ada di config. Jika kosong/tidak valid, pakai tab pertama.
        $tabKeys = array_values(array_unique(array_column($konfigTabel, 'tab_key')));
        $tab = $request->query('tab');
        $activeTab = in_array($tab, $tabKeys, true) ? $tab : ($tabKeys[0] ?? null);

        // Halaman bertab: hanya tabel grup aktif yang perlu di-query (tabel grup lain tidak ditampilkan,
        // lihat Show.vue), supaya buka satu tab tidak ikut menjalankan query tabel tab lain.
        if ($page['tabs'] ?? false) {
            $konfigTabel = array_values(array_filter(
                $konfigTabel,
                fn ($konfig) => $konfig['tab_key'] === $activeTab
            ));
        }

        $hasDateFilter = collect($konfigTabel)->contains(fn ($konfig) => $konfig['date_filter_column'] !== null);
        $tanggal = $hasDateFilter ? $this->resolveTanggal($request) : null;
        // Filter depo / koordinator untuk sumber bertanggal (mis. Sales Checkin Outlet Pagi).
        $filter = ['depo' => $request->integer('depo') ?: null, 'koordinator' => $request->integer('koordinator') ?: null];

        return Inertia::render('Menu/Show', [
            'slug' => $slug,
            'page' => [
                ...$page,
                'same_as_title' => $page['same_as'] ? config("marketama.pages.{$page['same_as']}.title") : null,
            ],
            // Keterangan singkat di PageHeader: sumber data menu ini (lihat STYLE_GUIDE.md § PageHeader).
            'subtitle' => $page['same_as']
                ? 'Sama dengan menu '.config("marketama.pages.{$page['same_as']}.title").'.'
                : (Akses::terhubungMotoris($slug)
                    ? 'Data diambil langsung dari tabel aplikasi motoris.'
                    : 'Belum terhubung ke data aplikasi motoris — menu placeholder struktur sidebar Marketama.'),
            // Query tabel yang berat ditunda ke request susulan (lihat Menu/Show.vue), supaya halaman langsung tampil.
            'tables' => Inertia::defer(fn () => $this->muatTabel($request, $slug, $konfigTabel, $search, $tanggal, $filter), 'tables'),
            'activeTab' => $activeTab,
            'search' => $search,
            'tanggal' => $tanggal,
            'hasDateFilter' => $hasDateFilter,
            'filter' => $filter,
        ]);
    }

    /**
     * Query tiap tabel di konfigurasi halaman (termasuk pencarian, filter tanggal, dan KPI).
     */
    private function muatTabel(Request $request, string $slug, array $konfigTabel, string $search, ?string $tanggal, array $filter): array
    {
        $tables = [];

        foreach ($konfigTabel as $konfig) {
            $table = $konfig['table'];
            // Tabel dengan 'sumber' (kelas SumberTabel) di-query lewat kelas itu, mis. butuh join ke master.
            $kelasSumber = config("marketama.tables.{$table}.sumber");
            $sumber = $kelasSumber ? app($kelasSumber) : null;

            if (! Schema::hasTable($sumber?->tabelUtama() ?? $table)) {
                continue;
            }

            $dateColumn = $konfig['date_filter_column'];
            $columns = config("marketama.tables.{$table}.columns", []);
            $kolomCari = $sumber ? $sumber->kolomCari() : array_keys($columns);
            // Sort dan filter kolom hanya untuk tabel tanpa sumber; kolom di luar whitelist ini diabaikan.
            $skema = $sumber ? [] : $this->tipeKolom($table);
            $sortable = $sumber ? [] : $this->kolomBisaDipakai($table, 'sortable', $columns, $skema);
            $sort = $this->resolveSort($request, $table, $sortable);
            $filterable = $sumber ? [] : $this->kolomBisaDipakai($table, 'filterable', $columns, $skema);
            // Kolom angka difilter dengan operator matematika (=, <, >, dst.), kolom lain dengan pencocokan teks.
            $filterAngka = array_values(array_filter($filterable, fn ($kolom) => $this->bertipeAngka($skema[$kolom] ?? '')));
            // Kolom tanggal (tipe date/datetime) difilter dengan datepicker dan operator yang sama.
            $filterTanggal = array_values(array_filter($filterable, fn ($kolom) => $this->bertipeTanggal($skema[$kolom] ?? '')));
            $filterAktif = $this->resolveFilter($request, $table, $filterable, $filterAngka, $filterTanggal);
            $operator = $this->resolveOperator($request, $table, array_merge($filterAngka, $filterTanggal));
            // Sumber bertanggal: baris dihitung untuk tanggal terpilih (+ filter depo/koordinator), lengkap dengan KPI.
            $bertanggal = $sumber instanceof SumberBertanggal && $dateColumn !== null;
            $kpi = $bertanggal ? $sumber->kpi($request->user(), $tanggal, $filter) : null;

            if ($bertanggal) {
                $query = $sumber->queryTanggal($request->user(), $tanggal, $filter);
            } else {
                $query = $sumber ? $sumber->query($request->user()) : DB::table($table);

                if ($dateColumn !== null) {
                    $query->where($dateColumn, $tanggal);
                }
            }

            // PostgreSQL: LIKE hanya untuk teks dan peka huruf besar/kecil, jadi pakai CAST + ILIKE.
            $pgsql = DB::getDriverName() === 'pgsql';

            if ($search !== '') {
                $query->where(function ($q) use ($kolomCari, $search, $pgsql) {
                    foreach ($kolomCari as $column) {
                        $pgsql
                            ? $q->orWhereRaw('CAST('.$q->getGrammar()->wrap($column).' AS TEXT) ILIKE ?', ["%{$search}%"])
                            : $q->orWhere($column, 'like', "%{$search}%");
                    }
                });
            }

            // Filter per kolom: tiap kolom yang diisi wajib cocok (AND), dan tetap digabung dengan pencarian global.
            foreach ($filterAktif as $kolom => $nilai) {
                if (in_array($kolom, $filterAngka, true)) {
                    $query->where($kolom, $operator[$kolom], $nilai);

                    continue;
                }

                if (in_array($kolom, $filterTanggal, true)) {
                    $this->terapkanFilterTanggal($query, $kolom, $operator[$kolom], $nilai);

                    continue;
                }

                $pgsql
                    ? $query->whereRaw('CAST('.$query->getGrammar()->wrap($kolom).' AS TEXT) ILIKE ?', ["%{$nilai}%"])
                    : $query->where($kolom, 'like', "%{$nilai}%");
            }

            if (! $sumber) {
                if ($sort) {
                    $query->orderBy($sort['kolom'], $sort['arah']);
                }

                // id sebagai pengurut kedua supaya urutan antarhalaman stabil.
                $query->orderBy('id');
            }

            $rows = $query->paginate(50, ['*'], "page_{$table}")->withQueryString();

            if ($sumber) {
                $rows->through(fn ($baris) => $sumber->ubahBaris($baris));
            }

            // Kolom "No" yang tidak disimpan di tabel: nomor urut lintas halaman.
            if (array_key_exists('no', $columns)) {
                $urut = (int) $rows->firstItem();
                $rows->through(function ($baris) use (&$urut) {
                    $baris->no ??= $urut;
                    $urut++;

                    return $baris;
                });
            }

            $tables[] = [
                'name' => $table,
                'title' => config("marketama.tables.{$table}.title"),
                'tab_key' => $konfig['tab_key'],
                'tab_label' => $konfig['tab_label'] ?? config("marketama.tables.{$table}.title"),
                'date_filter' => $dateColumn !== null,
                'columns' => $columns,
                'sortable_columns' => $sortable,
                'sort' => $sort,
                'filterable_columns' => $filterable,
                'filter_kolom' => $filterAktif,
                'filter_angka' => $filterAngka,
                'filter_tanggal' => $filterTanggal,
                'filter_operator' => $operator,
                'rows' => [
                    'data' => $rows->items(),
                    'total' => $rows->total(),
                    'links' => $rows->onEachSide(1)->linkCollection()->toArray(),
                ],
                'kpi' => $kpi,
                'keterangan' => $bertanggal ? $sumber->keterangan() : null,
                'filter_pilihan' => $bertanggal ? $sumber->pilihanFilter($request->user()) : null,
                // Aksi per baris (mis. 'verifikasi_outlet_baru'), hanya untuk akun yang punya hak 'aksi_hak'
                // (bawaan: ubah) di menu ini.
                'aksi' => Akses::bisa($request->user(), $slug, config("marketama.tables.{$table}.aksi_hak", 'ubah'))
                    ? config("marketama.tables.{$table}.aksi") : null,
            ];
        }

        return $tables;
    }

    /**
     * Kolom yang boleh di-sort atau difilter: key $kunci ('sortable' / 'filterable') di config bila diisi (boleh [] untuk mematikan).
     * Bawaan: semua kolom di 'columns' yang ada di tabel database, kecuali 'no' (nomor urut dihitung di controller).
     */
    private function kolomBisaDipakai(string $table, string $kunci, array $columns, array $skema): array
    {
        $daftar = config("marketama.tables.{$table}.{$kunci}");

        if ($daftar !== null) {
            return $daftar;
        }

        return array_values(array_filter(
            array_keys($columns),
            fn ($kolom) => $kolom !== 'no' && array_key_exists($kolom, $skema)
        ));
    }

    /**
     * Tipe kolom database per nama kolom, mis. ['kode' => 'varchar'].
     */
    private function tipeKolom(string $table): array
    {
        return collect(Schema::getColumns($table))->pluck('type_name', 'name')->all();
    }

    /**
     * Tipe angka (integer, bigint, decimal, numeric, float, double, real) lintas driver database.
     */
    private function bertipeAngka(string $tipe): bool
    {
        return preg_match('/^(tiny|small|medium|big)?int\d*$|^integer$|^(numeric|decimal|float\d*|double( precision)?|real)$/i', $tipe) === 1;
    }

    /**
     * Tipe tanggal (date, datetime, timestamp) lintas driver database.
     */
    private function bertipeTanggal(string $tipe): bool
    {
        return preg_match('/^(date|datetime|timestamp|timestamptz)$/i', $tipe) === 1;
    }

    /**
     * Filter kolom tanggal per hari. '=' hari itu, '<' sebelum hari itu, '>=' mulai hari itu, dan seterusnya.
     * Batas memakai Y-m-d, yang bisa dibandingkan dengan kolom date maupun datetime di semua driver.
     */
    private function terapkanFilterTanggal(Builder $query, string $kolom, string $op, string $tanggal): void
    {
        $hari = Carbon::createFromFormat('Y-m-d', $tanggal);
        $awal = $hari->format('Y-m-d');
        $berikut = $hari->copy()->addDay()->format('Y-m-d');

        match ($op) {
            '=' => $query->where($kolom, '>=', $awal)->where($kolom, '<', $berikut),
            '!=' => $query->where(fn ($q) => $q->where($kolom, '<', $awal)->orWhere($kolom, '>=', $berikut)),
            '<' => $query->where($kolom, '<', $awal),
            '<=' => $query->where($kolom, '<', $berikut),
            '>' => $query->where($kolom, '>=', $berikut),
            '>=' => $query->where($kolom, '>=', $awal),
        };
    }

    /**
     * Tanggal dengan format Y-m-d yang valid (bukan tanggal yang digeser otomatis, mis. 2026-02-31).
     */
    private function tanggalValid(string $tanggal): bool
    {
        return Carbon::canBeCreatedFromFormat($tanggal, 'Y-m-d')
            && Carbon::createFromFormat('Y-m-d', $tanggal)->format('Y-m-d') === $tanggal;
    }

    /**
     * Filter per kolom dari query ?filter[<tabel>][<kolom>]=nilai. Hanya kolom di whitelist dengan nilai tidak kosong yang dipakai.
     * Kolom angka hanya menerima angka, kolom tanggal hanya menerima tanggal Y-m-d yang valid.
     */
    private function resolveFilter(Request $request, string $table, array $filterable, array $filterAngka, array $filterTanggal): array
    {
        $input = $request->input("filter.{$table}");

        if (! is_array($input)) {
            return [];
        }

        $aktif = [];

        foreach ($filterable as $kolom) {
            $nilai = $input[$kolom] ?? null;

            if (! is_string($nilai) || trim($nilai) === '') {
                continue;
            }

            if (in_array($kolom, $filterAngka, true)) {
                if (is_numeric(trim($nilai))) {
                    $aktif[$kolom] = trim($nilai);
                }

                continue;
            }

            if (in_array($kolom, $filterTanggal, true)) {
                if ($this->tanggalValid(trim($nilai))) {
                    $aktif[$kolom] = trim($nilai);
                }

                continue;
            }

            $aktif[$kolom] = trim($nilai);
        }

        return $aktif;
    }

    /**
     * Operator per kolom angka dari query ?operator[<tabel>][<kolom>]=<op>. Bawaan '=' bila kosong atau tidak dikenal.
     */
    private function resolveOperator(Request $request, string $table, array $filterAngka): array
    {
        $input = $request->input("operator.{$table}");
        $operator = [];

        foreach ($filterAngka as $kolom) {
            $op = is_array($input) ? ($input[$kolom] ?? null) : null;

            $operator[$kolom] = in_array($op, self::OPERATOR_FILTER, true) ? $op : '=';
        }

        return $operator;
    }

    /**
     * Urutan tabel dari query ?sort[<tabel>]=<kolom>&arah[<tabel>]=asc|desc. Null jika kolom kosong atau tidak ada di whitelist.
     */
    private function resolveSort(Request $request, string $table, array $sortable): ?array
    {
        $kolom = $request->input("sort.{$table}");

        if (! is_string($kolom) || ! in_array($kolom, $sortable, true)) {
            return null;
        }

        return ['kolom' => $kolom, 'arah' => $request->input("arah.{$table}") === 'desc' ? 'desc' : 'asc'];
    }

    /**
     * Tanggal filter dari query string (Y-m-d). Hari ini jika kosong atau tidak valid.
     */
    private function resolveTanggal(Request $request): string
    {
        $input = (string) $request->query('tanggal');

        // Hari ini menurut zona waktu operasional (WIB), bukan UTC server.
        return $this->tanggalValid($input) ? $input : Pengaturan::sekarang()->toDateString();
    }
}
