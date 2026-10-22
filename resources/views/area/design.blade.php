@extends('layouts.app')

@section('title', 'Design Polygon Area')

@push('head')
    <link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
    <style>
        #polygon_map { height: 620px; border-radius: .375rem; }
        #polygon_map.drawing-mode { cursor: crosshair; }
        #area_list .list-group-item { cursor: pointer; font-size: .8rem; }
        .area-label { background: transparent; border: none; box-shadow: none; font-weight: 600; font-size: 11px; color: #212529; text-shadow: 0 0 3px #fff; }
    </style>
@endpush

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">Design Polygon Area</h5>
        <a class="text-secondary" href="{{ route('menu', 'master_area') }}">Kembali</a>
    </div>

    <div class="row g-2 mb-2">
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header bg-light py-2"><h6 class="mb-0">Filter &amp; Zoom Area</h6></div>
                <div class="card-body py-2">
                    <label class="form-label small mb-1" for="map_search"><i class="fas fa-search-location me-1"></i>Cari Alamat di Peta</label>
                    <input class="form-control form-control-sm mb-2" id="map_search" type="text" placeholder="Ketik alamat lalu tekan Enter..." autocomplete="off">
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label small mb-1" for="filter_province">Provinsi <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="filter_province" data-level="province">
                                <option value="">Pilih Provinsi</option>
                                @foreach ($provinces as $province)
                                    <option>{{ $province }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small mb-1" for="filter_city">Kota / Kabupaten</label>
                            <select class="form-select form-select-sm" id="filter_city" data-level="city"><option value="">Semua</option></select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small mb-1" for="filter_district">Kecamatan</label>
                            <select class="form-select form-select-sm" id="filter_district" data-level="district"><option value="">Semua</option></select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small mb-1" for="filter_village">Kelurahan / Desa</label>
                            <select class="form-select form-select-sm" id="filter_village" data-level="village"><option value="">Semua</option></select>
                        </div>
                        <div class="col-12 d-flex gap-2">
                            <button type="button" class="btn btn-primary btn-sm flex-grow-1" id="btn_filter"><i class="fas fa-search me-1"></i>Tampilkan</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="btn_reset"><i class="fas fa-undo me-1"></i>Reset</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header bg-light py-2"><h6 class="mb-0">Area Tersimpan (<span id="area_count">0</span>)</h6></div>
                <div class="card-body py-2 pb-0">
                    <input type="search" class="form-control form-control-sm mb-2" id="search_area" placeholder="Cari area...">
                </div>
                <div style="height: 200px; overflow-y: auto;">
                    <div class="list-group list-group-flush" id="area_list">
                        <div class="list-group-item text-center text-muted py-4">Klik "Tampilkan" untuk memuat data area</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-2">
        <div class="card-header bg-light py-2"><h6 class="mb-0"><i class="fas fa-draw-polygon me-1"></i>Gambar Polygon Baru</h6></div>
        <div class="card-body py-2 d-flex align-items-center gap-2 flex-wrap">
            <button type="button" class="btn btn-success btn-sm" id="btn_start_draw"><i class="fas fa-pencil-alt me-1"></i>Mulai Gambar</button>
            <button type="button" class="btn btn-primary btn-sm d-none" id="btn_finish_draw"><i class="fas fa-check me-1"></i>Selesai</button>
            <button type="button" class="btn btn-outline-warning btn-sm d-none" id="btn_undo_point"><i class="fas fa-undo me-1"></i>Hapus Titik Terakhir</button>
            <button type="button" class="btn btn-outline-danger btn-sm d-none" id="btn_cancel_draw"><i class="fas fa-times me-1"></i>Batal</button>
            <span class="badge bg-secondary" id="map_mode_badge">Mode: Lihat</span>
            <small class="text-muted" id="draw_status">Pilih provinsi lalu klik "Mulai Gambar" untuk membuat area baru.</small>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-2"><div id="polygon_map"></div></div>
    </div>

    {{-- Form simpan area baru --}}
    <div class="modal fade" id="save_modal" tabindex="-1">
        <div class="modal-dialog">
            <form class="modal-content" id="save_form">
                <div class="modal-header"><h6 class="modal-title">Simpan Area Baru</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-2"><label class="form-label small">Nama Area <span class="text-danger">*</span></label><input class="form-control form-control-sm" name="name" required></div>
                    <div class="mb-2">
                        <label class="form-label small">Depo</label>
                        <select class="form-select form-select-sm" name="warehouse_id">
                            <option value="">-</option>
                            @foreach ($warehouses as $w)
                                <option value="{{ $w->warehouse_id }}">{{ $w->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-2"><label class="form-label small">Keterangan</label><textarea class="form-control form-control-sm" name="info" rows="2"></textarea></div>
                    <small class="text-muted">Wilayah mengikuti filter: <span id="save_region">-</span></small>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Batal</button><button class="btn btn-sm btn-primary">Simpan</button></div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
    <script>
        const urls = {
            options: @json(route('design-area.options')),
            areas: @json(route('design-area.areas')),
            store: @json(route('design-area.store')),
            destroy: @json(url('master_area/design_area/areas')),
        };
        const csrf = document.querySelector('meta[name="csrf-token"]').content;
        const levels = ['province', 'city', 'district', 'village'];
        const $ = (id) => document.getElementById(id);
        const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        const COLORS = ['#e6194b', '#3cb44b', '#4363d8', '#f58231', '#911eb4', '#42d4f4', '#f032e6', '#469990', '#9A6324', '#800000', '#808000', '#000075'];

        // ---------- Peta ----------
        const map = L.map('polygon_map', { center: [-6.2, 106.8], zoom: 10 });
        const osm = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' }).addTo(map);
        const sat = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', { maxZoom: 20, maxNativeZoom: 19, attribution: 'Tiles &copy; Esri' });
        L.control.layers({ '2D Map': osm, 'Satelit': sat }, {}, { position: 'topright' }).addTo(map);

        const areaLayer = L.featureGroup().addTo(map);
        const depoLayer = L.layerGroup().addTo(map);
        let areas = [];
        const overlays = new Map();

        const depoIcon = L.divIcon({
            className: '', iconSize: [36, 36], iconAnchor: [18, 18],
            html: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" width="36" height="36"><circle cx="32" cy="32" r="30" fill="#6f42c1" opacity="0.9"/><rect x="18" y="32" width="28" height="18" fill="#fff"/><polygon points="12,33 32,14 52,33" fill="#fff"/></svg>',
        });

        function areaPopup(a) {
            return '<div style="min-width:220px;font-size:12px">'
                + '<strong style="font-size:13px">' + esc(a.name) + '</strong><br><span class="text-muted">' + esc(a.code) + '</span><hr class="my-1">'
                + '<div><b>Depo:</b> ' + esc(a.warehouse || '-') + '</div>'
                + '<div><b>Wilayah Depo:</b> ' + esc(a.branch_area || '-') + '</div>'
                + '<div><b>Kelurahan:</b> ' + esc(a.village || '-') + ', ' + esc(a.district || '-') + '</div>'
                + '<div><b>Kota:</b> ' + esc(a.city || '-') + '</div>'
                + '<div><b>Jumlah Toko:</b> ' + (a.outlet_count ?? 0) + '</div>'
                + '<div><b>Sales:</b> ' + esc(a.salesperson || '-') + '</div>'
                + (a.info ? '<div><b>Info:</b> ' + esc(a.info) + '</div>' : '')
                + '<button type="button" class="btn btn-sm btn-outline-danger mt-2 btn-delete-area" data-id="' + a.id + '"><i class="fas fa-trash me-1"></i>Hapus</button></div>';
        }

        function drawAreas() {
            areaLayer.clearLayers();
            overlays.clear();
            areas.forEach((a, i) => {
                const latlngs = (a.polygon || []).map(([lng, lat]) => [lat, lng]);
                if (latlngs.length < 3) return;
                const color = COLORS[(a.warehouse_id || i) % COLORS.length];
                const poly = L.polygon(latlngs, { color, weight: 2, fillOpacity: 0.25 }).bindPopup(areaPopup(a));
                poly.addTo(areaLayer);
                overlays.set(a.id, poly);
            });
            if (areaLayer.getLayers().length) map.fitBounds(areaLayer.getBounds(), { padding: [20, 20] });
        }

        function renderList() {
            const q = $('search_area').value.toLowerCase();
            const shown = areas.filter((a) => !q || (a.name + ' ' + a.code + ' ' + (a.village || '')).toLowerCase().includes(q));
            $('area_count').textContent = areas.length;
            $('area_list').innerHTML = shown.length
                ? shown.slice(0, 300).map((a) => '<a class="list-group-item list-group-item-action py-1" data-id="' + a.id + '"><b>' + esc(a.name) + '</b> <span class="text-muted">' + esc(a.code) + '</span><br><small class="text-muted">' + esc(a.village || '-') + ', ' + esc(a.district || '-') + ' &middot; ' + (a.outlet_count ?? 0) + ' toko</small></a>').join('')
                    + (shown.length > 300 ? '<div class="list-group-item text-center text-muted small">' + (shown.length - 300) + ' area lainnya, persempit filter atau pencarian</div>' : '')
                : '<div class="list-group-item text-center text-muted py-4">Tidak ada area</div>';
        }

        $('area_list').addEventListener('click', (e) => {
            const item = e.target.closest('[data-id]');
            const poly = item && overlays.get(Number(item.dataset.id));
            if (!poly) return;
            map.fitBounds(poly.getBounds(), { maxZoom: 17 });
            poly.openPopup();
        });
        $('search_area').addEventListener('input', renderList);

        // ---------- Filter wilayah ----------
        function filters() {
            const f = {};
            levels.forEach((l) => { const v = $('filter_' + l).value; if (v) f[l] = v; });
            return f;
        }

        async function fillOptions(level) {
            const sel = $('filter_' + level);
            sel.innerHTML = '<option value="">Semua</option>';
            const parents = filters();
            if (!parents.province) return;
            const params = new URLSearchParams({ level });
            for (const l of levels) { if (l === level) break; if (parents[l]) params.set(l, parents[l]); }
            const list = await fetch(urls.options + '?' + params).then((r) => r.json());
            sel.insertAdjacentHTML('beforeend', list.map((v) => '<option>' + esc(v) + '</option>').join(''));
        }

        levels.forEach((level, i) => {
            $('filter_' + level).addEventListener('change', () => {
                levels.slice(i + 1).forEach((child, j) => {
                    $('filter_' + child).innerHTML = '<option value="">Semua</option>';
                    if (j === 0) fillOptions(child);
                });
            });
        });

        async function loadAreas() {
            const f = filters();
            if (!f.province) { alert('Harap pilih Provinsi terlebih dahulu.'); return; }
            $('area_list').innerHTML = '<div class="list-group-item text-center py-4"><span class="spinner-border spinner-border-sm me-2"></span>Memuat data...</div>';
            const data = await fetch(urls.areas + '?' + new URLSearchParams(f)).then((r) => r.json());
            areas = data.areas;
            renderList();
            drawAreas();
            depoLayer.clearLayers();
            data.warehouses.forEach((w) => {
                if (!w.lat || !w.lng) return;
                L.marker([w.lat, w.lng], { icon: depoIcon, zIndexOffset: 500 }).bindPopup('<b>' + esc(w.name) + '</b><br><small>Depo</small>').addTo(depoLayer);
            });
        }

        $('btn_filter').addEventListener('click', loadAreas);
        $('btn_reset').addEventListener('click', () => {
            $('filter_province').value = '';
            levels.slice(1).forEach((l) => { $('filter_' + l).innerHTML = '<option value="">Semua</option>'; });
            areas = []; renderList(); areaLayer.clearLayers(); depoLayer.clearLayers();
            $('area_list').innerHTML = '<div class="list-group-item text-center text-muted py-4">Klik "Tampilkan" untuk memuat data area</div>';
        });

        // Hapus area dari popup
        map.on('popupopen', (e) => {
            const btn = e.popup.getElement().querySelector('.btn-delete-area');
            if (!btn) return;
            btn.addEventListener('click', async () => {
                if (!confirm('Hapus area ini?')) return;
                await fetch(urls.destroy + '/' + btn.dataset.id, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': csrf } });
                map.closePopup();
                areas = areas.filter((a) => a.id !== Number(btn.dataset.id));
                renderList(); drawAreas();
            });
        });

        // ---------- Cari alamat ----------
        $('map_search').addEventListener('keydown', (e) => {
            if (e.key !== 'Enter') return;
            e.preventDefault();
            fetch('https://nominatim.openstreetmap.org/search?format=json&limit=1&countrycodes=id&q=' + encodeURIComponent(e.target.value))
                .then((r) => r.json())
                .then((d) => { if (d[0]) map.setView([parseFloat(d[0].lat), parseFloat(d[0].lon)], 17); });
        });

        // ---------- Gambar polygon baru ----------
        let drawing = false;
        let points = [];
        const drawLayer = L.layerGroup().addTo(map);

        function setDrawUi(on) {
            drawing = on;
            $('polygon_map').classList.toggle('drawing-mode', on);
            $('btn_start_draw').classList.toggle('d-none', on);
            ['btn_finish_draw', 'btn_undo_point', 'btn_cancel_draw'].forEach((id) => $(id).classList.toggle('d-none', !on));
            $('map_mode_badge').textContent = on ? 'Mode: Menggambar' : 'Mode: Lihat';
            $('map_mode_badge').className = 'badge ' + (on ? 'bg-danger' : 'bg-secondary');
            areaLayer.eachLayer((l) => l.setStyle({ fillOpacity: on ? 0.08 : 0.25, opacity: on ? 0.4 : 1 }));
        }

        function redraw() {
            drawLayer.clearLayers();
            if (points.length > 1) L.polygon(points, { color: '#dc3545', dashArray: '6 4', fillOpacity: 0.15 }).addTo(drawLayer);
            points.forEach((p, i) => L.circleMarker(p, { radius: i === 0 ? 7 : 5, color: '#fff', weight: 2, fillColor: i === 0 ? '#dc3545' : '#0d6efd', fillOpacity: 1 }).addTo(drawLayer));
            $('draw_status').textContent = points.length + ' titik. ' + (points.length < 3 ? 'Tambahkan minimal 3 titik.' : 'Klik "Selesai" untuk menyimpan.');
        }

        $('btn_start_draw').addEventListener('click', () => {
            if (!$('filter_province').value) { alert('Pilih Provinsi dulu supaya area tersimpan di wilayah yang benar.'); return; }
            points = []; setDrawUi(true); redraw();
        });
        $('btn_undo_point').addEventListener('click', () => { points.pop(); redraw(); });
        $('btn_cancel_draw').addEventListener('click', () => { points = []; drawLayer.clearLayers(); setDrawUi(false); $('draw_status').textContent = 'Menggambar dibatalkan.'; });
        $('btn_finish_draw').addEventListener('click', () => {
            if (points.length < 3) { alert('Polygon minimal 3 titik.'); return; }
            const f = filters();
            $('save_region').textContent = levels.map((l) => f[l]).filter(Boolean).join(', ');
            bootstrap.Modal.getOrCreateInstance($('save_modal')).show();
        });
        map.on('click', (e) => { if (drawing) { points.push([e.latlng.lat, e.latlng.lng]); redraw(); } });

        $('save_form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const form = new FormData(e.target);
            const body = { ...filters(), name: form.get('name'), warehouse_id: form.get('warehouse_id') || null, info: form.get('info') || null, polygon: points.map(([lat, lng]) => [lng, lat]) };
            const res = await fetch(urls.store, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify(body) });
            if (!res.ok) { alert('Gagal menyimpan area.'); return; }
            const saved = await res.json();
            bootstrap.Modal.getInstance($('save_modal')).hide();
            e.target.reset();
            points = []; drawLayer.clearLayers(); setDrawUi(false);
            $('draw_status').textContent = 'Area ' + saved.code + ' tersimpan.';
            loadAreas();
        });
    </script>
@endpush
