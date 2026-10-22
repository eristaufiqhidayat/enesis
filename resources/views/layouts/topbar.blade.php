{{-- Ikon kanan atas, sama seperti header Marketama. --}}
<div class="topbar d-flex justify-content-end align-items-center gap-1 mb-3">
    <a class="topbar-icon" href="{{ url('loading_barang/add') }}" title="Tambah Loading Barang" data-bs-toggle="tooltip"><i class="fas fa-truck-loading"></i></a>
    <a class="topbar-icon" href="{{ url('barang_canvassing/add') }}" title="Tambah Barang Canvassing" data-bs-toggle="tooltip"><i class="fas fa-boxes"></i></a>
    <a class="topbar-icon" href="{{ route('design-area.index') }}" title="Design Polygon Area" data-bs-toggle="tooltip"><i class="fas fa-draw-polygon"></i></a>
    <div class="dropdown">
        <a class="topbar-icon" href="#" data-bs-toggle="dropdown" title="Notifikasi Transaksi"><i class="fas fa-bell"></i></a>
        <div class="dropdown-menu dropdown-menu-end p-3 text-muted small" style="min-width: 240px;">
            <i class="fas fa-bell-slash me-1"></i> Tidak ada notifikasi baru
        </div>
    </div>
</div>
