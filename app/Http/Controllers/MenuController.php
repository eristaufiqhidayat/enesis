<?php

namespace App\Http\Controllers;

use App\Support\Akses\Akses;
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
            $kpi = $dateColumn !== null ? match ($konfig['kpi']) {
                'checkin_pagi' => $this->kpiCheckinPagi($tanggal),
                default => null,
            } : null;
            $columns = config("marketama.tables.{$table}.columns", []);
            $query = $sumber ? $sumber->query($request->user()) : DB::table($table);
            $kolomCari = $sumber ? $sumber->kolomCari() : array_keys($columns);

            if ($dateColumn !== null) {
                $query->where($dateColumn, $tanggal);
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
                // Aksi per baris (mis. 'verifikasi_outlet_baru'), hanya untuk akun yang punya hak ubah menu ini.
                'aksi' => Akses::bisa($request->user(), $slug, 'ubah') ? config("marketama.tables.{$table}.aksi") : null,
            ];
        }

        return Inertia::render('Menu/Show', [
            'slug' => $slug,
            'page' => [
                ...$page,
                'same_as_title' => $page['same_as'] ? config("marketama.pages.{$page['same_as']}.title") : null,
            ],
            'tables' => $tables,
            'activeTab' => $activeTab,
            'search' => $search,
            'tanggal' => $tanggal,
            'hasDateFilter' => $hasDateFilter,
        ]);
    }

    /**
     * Rekap KPI check-in pagi untuk satu tanggal. Dihitung dari COUNT, bukan dari baris halaman yang dipaginasi.
     */
    private function kpiCheckinPagi(string $tanggal): array
    {
        $checkin = DB::table('checkin_pagi')
            ->where('tanggal_data', $tanggal)
            ->selectRaw("COUNT(*) AS total, SUM(CASE WHEN checkin_pertama_hari_ini IS NOT NULL AND checkin_pertama_hari_ini <> '' THEN 1 ELSE 0 END) AS sudah_absen")
            ->first();
        $sudahAbsen = (int) $checkin->sudah_absen;

        return [
            'sudah_absen' => $sudahAbsen,
            'belum_absen' => (int) $checkin->total - $sudahAbsen,
            'terlambat' => DB::table('absensi_sales_terlambat')->where('tanggal', $tanggal)->count(),
        ];
    }

    /**
     * Tanggal filter dari query string (Y-m-d). Hari ini jika kosong atau tidak valid.
     */
    private function resolveTanggal(Request $request): string
    {
        $input = (string) $request->query('tanggal');

        $valid = Carbon::canBeCreatedFromFormat($input, 'Y-m-d')
            && Carbon::createFromFormat('Y-m-d', $input)->format('Y-m-d') === $input;

        return $valid ? $input : Carbon::today()->toDateString();
    }
}
