{{-- Filter bersama halaman kehadiran (absensi, checkin depo, checkin outlet): rentang tanggal, sales, outlet, depo, koordinator, sumber data. --}}
<form method="GET" class="flex flex-wrap items-end gap-2" role="search">
    @isset($status) @if ($status !== 'semua') <input type="hidden" name="status" value="{{ $status }}"> @endif @endisset
    <div>
        <x-ui.atoms.label for="dari">Dari</x-ui.atoms.label>
        <x-ui.atoms.input type="date" id="dari" name="dari" value="{{ $filter['dari'] }}" />
    </div>
    <div>
        <x-ui.atoms.label for="sampai">Sampai</x-ui.atoms.label>
        <x-ui.atoms.input type="date" id="sampai" name="sampai" value="{{ $filter['sampai'] }}" />
    </div>
    <div class="grow min-w-[12rem]">
        @php($orang = strtolower($labelOrang ?? 'Sales'))
        <x-ui.atoms.label for="q">Nama {{ $orang }}</x-ui.atoms.label>
        <x-ui.atoms.input type="search" id="q" name="q" value="{{ $filter['q'] }}" placeholder="Cari nama {{ $orang }}…" />
    </div>
    @isset($cariOutlet)
        <div class="grow min-w-[12rem]">
            <x-ui.atoms.label for="outlet">Outlet</x-ui.atoms.label>
            <x-ui.atoms.input type="search" id="outlet" name="outlet" value="{{ $cariOutlet }}" placeholder="Nama atau kode outlet…" />
        </div>
    @endisset
    <div>
        <x-ui.atoms.label for="depo">Depo</x-ui.atoms.label>
        <x-ui.atoms.select id="depo" name="depo">
            <option value="">Semua depo</option>
            @foreach ($daftarDepo as $id => $nama)
                <option value="{{ $id }}" @selected($filter['depo'] === (int) $id)>{{ $nama }}</option>
            @endforeach
        </x-ui.atoms.select>
    </div>
    @if ($daftarKoordinator->isNotEmpty())
        <div>
            <x-ui.atoms.label for="koordinator">Koordinator</x-ui.atoms.label>
            <x-ui.atoms.select id="koordinator" name="koordinator">
                <option value="">Semua koordinator</option>
                @foreach ($daftarKoordinator as $id => $nama)
                    <option value="{{ $id }}" @selected($filter['koordinator'] === (int) $id)>{{ $nama }}</option>
                @endforeach
            </x-ui.atoms.select>
        </div>
    @endif
    <div>
        <x-ui.atoms.label for="sumber">Sumber</x-ui.atoms.label>
        <x-ui.atoms.select id="sumber" name="sumber">
            @foreach (\App\Http\Controllers\Admin\KehadiranController::SUMBER as $kode => $label)
                <option value="{{ $kode }}" @selected($filter['sumber'] === $kode)>{{ $label }}</option>
            @endforeach
        </x-ui.atoms.select>
    </div>
    <x-ui.atoms.button type="submit">Tampilkan</x-ui.atoms.button>
</form>
