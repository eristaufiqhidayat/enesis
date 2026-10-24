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
                <x-ui.atoms.input type="password" id="password" name="password" autocomplete="current-password" required />
            </div>

            <x-ui.atoms.button type="submit" class="w-full">Masuk</x-ui.atoms.button>
        </form>
    </x-ui.molecules.card>
@endsection
