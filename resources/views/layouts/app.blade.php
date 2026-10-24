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

        // Collapse seluruh sidebar jadi strip ikon-saja: state terpisah dari
        // collapse per grup (key localStorage sendiri) supaya dua preferensi
        // ini tidak saling menimpa.
        (() => {
            const STORAGE_KEY = 'sidebar-collapsed-full';

            if (localStorage.getItem(STORAGE_KEY) === '1') {
                document.body.classList.add('sidebar-collapsed');
            }

            const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
            if (!sidebarToggle) return;

            sidebarToggle.addEventListener('click', () => {
                const collapsed = document.body.classList.toggle('sidebar-collapsed');
                try {
                    localStorage.setItem(STORAGE_KEY, collapsed ? '1' : '0');
                } catch {
                    // localStorage tidak tersedia (mode privat dll.), state collapse tidak persist.
                }
            });
        })();

        // Pengganti ringan Bootstrap collapse/dropdown (tanpa Bootstrap JS).
        document.querySelectorAll('[data-collapse-toggle]').forEach((toggle) => {
            const target = document.getElementById(toggle.dataset.collapseToggle);
            if (!target) return;
            toggle.addEventListener('click', (e) => {
                if (document.body.classList.contains('sidebar-collapsed')) {
                    document.body.classList.remove('sidebar-collapsed');
                    try {
                        localStorage.setItem('sidebar-collapsed-full', '0');
                    } catch {
                        // localStorage tidak tersedia (mode privat dll.), state collapse tidak persist.
                    }
                }
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

        // Modal sederhana (.modal-container[data-modal]): [data-modal-open="id"] membuka, [data-modal-dismiss] / klik latar menutup,
        // ?buka=id di URL membuka modal saat halaman dimuat.
        (() => {
            const buka = (id) => document.getElementById(id)?.classList.remove('hidden');
            document.querySelectorAll('[data-modal-open]').forEach((el) => el.addEventListener('click', (e) => {
                e.preventDefault();
                buka(el.dataset.modalOpen);
            }));
            document.querySelectorAll('[data-modal] [data-modal-dismiss]').forEach((el) => el.addEventListener('click', (e) => {
                e.preventDefault();
                el.closest('.modal-container')?.classList.add('hidden');
            }));
            document.querySelectorAll('.modal-container[data-modal]').forEach((m) => m.addEventListener('click', (e) => {
                if (e.target === m) m.classList.add('hidden');
            }));
            const awal = new URLSearchParams(location.search).get('buka');
            if (awal && /^[a-z0-9-]+$/.test(awal)) buka(awal);
        })();
    </script>
    @stack('scripts')
</body>
</html>
