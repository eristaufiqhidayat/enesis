<?php

namespace App\Http\Middleware;

use App\Support\Akses\Akses;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            // Sidebar hanya berisi menu yang boleh dilihat role akun.
            'menu' => fn () => Akses::menu($request->user()),
            // Data pencarian sidebar: menu() yang diratakan + entri per tab untuk halaman bertab.
            'searchMenu' => fn () => Akses::menuPencarian($request->user()),
            'currentSlug' => $request->route('slug') ?? $request->path(),
            'appName' => config('app.name'),
            // Pesan hasil aksi (redirect ->with('sukses'|'galat')), ditampilkan di atas halaman.
            'flash' => fn () => [
                'sukses' => $request->session()->get('sukses'),
                'galat' => $request->session()->get('galat'),
            ],
            // Hanya data yang perlu ditampilkan di menu user (footer sidebar), bukan seluruh kolom users.
            'auth' => [
                'user' => fn () => ($user = $request->user())
                    ? $user->only(['name', 'username', 'kategori']) + ['role' => Akses::role($user)?->nama]
                    : null,
            ],
        ];
    }
}
