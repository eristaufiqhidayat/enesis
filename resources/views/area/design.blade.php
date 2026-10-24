@extends('layouts.app')

@section('title', 'Design Polygon Area')
@section('breadcrumb_slug', 'master_area')

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
    <div class="flex justify-between items-center mb-3">
        <h5 class="mb-0 font-semibold">Design Polygon Area</h5>
        <a class="text-gray-500 hover:underline" href="{{ route('menu', 'master_area') }}">Kembali</a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-2 mb-2">
        <div class="lg:col-span-5">
            <x-ui.molecules.card class="h-full" body-class="card-body card-body-compact">
                <x-slot:header><h6 class="mb-0 font-semibold">Filter &amp; Zoom Area</h6></x-slot:header>

                <x-ui.atoms.label for="map_search" class="mb-1"><x-ui.atoms.icon name="map-pin" class="me-1" />Cari Alamat di Peta</x-ui.atoms.label>
                <x-ui.atoms.input id="map_search" class="mb-2" placeholder="Ketik alamat lalu tekan Enter..." autocomplete="off" />
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <x-ui.atoms.label for="filter_province" class="mb-1">Provinsi <span class="text-red-600">*</span></x-ui.atoms.label>
                        <x-ui.atoms.select id="filter_province" data-level="province">
                            <option value="">Pilih Provinsi</option>
                            @foreach ($provinces as $province)
                                <option>{{ $province }}</option>
                            @endforeach
                        </x-ui.atoms.select>
                    </div>
                    <div>
                        <x-ui.atoms.label for="filter_city" class="mb-1">Kota / Kabupaten</x-ui.atoms.label>
                        <x-ui.atoms.select id="filter_city" data-level="city"><option value="">Semua</option></x-ui.atoms.select>
                    </div>
                    <div>
                        <x-ui.atoms.label for="filter_district" class="mb-1">Kecamatan</x-ui.atoms.label>
                        <x-ui.atoms.select id="filter_district" data-level="district"><option value="">Semua</option></x-ui.atoms.select>
                    </div>
                    <div>
                        <x-ui.atoms.label for="filter_village" class="mb-1">Kelurahan / Desa</x-ui.atoms.label>
                        <x-ui.atoms.select id="filter_village" data-level="village"><option value="">Semua</option></x-ui.atoms.select>
                    </div>
                    <div class="col-span-2 flex gap-2">
                        <x-ui.atoms.button variant="primary" class="grow" id="btn_filter"><x-ui.atoms.icon name="search" class="me-1" />Tampilkan</x-ui.atoms.button>
                        <x-ui.atoms.button variant="outline-secondary" id="btn_reset"><x-ui.atoms.icon name="undo" class="me-1" />Reset</x-ui.atoms.button>
                    </div>
                </div>
            </x-ui.molecules.card>
        </div>
        <div class="lg:col-span-7">
            <div class="card h-full">
                <div class="card-header card-header-compact bg-gray-50"><h6 class="mb-0 font-semibold">Area Tersimpan (<span id="area_count">0</span>)</h6></div>
                <div class="card-body card-body-compact pb-0">
                    <x-ui.atoms.input type="search" id="search_area" class="mb-2" placeholder="Cari area..." />
                </div>
                <div class="h-50 overflow-y-auto">
                    <div class="list-group list-group-flush" id="area_list">
                        <div class="list-group-item text-center text-gray-500 py-4">Klik "Tampilkan" untuk memuat data area</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <x-ui.molecules.card class="mb-2" body-class="card-body card-body-compact flex items-center gap-2 flex-wrap">
        <x-slot:header><h6 class="mb-0 font-semibold"><x-ui.atoms.icon name="shapes" class="me-1" />Gambar Polygon Baru</h6></x-slot:header>

        <x-ui.atoms.button variant="success" id="btn_start_draw"><x-ui.atoms.icon name="pen" class="me-1" />Mulai Gambar</x-ui.atoms.button>
        <x-ui.atoms.button variant="primary" class="hidden" id="btn_finish_draw"><x-ui.atoms.icon name="check" class="me-1" />Selesai</x-ui.atoms.button>
        <x-ui.atoms.button variant="outline-warning" class="hidden" id="btn_undo_point"><x-ui.atoms.icon name="undo" class="me-1" />Hapus Titik Terakhir</x-ui.atoms.button>
        <x-ui.atoms.button variant="outline-danger" class="hidden" id="btn_cancel_draw"><x-ui.atoms.icon name="x" class="me-1" />Batal</x-ui.atoms.button>
        <x-ui.atoms.badge variant="secondary" id="map_mode_badge">Mode: Lihat</x-ui.atoms.badge>
        <small class="text-gray-500" id="draw_status">Pilih provinsi lalu klik "Mulai Gambar" untuk membuat area baru.</small>
    </x-ui.molecules.card>

    <x-ui.molecules.card body-class="card-body p-2">
        <div id="polygon_map"></div>
    </x-ui.molecules.card>

    {{-- Form simpan area baru --}}
    <x-ui.molecules.modal id="save_modal">
        <form class="modal-content" id="save_form">
            <div class="modal-header">
                <h6 class="m-0 font-semibold">Simpan Area Baru</h6>
                <button type="button" class="btn-close" data-modal-dismiss="save_modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="mb-2"><x-ui.atoms.label>Nama Area <span class="text-red-600">*</span></x-ui.atoms.label><x-ui.atoms.input name="name" required /></div>
                <div class="mb-2">
                    <x-ui.atoms.label>Depo</x-ui.atoms.label>
                    <x-ui.atoms.select name="warehouse_id">
                        <option value="">-</option>
                        @foreach ($warehouses as $w)
                            <option value="{{ $w->warehouse_id }}">{{ $w->name }}</option>
                        @endforeach
                    </x-ui.atoms.select>
                </div>
                <div class="mb-2"><x-ui.atoms.label>Keterangan</x-ui.atoms.label><x-ui.atoms.textarea name="info" rows="2" /></div>
                <small class="text-gray-500">Wilayah mengikuti filter: <span id="save_region">-</span></small>
            </div>
            <div class="modal-footer">
                <x-ui.atoms.button variant="light" data-modal-dismiss="save_modal">Batal</x-ui.atoms.button>
                <x-ui.atoms.button variant="primary" type="submit">Simpan</x-ui.atoms.button>
            </div>
        </form>
    </x-ui.molecules.modal>
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
        // SVG Lucide ditulis manual karena titik ini bukan konteks render Blade (popup Leaflet dibangun lewat template literal JS).
        const icoTrash = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:4px"><path d="M10 11v6"/><path d="M14 11v6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>';

        function showModal(id) { $(id).classList.remove('hidden'); }
        function hideModal(id) { $(id).classList.add('hidden'); }
        document.querySelectorAll('[data-modal-dismiss]').forEach((btn) => {
            btn.addEventListener('click', () => hideModal(btn.dataset.modalDismiss));
        });
        $('save_modal').addEventListener('click', (e) => { if (e.target === $('save_modal')) hideModal('save_modal'); });

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
                + '<strong style="font-size:13px">' + esc(a.name) + '</strong><br><span class="text-gray-500">' + esc(a.code) + '</span><hr class="my-1">'
                + '<div><b>Depo:</b> ' + esc(a.warehouse || '-') + '</div>'
                + '<div><b>Wilayah Depo:</b> ' + esc(a.branch_area || '-') + '</div>'
                + '<div><b>Kelurahan:</b> ' + esc(a.village || '-') + ', ' + esc(a.district || '-') + '</div>'
                + '<div><b>Kota:</b> ' + esc(a.city || '-') + '</div>'
                + '<div><b>Jumlah Toko:</b> ' + (a.outlet_count ?? 0) + '</div>'
                + '<div><b>Sales:</b> ' + esc(a.salesperson || '-') + '</div>'
                + (a.info ? '<div><b>Info:</b> ' + esc(a.info) + '</div>' : '')
                + '<button type="button" class="btn btn-sm btn-outline-danger mt-2 btn-delete-area" data-id="' + a.id + '">' + icoTrash + 'Hapus</button></div>';
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
                ? shown.slice(0, 300).map((a) => '<a class="list-group-item list-group-item-action py-1" data-id="' + a.id + '"><b>' + esc(a.name) + '</b> <span class="text-gray-500">' + esc(a.code) + '</span><br><small class="text-gray-500">' + esc(a.village || '-') + ', ' + esc(a.district || '-') + ' &middot; ' + (a.outlet_count ?? 0) + ' toko</small></a>').join('')
                    + (shown.length > 300 ? '<div class="list-group-item text-center text-gray-500 text-xs">' + (shown.length - 300) + ' area lainnya, persempit filter atau pencarian</div>' : '')
                : '<div class="list-group-item text-center text-gray-500 py-4">Tidak ada area</div>';
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
            $('area_list').innerHTML = '<div class="list-group-item text-center py-4"><span class="spinner me-2"></span>Memuat data...</div>';
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
            $('area_list').innerHTML = '<div class="list-group-item text-center text-gray-500 py-4">Klik "Tampilkan" untuk memuat data area</div>';
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
            $('btn_start_draw').classList.toggle('hidden', on);
            ['btn_finish_draw', 'btn_undo_point', 'btn_cancel_draw'].forEach((id) => $(id).classList.toggle('hidden', !on));
            $('map_mode_badge').textContent = on ? 'Mode: Menggambar' : 'Mode: Lihat';
            $('map_mode_badge').className = 'badge ' + (on ? 'badge-danger' : 'badge-secondary');
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
            showModal('save_modal');
        });
        map.on('click', (e) => { if (drawing) { points.push([e.latlng.lat, e.latlng.lng]); redraw(); } });

        $('save_form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const form = new FormData(e.target);
            const body = { ...filters(), name: form.get('name'), warehouse_id: form.get('warehouse_id') || null, info: form.get('info') || null, polygon: points.map(([lat, lng]) => [lng, lat]) };
            const res = await fetch(urls.store, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify(body) });
            if (!res.ok) { alert('Gagal menyimpan area.'); return; }
            const saved = await res.json();
            hideModal('save_modal');
            e.target.reset();
            points = []; drawLayer.clearLayers(); setDrawUi(false);
            $('draw_status').textContent = 'Area ' + saved.code + ' tersimpan.';
            loadAreas();
        });
    </script>
@endpush
