<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') · {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/all.min.css') }}">
    @vite(['resources/css/app.css'])
    @stack('head')
</head>
<body>
    @include('layouts.sidebar')
    <main>
        @include('layouts.topbar')
        @yield('content')
    </main>
    <script>
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
