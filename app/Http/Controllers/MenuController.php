<?php

namespace App\Http\Controllers;

use App\Support\Akses\Akses;
use App\Support\Menu\SumberBertanggal;
use App\Support\Mobile\Pengaturan;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class MenuController extends Controller
{
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

            if ($search !== '') {
                // PostgreSQL: LIKE hanya untuk teks dan peka huruf besar/kecil, jadi pakai CAST + ILIKE.
                $pgsql = DB::getDriverName() === 'pgsql';
                $query->where(function ($q) use ($kolomCari, $search, $pgsql) {
                    foreach ($kolomCari as $column) {
                        $pgsql
                            ? $q->orWhereRaw('CAST('.$q->getGrammar()->wrap($column).' AS TEXT) ILIKE ?', ["%{$search}%"])
                            : $q->orWhere($column, 'like', "%{$search}%");
                    }
                });
            }

            $rows = ($sumber ? $query : $query->orderBy('id'))->paginate(50, ['*'], "page_{$table}")->withQueryString();

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
     * Tanggal filter dari query string (Y-m-d). Hari ini jika kosong atau tidak valid.
     */
    private function resolveTanggal(Request $request): string
    {
        $input = (string) $request->query('tanggal');

        $valid = Carbon::canBeCreatedFromFormat($input, 'Y-m-d')
            && Carbon::createFromFormat('Y-m-d', $input)->format('Y-m-d') === $input;

        // Hari ini menurut zona waktu operasional (WIB), bukan UTC server.
        return $valid ? $input : Pengaturan::sekarang()->toDateString();
    }
}
