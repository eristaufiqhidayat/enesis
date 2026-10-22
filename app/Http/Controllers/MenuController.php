<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MenuController extends Controller
{
    public function show(Request $request, string $slug)
    {
        $page = config("marketama.pages.{$slug}");
        abort_unless($page, 404);

        $search = trim((string) $request->query('q'));
        $tables = [];

        foreach ($page['tables'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $columns = config("marketama.tables.{$table}.columns", []);
            $query = DB::table($table);

            if ($search !== '') {
                $query->where(function ($q) use ($columns, $search) {
                    foreach (array_keys($columns) as $column) {
                        $q->orWhere($column, 'like', "%{$search}%");
                    }
                });
            }

            $tables[] = [
                'name' => $table,
                'title' => config("marketama.tables.{$table}.title"),
                'columns' => $columns,
                'rows' => $query->orderBy('id')->paginate(50, ['*'], "page_{$table}")->withQueryString(),
            ];
        }

        return view('menu.show', [
            'slug' => $slug,
            'page' => $page,
            'tables' => $tables,
            'search' => $search,
        ]);
    }
}
