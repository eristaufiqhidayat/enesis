@extends('layouts.app')

@section('title', $judul)
@section('breadcrumb_slug', $menu)

@section('content')
    <x-ui.molecules.page-header :title="$depo->nama">
        @if ($depo->distributor) <x-slot:subtitle>{{ $depo->distributor }}</x-slot:subtitle> @endif
        <x-slot:actions>
            <x-ui.atoms.button tag="a" variant="outline-secondary" href="{{ route('summary-depo', $filter) }}">Kembali ke Summary All Depo</x-ui.atoms.button>
        </x-slot:actions>
    </x-ui.molecules.page-header>

    <x-ui.molecules.card>
        @include('motoris._summary-detail')
    </x-ui.molecules.card>
@endsection
