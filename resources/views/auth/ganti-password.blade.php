@extends('layouts.auth')

@section('title', 'Ganti Password')

@section('content')
    <x-ui.molecules.card>
        <x-slot:header>Ganti Password</x-slot:header>

        <form method="POST" action="{{ route('password.update') }}" class="space-y-3">
            @csrf
            @method('PUT')

            @if ($wajib)
                <x-ui.molecules.alert variant="info">
                    Anda masuk dengan password awal dari admin. Buat password baru sebelum melanjutkan.
                </x-ui.molecules.alert>
            @endif

            @if ($errors->any())
                <x-ui.molecules.alert variant="danger" role="alert">{{ $errors->first() }}</x-ui.molecules.alert>
            @endif

            @unless ($wajib)
                <div>
                    <x-ui.atoms.label for="password_lama">Password lama</x-ui.atoms.label>
                    <x-ui.molecules.password-input id="password_lama" name="password_lama" autocomplete="current-password" required />
                </div>
            @endunless

            <div>
                <x-ui.atoms.label for="password">Password baru (minimal 8 karakter)</x-ui.atoms.label>
                <x-ui.molecules.password-input id="password" name="password" autocomplete="new-password" required />
            </div>

            <div>
                <x-ui.atoms.label for="password_confirmation">Ulangi password baru</x-ui.atoms.label>
                <x-ui.molecules.password-input id="password_confirmation" name="password_confirmation" autocomplete="new-password" required />
            </div>

            <div class="flex items-center justify-between gap-2">
                <x-ui.atoms.button type="submit">Simpan password</x-ui.atoms.button>
                @unless ($wajib)
                    <x-ui.atoms.button tag="a" variant="light" href="{{ url('/home') }}">Batal</x-ui.atoms.button>
                @endunless
            </div>
        </form>

        <x-slot:footer>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <x-ui.atoms.button type="submit" variant="outline-secondary">Keluar</x-ui.atoms.button>
            </form>
        </x-slot:footer>
    </x-ui.molecules.card>
@endsection
