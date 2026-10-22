<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') · {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/all.min.css') }}">
    <style>
        body { background: #edf2f9; font-size: .875rem; }
        /* Warna sidebar sama dengan Marketama (tema Falcon "navbar-vibrant"). */
        .sidebar { width: 260px; height: 100vh; position: fixed; top: 0; left: 0; overflow-y: auto; background-image: linear-gradient(-45deg, rgba(0, 160, 255, .86), #0048a2); padding: 1rem .75rem; scrollbar-color: rgba(255, 255, 255, .3) transparent; }
        .sidebar .brand { font-weight: 700; font-size: 1.1rem; color: #fff; padding: 0 .5rem 1rem; }
        .sidebar .nav-link { color: rgba(255, 255, 255, .75); padding: .35rem .5rem; border-radius: .25rem; }
        .sidebar .nav-link:hover, .sidebar .nav-link:focus { color: #fff; background: rgba(255, 255, 255, .08); }
        .sidebar .nav-link.active { color: #fff; font-weight: 600; background: rgba(255, 255, 255, .12); }
        .sidebar .nav-link-icon { width: 1.5rem; display: inline-block; text-align: center; margin-right: .25rem; }
        .sidebar .nav .nav .nav-link { padding-left: 2.25rem; }
        .sidebar .section-label { font-size: .7rem; text-transform: uppercase; color: rgba(255, 255, 255, .4); font-weight: 600; margin: 1rem .5rem .25rem; border-top: 1px solid rgba(255, 255, 255, .2); padding-top: .75rem; }
        .sidebar .section-label:first-child { border-top: 0; padding-top: 0; }
        .sidebar [data-bs-toggle="collapse"]::after { content: "\f107"; font-family: "Font Awesome 5 Free"; font-weight: 900; float: right; }
        main { margin-left: 260px; padding: 1rem 1.5rem 1.5rem; }
        .topbar-icon { display: inline-flex; width: 2.25rem; height: 2.25rem; align-items: center; justify-content: center; border-radius: 50%; color: #5e6e82; font-size: 1.05rem; }
        .topbar-icon:hover { background: #fff; color: #2c7be5; }
        @media (max-width: 991px) { .sidebar { position: static; width: 100%; height: auto; } main { margin-left: 0; } }
    </style>
    @stack('head')
</head>
<body>
    @include('layouts.sidebar')
    <main>
        @include('layouts.topbar')
        @yield('content')
    </main>
    <script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
    <script>document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => new bootstrap.Tooltip(el));</script>
    @stack('scripts')
</body>
</html>
