@extends('layouts.auth')

@section('title', 'Masuk')

@section('content')
    <x-ui.molecules.card>
        <x-slot:header>Masuk</x-slot:header>

        <form method="POST" action="{{ url('/login') }}" class="space-y-3">
            @csrf

            @if ($errors->any())
                <x-ui.molecules.alert variant="danger" role="alert">{{ $errors->first() }}</x-ui.molecules.alert>
            @endif

            <div>
                <x-ui.atoms.label for="username">Username</x-ui.atoms.label>
                <x-ui.atoms.input id="username" name="username" value="{{ old('username') }}" autocomplete="username" autofocus required />
            </div>

            <div>
                <x-ui.atoms.label for="password">Password</x-ui.atoms.label>
                <x-ui.molecules.password-input id="password" name="password" autocomplete="current-password" required />
            </div>

            <label class="form-check">
                <x-ui.atoms.checkbox name="ingat" value="1" :checked="old('ingat')" />
                <span>Ingat saya</span>
            </label>

            <x-ui.atoms.button type="submit" class="w-full">Masuk</x-ui.atoms.button>

            <p class="text-center text-xs text-gray-500">Lupa password? Hubungi admin.</p>
        </form>

        @if (config('auth.demo_login'))
            <div class="demo-login">
                <div class="demo-login-title"><strong>Demo masuk cepat</strong> · mode uji coba, tanpa password</div>
                <div class="demo-login-buttons">
                    @foreach (config('auth.demo_peran') as $peran => $label)
                        <form method="POST" action="{{ route('login.demo', $peran) }}">
                            @csrf
                            <x-ui.atoms.button type="submit" variant="outline-secondary">{{ $label }}</x-ui.atoms.button>
                        </form>
                    @endforeach
                </div>
            </div>
        @endif
    </x-ui.molecules.card>
@endsection
