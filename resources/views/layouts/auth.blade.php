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
</head>
<body>
    {{-- Halaman tamu: tanpa sidebar/topbar. --}}
    <div class="auth-page">
        <div class="auth-box">
            <div class="auth-brand">{{ config('app.name') }}</div>
            @yield('content')
        </div>
    </div>
    @stack('scripts')
</body>
</html>
