{{-- Ikon kanan atas, sama seperti header Marketama. --}}
@props(['breadcrumbSlug' => null])

<div class="topbar flex justify-between items-center gap-2">
    <x-ui.molecules.breadcrumb :slug="$breadcrumbSlug" />
    <div class="flex items-center gap-2">
        <a class="shortcut-btn" href="{{ url('loading_barang/add') }}" title="Tambah Loading Barang"><i class="fas fa-truck-loading"></i></a>
        <a class="shortcut-btn" href="{{ url('barang_canvassing/add') }}" title="Tambah Barang Canvassing"><i class="fas fa-boxes"></i></a>
        <a class="shortcut-btn" href="{{ route('design-area.index') }}" title="Design Polygon Area"><i class="fas fa-draw-polygon"></i></a>
        <div class="relative">
            <a class="notif-bell" href="#" data-dropdown-toggle="notif_menu" title="Notifikasi Transaksi" aria-label="Notifikasi Transaksi"><i class="fas fa-bell"></i></a>
            <div class="dropdown-menu hidden" id="notif_menu">
                <i class="fas fa-bell-slash me-1"></i> Tidak ada notifikasi baru
            </div>
        </div>
    </div>
</div>
