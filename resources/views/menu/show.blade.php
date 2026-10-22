@extends('layouts.app')

@section('title', $page['title'])

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2">
        <h5 class="mb-0">{{ $page['title'] }}</h5>
        @if ($tables)
            <form method="get" class="d-flex gap-2">
                <input type="search" name="q" value="{{ $search }}" class="form-control form-control-sm" placeholder="Cari...">
                <button class="btn btn-sm btn-primary">Cari</button>
            </form>
        @endif
    </div>

    @if ($page['same_as'])
        <div class="alert alert-info py-2">
            Data menu ini sama dengan
            <a href="{{ route('menu', $page['same_as']) }}">{{ config("marketama.pages.{$page['same_as']}.title") }}</a>.
        </div>
    @endif

    @forelse ($tables as $table)
        <div class="card mb-3">
            @if (count($tables) > 1)
                <div class="card-header fw-semibold">{{ $table['title'] }}</div>
            @endif
            <div class="table-responsive">
                <table class="table table-sm table-striped table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            @foreach ($table['columns'] as $label)
                                <th class="text-nowrap">{{ $label }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($table['rows'] as $row)
                            <tr>
                                @foreach (array_keys($table['columns']) as $column)
                                    <td class="text-nowrap">{{ $row->{$column} }}</td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($table['columns']) }}" class="text-center text-muted py-4">Tidak ada data.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer d-flex justify-content-between align-items-center">
                <small class="text-muted">Total {{ number_format($table['rows']->total(), 0, ',', '.') }} data</small>
                {{ $table['rows']->onEachSide(1)->links('pagination::bootstrap-5') }}
            </div>
        </div>
    @empty
        @unless ($page['same_as'])
            <div class="card">
                <div class="card-body text-muted">
                    Halaman ini di Marketama berupa peta atau grafik, jadi tidak ada tabel data yang ditarik.
                </div>
            </div>
        @endunless
    @endforelse
@endsection
