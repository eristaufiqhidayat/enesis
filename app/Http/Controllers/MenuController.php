<?php

namespace App\Http\Controllers;

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
        $dateColumn = $page['date_filter_column'] ?? null;
        $tanggal = $dateColumn !== null ? $this->resolveTanggal($request) : null;
        $kpi = $tanggal !== null ? match ($page['kpi'] ?? null) {
            'checkin_pagi' => $this->kpiCheckinPagi($tanggal),
            default => null,
        } : null;
        $tables = [];

        foreach ($page['tables'] as $table) {
            // "sumber" = [Kelas, 'metode'] yang mengembalikan query (mis. menu User Sales dari tabel users);
            // tanpa itu, tabel dibaca apa adanya.
            $sumber = config("marketama.tables.{$table}.sumber");

            if (! $sumber && ! Schema::hasTable($table)) {
                continue;
            }

            $columns = config("marketama.tables.{$table}.columns", []);
            $query = $sumber ? call_user_func($sumber) : DB::table($table);

            if ($dateColumn !== null) {
                $query->where($dateColumn, $tanggal);
            }

            if ($search !== '') {
                // PostgreSQL: LIKE hanya untuk teks dan peka huruf besar/kecil, jadi pakai CAST + ILIKE.
                $pgsql = DB::getDriverName() === 'pgsql';
                $query->where(function ($q) use ($columns, $search, $pgsql) {
                    foreach (array_keys($columns) as $column) {
                        $pgsql
                            ? $q->orWhereRaw('CAST('.$q->getGrammar()->wrap($column).' AS TEXT) ILIKE ?', ["%{$search}%"])
                            : $q->orWhere($column, 'like', "%{$search}%");
                    }
                });
            }

            $rows = $query->orderBy('id')->paginate(50, ['*'], "page_{$table}")->withQueryString();

            $tables[] = [
                'name' => $table,
                'title' => config("marketama.tables.{$table}.title"),
                'columns' => $columns,
                'rows' => [
                    'data' => $rows->items(),
                    'total' => $rows->total(),
                    'links' => $rows->onEachSide(1)->linkCollection()->toArray(),
                ],
            ];
        }

        return Inertia::render('Menu/Show', [
            'slug' => $slug,
            'page' => [
                ...$page,
                'same_as_title' => $page['same_as'] ? config("marketama.pages.{$page['same_as']}.title") : null,
            ],
            'tables' => $tables,
            'search' => $search,
            'tanggal' => $tanggal,
            'hasDateFilter' => $dateColumn !== null,
            'kpi' => $kpi,
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
