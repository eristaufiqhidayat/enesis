@php($tgl = fn ($t) => \Illuminate\Support\Carbon::parse($t)->locale('id')->translatedFormat('d F Y'))
@extends('layouts.app')

@section('title', $judul)
@section('breadcrumb_slug', $menu)

@section('content')
    <x-ui.molecules.page-header :title="$judul">
        <x-slot:subtitle>Periode {{ $summary->dari === $summary->sampai ? $tgl($summary->dari) : $tgl($summary->dari).' – '.$tgl($summary->sampai) }}</x-slot:subtitle>
        <x-slot:actions>
            <x-ui.atoms.button tag="a" variant="outline-secondary" href="{{ route('summary-depo', ['date_from' => $filter['date_from'], 'date_to' => $filter['date_to']]) }}">Kembali ke Summary All Depo</x-ui.atoms.button>
        </x-slot:actions>
    </x-ui.molecules.page-header>

    <x-ui.molecules.card>
        @include('motoris._summary-penjualan')
    </x-ui.molecules.card>
@endsection
