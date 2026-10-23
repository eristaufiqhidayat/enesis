@extends('layouts.app')

@section('title', $title)

@section('content')
    <h5 class="mb-3 font-semibold">{{ $title }}</h5>
    <div class="card">
        <div class="card-body text-gray-500">
            Form ini belum tersedia di versi Laravel. Data yang sudah ada bisa dilihat di
            <a class="text-blue-600 hover:underline" href="{{ route('menu', $back) }}">{{ config("marketama.pages.{$back}.title") }}</a>.
        </div>
    </div>
@endsection
