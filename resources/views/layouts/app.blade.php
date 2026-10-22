<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/all.min.css') }}">
    <style>
        body { background: #edf2f9; font-size: .875rem; }
        .sidebar { width: 260px; height: 100vh; position: fixed; top: 0; left: 0; overflow-y: auto; background: #fff; border-right: 1px solid #e3e6ed; padding: 1rem .75rem; }
        .sidebar .brand { font-weight: 700; font-size: 1.1rem; color: #2c7be5; padding: 0 .5rem 1rem; }
        .sidebar .nav-link { color: #5e6e82; padding: .35rem .5rem; border-radius: .25rem; }
        .sidebar .nav-link:hover { color: #232e3c; background: #f5f7fa; }
        .sidebar .nav-link.active { color: #2c7be5; font-weight: 600; }
        .sidebar .nav-link-icon { width: 1.5rem; display: inline-block; text-align: center; margin-right: .25rem; }
        .sidebar .nav .nav .nav-link { padding-left: 2.25rem; }
        .sidebar .section-label { font-size: .7rem; text-transform: uppercase; color: #9da9bb; font-weight: 600; margin: 1rem .5rem .25rem; }
        .sidebar [data-bs-toggle="collapse"]::after { content: "\f107"; font-family: "Font Awesome 5 Free"; font-weight: 900; float: right; }
        main { margin-left: 260px; padding: 1.5rem; }
        @media (max-width: 991px) { .sidebar { position: static; width: 100%; height: auto; } main { margin-left: 0; } }
    </style>
    @stack('head')
</head>
<body>
    @include('layouts.sidebar')
    <main>
        @yield('content')
    </main>
    <script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
    @stack('scripts')
</body>
</html>
