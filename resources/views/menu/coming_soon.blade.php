@extends('layouts.app')

@section('title', $title)

@section('content')
    <h5 class="mb-3">{{ $title }}</h5>
    <div class="card">
        <div class="card-body text-muted">
            Form ini belum tersedia di versi Laravel. Data yang sudah ada bisa dilihat di
            <a href="{{ route('menu', $back) }}">{{ config("marketama.pages.{$back}.title") }}</a>.
        </div>
    </div>
@endsection
