<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') · {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/all.min.css') }}">
    @vite(['resources/css/app.css'])
    @stack('head')
</head>
<body>
    <x-ui.organisms.sidebar />
    <main>
        <x-ui.organisms.topbar :breadcrumb-slug="trim($__env->yieldContent('breadcrumb_slug')) ?: null" />
        @yield('content')
    </main>
    <script>
        // Halaman Blade (bukan Inertia) full reload tiap navigasi, jadi scroll
        // sidebar disimpan di sessionStorage supaya tidak kembali ke atas tiap pindah menu.
        (() => {
            const SCROLL_KEY = 'sidebar-scroll-top';
            const sidebar = document.querySelector('.sidebar');
            if (!sidebar) return;

            const saved = sessionStorage.getItem(SCROLL_KEY);
            if (saved) sidebar.scrollTop = parseInt(saved, 10) || 0;

            sidebar.addEventListener('scroll', () => {
                sessionStorage.setItem(SCROLL_KEY, sidebar.scrollTop);
            });
        })();

        // Pengganti ringan Bootstrap collapse/dropdown (tanpa Bootstrap JS).
        document.querySelectorAll('[data-collapse-toggle]').forEach((toggle) => {
            const target = document.getElementById(toggle.dataset.collapseToggle);
            if (!target) return;
            toggle.addEventListener('click', (e) => {
                e.preventDefault();
                target.classList.toggle('hidden');
                toggle.classList.toggle('is-open', !target.classList.contains('hidden'));
            });
        });

        document.querySelectorAll('[data-dropdown-toggle]').forEach((toggle) => {
            const target = document.getElementById(toggle.dataset.dropdownToggle);
            if (!target) return;
            toggle.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                target.classList.toggle('hidden');
            });
            document.addEventListener('click', (e) => {
                if (!target.contains(e.target) && e.target !== toggle) target.classList.add('hidden');
            });
        });
    </script>
    @stack('scripts')
</body>
</html>
