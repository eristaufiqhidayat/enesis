<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Badge from '@/Components/ui/atoms/Badge.vue';
import Button from '@/Components/ui/atoms/Button.vue';
import Icon from '@/Components/ui/atoms/Icon.vue';
import Input from '@/Components/ui/atoms/Input.vue';
import Label from '@/Components/ui/atoms/Label.vue';
import Select from '@/Components/ui/atoms/Select.vue';
import Textarea from '@/Components/ui/atoms/Textarea.vue';
import Card from '@/Components/ui/molecules/Card.vue';
import Modal from '@/Components/ui/molecules/Modal.vue';
import PageHeader from '@/Components/ui/molecules/PageHeader.vue';
import { useLeafletMap } from '@/Composables/useLeafletMap.js';
import { bersihkanQuery } from '@/Support/query.js';
import { escapeHtml, ikonDepo, ikonLingkaran, ikonSvg, multiKeLatLng } from '@/Support/peta.js';
import { MAKS_PENANDA, buatEditorPolygon } from '@/Pages/Area/partials/editorPolygon.js';

const props = defineProps({
    slug: { type: String, required: true },
    title: { type: String, required: true },
    provinces: { type: Array, required: true },
    warehouses: { type: Array, required: true },
    bisaUbah: { type: Boolean, default: false },
    bisaHapus: { type: Boolean, default: false },
    kembaliUrl: { type: String, required: true },
    optionsUrl: { type: String, required: true },
    areasUrl: { type: String, required: true },
    storeUrl: { type: String, required: true },
    destroyUrl: { type: String, required: true },
    updateUrl: { type: String, required: true },
    // Area yang langsung dibuka (?area=<id>): { id, province, city, district, village }.
    fokus: { type: Object, default: null },
    generateUrl: { type: String, default: null },
    autoGridUrl: { type: String, default: null },
});

const appName = usePage().props.appName;
const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

const levels = ['province', 'city', 'district', 'village'];
const warna = ['#e6194b', '#3cb44b', '#4363d8', '#f58231', '#911eb4', '#42d4f4', '#f032e6', '#469990', '#9A6324', '#800000', '#808000', '#000075'];
const ikonPensil = ikonSvg('<path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/>');
const ikonBentuk = ikonSvg('<path d="M12 3 3 9l3 11h12l3-11z"/><circle cx="12" cy="3" r="1.5"/><circle cx="3" cy="9" r="1.5"/><circle cx="21" cy="9" r="1.5"/>');
const ikonTempatSampah = ikonSvg('<path d="M10 11v6"/><path d="M14 11v6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>');

// Filter wilayah & opsi dropdown bertingkat (kota per provinsi, kecamatan per kota, kelurahan per kecamatan).
const filter = reactive({ province: '', city: '', district: '', village: '' });
const opsi = reactive({ city: [], district: [], village: [] });

// Daftar area & pencarian.
const areas = ref([]);
const cari = ref('');
const memuat = ref(false);
const tampil = computed(() => {
    const q = cari.value.toLowerCase();
    return areas.value.filter((a) => !q || (a.name + ' ' + a.code + ' ' + (a.village || '')).toLowerCase().includes(q));
});
const jumlahTersembunyi = computed(() => Math.max(0, tampil.value.length - 300));

// Menggambar polygon baru.
const menggambar = ref(false);
const titik = ref([]);
const statusGambar = ref('Pilih provinsi lalu klik "Mulai Gambar" untuk membuat area baru.');
const pesanGambar = computed(() => {
    if (!menggambar.value) return statusGambar.value;
    const n = titik.value.length;
    return `${n} titik. ` + (n < 3 ? 'Klik peta untuk menambah titik (minimal 3).' : 'Klik "Selesai" atau klik dua kali di peta untuk menyimpan.');
});

// Modal data area dipakai untuk area baru (hasil gambar) dan untuk mengubah data area yang ada.
const modalSimpan = ref(false);
const areaDiubah = ref(null);
const formSimpan = reactive({ name: '', warehouse_id: '', info: '' });

// Mengubah bentuk area yang sudah ada.
const mengedit = ref(null);
const infoEdit = reactive({ titik: 0, tampil: true, bisaUrung: false });
const menyimpanBentuk = ref(false);
const sibuk = computed(() => menggambar.value || !!mengedit.value);
const wilayahSimpan = computed(() => levels.map((l) => filter[l]).filter(Boolean).join(', ') || '-');

// Layer & instance Leaflet bukan state reaktif Vue: disimpan di luar ref supaya tidak di-proxy.
let L = null;
let peta = null;
let lapisanArea = null;
let lapisanDepo = null;
let lapisanGambar = null;
let editor = null;
const overlay = new Map();

const wadah = ref(null);
useLeafletMap(wadah, {
    pusat: [-6.2, 106.8],
    zoom: 10,
    onSiap(map, Lib) {
        L = Lib;
        peta = map;
        lapisanArea = L.featureGroup().addTo(map);
        lapisanDepo = L.layerGroup().addTo(map);
        lapisanGambar = L.layerGroup().addTo(map);
        editor = buatEditorPolygon(L, map, (info) => Object.assign(infoEdit, info));

        map.on('click', (e) => {
            if (!menggambar.value) return;
            // Klik kedua dari dobel klik jatuh di titik yang sama: jangan jadi titik kembar.
            const akhir = titik.value[titik.value.length - 1];
            if (akhir && Math.abs(akhir[0] - e.latlng.lat) < 1e-9 && Math.abs(akhir[1] - e.latlng.lng) < 1e-9) return;
            titik.value.push([e.latlng.lat, e.latlng.lng]);
            gambarTitik();
        });

        // Dobel klik dipakai menutup polygon, bukan zoom.
        map.doubleClickZoom.disable();
        map.on('dblclick', () => {
            if (menggambar.value && titik.value.length >= 3) selesaiGambar();
        });

        // Tombol Ubah / Ubah Bentuk / Hapus ada di HTML popup Leaflet, jadi listener dipasang saat popup dibuka.
        map.on('popupopen', (e) => {
            const el = e.popup.getElement();
            const aksi = { '.btn-edit-area': ubahData, '.btn-shape-area': ubahBentuk, '.btn-delete-area': hapusArea };
            Object.entries(aksi).forEach(([kelas, fn]) => {
                const tombol = el.querySelector(kelas);
                tombol?.addEventListener('click', () => fn(Number(tombol.dataset.id)));
            });
        });

        if (props.fokus) bukaFokus(props.fokus);
    },
});

function popupArea(a) {
    return '<div style="min-width:220px;font-size:12px">'
        + '<strong style="font-size:13px">' + escapeHtml(a.name) + '</strong><br><span class="text-gray-500">' + escapeHtml(a.code) + '</span><hr class="my-1">'
        + '<div><b>Depo:</b> ' + escapeHtml(a.warehouse || '-') + '</div>'
        + '<div><b>Wilayah Depo:</b> ' + escapeHtml(a.branch_area || '-') + '</div>'
        + '<div><b>Kelurahan:</b> ' + escapeHtml(a.village || '-') + ', ' + escapeHtml(a.district || '-') + '</div>'
        + '<div><b>Kota:</b> ' + escapeHtml(a.city || '-') + '</div>'
        + '<div><b>Jumlah Toko:</b> ' + (a.outlet_count ?? 0) + '</div>'
        + '<div><b>Sales:</b> ' + escapeHtml(a.salesperson || '-') + '</div>'
        + (a.info ? '<div><b>Info:</b> ' + escapeHtml(a.info) + '</div>' : '')
        + '<div class="flex flex-wrap gap-1 mt-2">'
        + (props.bisaUbah ? '<button type="button" class="btn btn-sm btn-outline-primary btn-edit-area" data-id="' + a.id + '">' + ikonPensil + 'Ubah Data</button>'
            + '<button type="button" class="btn btn-sm btn-outline-primary btn-shape-area" data-id="' + a.id + '">' + ikonBentuk + 'Ubah Bentuk</button>' : '')
        + (props.bisaHapus ? '<button type="button" class="btn btn-sm btn-outline-danger btn-delete-area" data-id="' + a.id + '">' + ikonTempatSampah + 'Hapus</button>' : '')
        + '</div></div>';
}

function gambarArea() {
    lapisanArea.clearLayers();
    overlay.clear();
    areas.value.forEach((a, i) => {
        // Area hasil Generate Polygon Kelurahan punya geometri utuh (MultiPolygon, semua pulau); area lain satu cincin.
        const latlngs = a.geometri?.coordinates
            ? multiKeLatLng(a.geometri.coordinates)
            : (a.polygon || []).map(([lng, lat]) => [lat, lng]);
        if (!a.geometri && latlngs.length < 3) return;
        const warnaArea = warna[(a.warehouse_id || i) % warna.length];
        // Popup dibuka manual: bindPopup menghentikan klik di polygon, sehingga saat menggambar titik di atas
        // area lama tidak bertambah dan peta bergeser karena popup. Saat menggambar, klik diteruskan ke peta.
        const poly = L.polygon(latlngs, { color: warnaArea, weight: 2, fillOpacity: 0.25 })
            .on('click', (e) => { if (!sibuk.value) bukaPopup(a, e.latlng); });
        poly.addTo(lapisanArea);
        overlay.set(a.id, poly);
    });
    if (lapisanArea.getLayers().length) peta.fitBounds(lapisanArea.getBounds(), { padding: [20, 20] });
}

function gambarDepo(warehouses) {
    lapisanDepo.clearLayers();
    warehouses.forEach((w) => {
        if (!w.lat || !w.lng) return;
        L.marker([w.lat, w.lng], { icon: ikonLingkaran('#6f42c1', ikonDepo), zIndexOffset: 500 })
            .bindPopup('<b>' + escapeHtml(w.name) + '</b><br><small>Depo</small>')
            .addTo(lapisanDepo);
    });
}

function bukaPopup(a, latlng) {
    L.popup().setLatLng(latlng).setContent(popupArea(a)).openOn(peta);
}

function fokusArea(a) {
    const poly = overlay.get(a.id);
    if (!poly) return;
    peta.fitBounds(poly.getBounds(), { maxZoom: 17 });
    bukaPopup(a, poly.getBounds().getCenter());
}

// ---------- Filter wilayah ----------
async function ambilOpsi(level) {
    if (!filter.province) return [];
    const params = new URLSearchParams(bersihkanQuery({ ...filter, level }));
    return fetch(props.optionsUrl + '?' + params).then((r) => r.json());
}

// Mengganti satu level mereset semua level di bawahnya, lalu mengisi dropdown level berikutnya.
async function ubahWilayah(i, nilai) {
    filter[levels[i]] = nilai;
    levels.slice(i + 1).forEach((anak) => {
        filter[anak] = '';
        opsi[anak] = [];
    });
    const berikut = levels[i + 1];
    if (!berikut) return;
    const hasil = await ambilOpsi(berikut);
    // Respons lama dibuang kalau induknya sudah diganti selama menunggu.
    if (filter[levels[i]] === nilai) opsi[berikut] = hasil;
}

function resetFilter() {
    levels.forEach((l) => { filter[l] = ''; });
    levels.slice(1).forEach((l) => { opsi[l] = []; });
    areas.value = [];
    lapisanArea.clearLayers();
    lapisanDepo.clearLayers();
}

async function muatArea() {
    if (!filter.province) { alert('Harap pilih Provinsi terlebih dahulu.'); return; }
    memuat.value = true;
    try {
        const res = await fetch(props.areasUrl + '?' + new URLSearchParams(bersihkanQuery({ ...filter })), { headers: { Accept: 'application/json' } });
        if (!res.ok) { alert('Gagal memuat data area.'); return; }
        const data = await res.json();
        areas.value = data.areas;
        gambarArea();
        gambarDepo(data.warehouses);
    } catch {
        alert('Gagal memuat data area.');
    } finally {
        memuat.value = false;
    }
}

// Buka area dari ?area=<id>: isi filter wilayahnya, muat, lalu fokus.
async function bukaFokus(f) {
    if (!f.province) return;
    for (const [i, l] of levels.entries()) {
        if (!f[l]) break;
        await ubahWilayah(i, f[l]);
    }
    await muatArea();
    const a = areas.value.find((x) => x.id === f.id);
    if (a) fokusArea(a);
}

// ---------- Hapus area ----------
async function hapusArea(id) {
    const a = areas.value.find((x) => x.id === id);
    const catatan = a && (a.outlet_count || a.salesperson) ? `\nArea ini punya ${a.outlet_count ?? 0} toko${a.salesperson ? ' dan sales ' + a.salesperson : ''}.` : '';
    if (!confirm(`Hapus area ${a ? a.name + ' (' + a.code + ')' : 'ini'}?${catatan}`)) return;
    const res = await fetch(props.destroyUrl + '/' + id, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' } });
    if (!res.ok) { alert('Gagal menghapus area.'); return; }
    peta.closePopup();
    areas.value = areas.value.filter((x) => x.id !== id);
    gambarArea();
    statusGambar.value = 'Area ' + (a?.code ?? '') + ' dihapus.';
}

// ---------- Ubah data area ----------
function ubahData(id) {
    const a = areas.value.find((x) => x.id === id);
    if (!a) return;
    peta.closePopup();
    areaDiubah.value = a;
    Object.assign(formSimpan, { name: a.name, warehouse_id: a.warehouse_id ?? '', info: a.info ?? '' });
    modalSimpan.value = true;
}

async function kirimUbah(a, tambahan = {}) {
    const res = await fetch(props.updateUrl + '/' + a.id, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
        body: JSON.stringify({ name: a.name, warehouse_id: a.warehouse_id || null, info: a.info || null, ...tambahan }),
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        alert(data.message || 'Gagal menyimpan perubahan area.');
        return null;
    }
    areas.value = areas.value.map((x) => (x.id === data.id ? data : x));
    gambarArea();
    return data;
}

// ---------- Ubah bentuk area ----------
function ubahBentuk(id) {
    const a = areas.value.find((x) => x.id === id);
    if (!a) return;
    peta.closePopup();
    const multi = a.geometri?.coordinates ?? [[[...(a.polygon || []), a.polygon?.[0]].filter(Boolean)]];
    mengedit.value = a;
    overlay.get(a.id)?.remove();
    editor.mulai(multi);
}

function urungkanBentuk() {
    editor?.urungkan();
}

function batalBentuk() {
    editor.batal();
    mengedit.value = null;
    gambarArea();
}

async function simpanBentuk() {
    const a = mengedit.value;
    menyimpanBentuk.value = true;
    try {
        const hasil = await kirimUbah(a, { geometri: editor.hasil() });
        if (!hasil) return;
        editor.batal();
        mengedit.value = null;
        gambarArea();
        statusGambar.value = 'Bentuk area ' + hasil.code + ' tersimpan.';
    } finally {
        menyimpanBentuk.value = false;
    }
}

// ---------- Gambar polygon baru ----------
function gambarTitik() {
    lapisanGambar.clearLayers();
    if (titik.value.length > 1) L.polygon(titik.value, { color: '#dc3545', dashArray: '6 4', fillOpacity: 0.15 }).addTo(lapisanGambar);
    titik.value.forEach((p, i) => L.circleMarker(p, { radius: i === 0 ? 7 : 5, color: '#fff', weight: 2, fillColor: i === 0 ? '#dc3545' : '#0d6efd', fillOpacity: 1 }).addTo(lapisanGambar));
}

// Area tersimpan jadi lebih transparan saat mode menggambar, supaya titik baru terlihat jelas.
watch(sibuk, (on) => {
    lapisanArea?.eachLayer((l) => l.setStyle({ fillOpacity: on ? 0.08 : 0.25, opacity: on ? 0.4 : 1 }));
});

function mulaiGambar() {
    if (mengedit.value) return;
    if (!filter.province) { alert('Pilih Provinsi dulu supaya area tersimpan di wilayah yang benar.'); return; }
    peta.closePopup();
    titik.value = [];
    menggambar.value = true;
    gambarTitik();
}

function batalGambar() {
    titik.value = [];
    lapisanGambar.clearLayers();
    menggambar.value = false;
    statusGambar.value = 'Menggambar dibatalkan.';
}

function selesaiGambar() {
    if (titik.value.length < 3) { alert('Polygon minimal 3 titik.'); return; }
    areaDiubah.value = null;
    modalSimpan.value = true;
}

function tutupModal() {
    modalSimpan.value = false;
    if (areaDiubah.value) {
        areaDiubah.value = null;
        Object.assign(formSimpan, { name: '', warehouse_id: '', info: '' });
    }
}

async function simpan() {
    if (areaDiubah.value) {
        const hasil = await kirimUbah({ ...areaDiubah.value, ...formSimpan });
        if (!hasil) return;
        modalSimpan.value = false;
        areaDiubah.value = null;
        Object.assign(formSimpan, { name: '', warehouse_id: '', info: '' });
        statusGambar.value = 'Data area ' + hasil.code + ' tersimpan.';
        return;
    }
    const body = {
        ...bersihkanQuery({ ...filter }),
        name: formSimpan.name,
        warehouse_id: formSimpan.warehouse_id || null,
        info: formSimpan.info || null,
        polygon: titik.value.map(([lat, lng]) => [lng, lat]),
    };
    const res = await fetch(props.storeUrl, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify(body) });
    if (!res.ok) { alert('Gagal menyimpan area.'); return; }
    const tersimpan = await res.json();
    modalSimpan.value = false;
    Object.assign(formSimpan, { name: '', warehouse_id: '', info: '' });
    titik.value = [];
    lapisanGambar.clearLayers();
    menggambar.value = false;
    statusGambar.value = 'Area ' + tersimpan.code + ' tersimpan.';
    muatArea();
}

// ---------- Cari alamat ----------
function cariAlamat(e) {
    if (e.key !== 'Enter') return;
    e.preventDefault();
    fetch('https://nominatim.openstreetmap.org/search?format=json&limit=1&countrycodes=id&q=' + encodeURIComponent(e.target.value))
        .then((r) => r.json())
        .then((d) => { if (d[0]) peta.setView([parseFloat(d[0].lat), parseFloat(d[0].lon)], 17); });
}
</script>

<template>
    <Head :title="`${title} · ${appName}`" />
    <AppLayout :breadcrumb-slug="slug">
        <PageHeader :title="title">
            <template #actions>
                <Button v-if="autoGridUrl" :tag="Link" :href="autoGridUrl" variant="outline-primary"><Icon name="map" class="me-1" />Auto Grid</Button>
                <Button v-if="generateUrl" :tag="Link" :href="generateUrl" variant="outline-primary"><Icon name="layers" class="me-1" />Generate Polygon Kelurahan</Button>
                <Link :href="kembaliUrl" class="text-gray-500 hover:underline">Kembali</Link>
            </template>
        </PageHeader>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-2 mb-2">
            <div class="lg:col-span-5">
                <Card class="h-full" body-class="card-body card-body-compact">
                    <template #header><h6 class="mb-0 font-semibold">Filter &amp; Zoom Area</h6></template>

                    <Label for="map_search" class="mb-1"><Icon name="map-pin" class="me-1" />Cari Alamat di Peta</Label>
                    <Input id="map_search" class="mb-2" placeholder="Ketik alamat lalu tekan Enter..." autocomplete="off" @keydown="cariAlamat" />

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <Label for="filter_province" class="mb-1">Provinsi <span class="text-red-600">*</span></Label>
                            <Select id="filter_province" :model-value="filter.province" @update:model-value="ubahWilayah(0, $event)">
                                <option value="">Pilih Provinsi</option>
                                <option v-for="p in provinces" :key="p" :value="p">{{ p }}</option>
                            </Select>
                        </div>
                        <div>
                            <Label for="filter_city" class="mb-1">Kota / Kabupaten</Label>
                            <Select id="filter_city" :model-value="filter.city" @update:model-value="ubahWilayah(1, $event)">
                                <option value="">Semua</option>
                                <option v-for="k in opsi.city" :key="k" :value="k">{{ k }}</option>
                            </Select>
                        </div>
                        <div>
                            <Label for="filter_district" class="mb-1">Kecamatan</Label>
                            <Select id="filter_district" :model-value="filter.district" @update:model-value="ubahWilayah(2, $event)">
                                <option value="">Semua</option>
                                <option v-for="k in opsi.district" :key="k" :value="k">{{ k }}</option>
                            </Select>
                        </div>
                        <div>
                            <Label for="filter_village" class="mb-1">Kelurahan / Desa</Label>
                            <Select id="filter_village" :model-value="filter.village" @update:model-value="ubahWilayah(3, $event)">
                                <option value="">Semua</option>
                                <option v-for="k in opsi.village" :key="k" :value="k">{{ k }}</option>
                            </Select>
                        </div>
                        <div class="col-span-2 flex gap-2">
                            <Button variant="primary" class="grow" id="btn_filter" @click="muatArea"><Icon name="search" class="me-1" />Tampilkan</Button>
                            <Button variant="outline-secondary" id="btn_reset" @click="resetFilter"><Icon name="undo" class="me-1" />Reset</Button>
                        </div>
                    </div>
                </Card>
            </div>

            <div class="lg:col-span-7">
                <Card class="h-full" body-class="p-0">
                    <template #header><h6 class="mb-0 font-semibold">Area Tersimpan ({{ areas.length }})</h6></template>
                    <div class="card-body card-body-compact pb-0">
                        <Input v-model="cari" type="search" class="mb-2" placeholder="Cari area..." />
                    </div>
                    <div class="h-50 overflow-y-auto">
                        <div class="list-group list-group-flush">
                            <div v-if="memuat" class="list-group-item text-center py-4"><span class="spinner me-2"></span>Memuat data...</div>
                            <div v-else-if="!areas.length" class="list-group-item text-center text-gray-500 py-4">Klik "Tampilkan" untuk memuat data area</div>
                            <div v-else-if="!tampil.length" class="list-group-item text-center text-gray-500 py-4">Tidak ada area</div>
                            <template v-else>
                                <button
                                    v-for="a in tampil.slice(0, 300)"
                                    :key="a.id"
                                    type="button"
                                    class="list-group-item list-group-item-action w-full text-left py-1"
                                    @click="fokusArea(a)"
                                >
                                    <b>{{ a.name }}</b> <span class="text-gray-500">{{ a.code }}</span><br>
                                    <small class="text-gray-500">{{ a.village || '-' }}, {{ a.district || '-' }} &middot; {{ a.outlet_count ?? 0 }} toko</small>
                                </button>
                                <div v-if="jumlahTersembunyi" class="list-group-item text-center text-gray-500 text-xs">{{ jumlahTersembunyi }} area lainnya, persempit filter atau pencarian</div>
                            </template>
                        </div>
                    </div>
                </Card>
            </div>
        </div>

        <Card class="mb-2" body-class="card-body card-body-compact flex items-center gap-2 flex-wrap">
            <template #header><h6 class="mb-0 font-semibold"><Icon name="shapes" class="me-1" />{{ mengedit ? 'Ubah Bentuk Area' : 'Gambar Polygon Baru' }}</h6></template>

            <template v-if="mengedit">
                <Button variant="primary" id="btn_save_shape" :disabled="menyimpanBentuk" @click="simpanBentuk"><Icon name="check" class="me-1" />Simpan Bentuk</Button>
                <Button variant="outline-warning" id="btn_undo_shape" :disabled="!infoEdit.bisaUrung" @click="urungkanBentuk"><Icon name="undo" class="me-1" />Urungkan</Button>
                <Button variant="outline-danger" id="btn_cancel_shape" @click="batalBentuk"><Icon name="x" class="me-1" />Batal</Button>
            </template>
            <template v-else-if="bisaUbah">
                <Button v-if="!menggambar" variant="success" id="btn_start_draw" @click="mulaiGambar"><Icon name="pen" class="me-1" />Mulai Gambar</Button>
                <template v-else>
                    <Button variant="primary" id="btn_finish_draw" @click="selesaiGambar"><Icon name="check" class="me-1" />Selesai</Button>
                    <Button variant="outline-warning" id="btn_undo_point" @click="() => { titik.pop(); gambarTitik(); }"><Icon name="undo" class="me-1" />Hapus Titik Terakhir</Button>
                    <Button variant="outline-danger" id="btn_cancel_draw" @click="batalGambar"><Icon name="x" class="me-1" />Batal</Button>
                </template>
            </template>
            <Badge :variant="sibuk ? 'danger' : 'secondary'" id="map_mode_badge">{{ mengedit ? 'Mode: Ubah Bentuk' : menggambar ? 'Mode: Menggambar' : 'Mode: Lihat' }}</Badge>
            <small v-if="mengedit" class="text-gray-500" id="draw_status">
                <b>{{ mengedit.name }}</b> · {{ infoEdit.titik.toLocaleString('id-ID') }} titik.
                <template v-if="infoEdit.tampil">Seret titik untuk memindah, klik titik untuk menghapus, seret/klik titik tengah untuk menambah.</template>
                <span v-else class="text-amber-700">Lebih dari {{ MAKS_PENANDA }} titik di layar: perbesar peta untuk mengedit titik.</span>
            </small>
            <small v-else class="text-gray-500" id="draw_status">{{ pesanGambar }}</small>
        </Card>

        <Card body-class="card-body p-2">
            <!-- Class mode menggambar di pembungkus: class pada elemen peta sendiri akan menimpa class milik Leaflet. -->
            <div :class="{ 'peta-menggambar': menggambar }">
                <div ref="wadah" class="h-155 rounded-md"></div>
            </div>
        </Card>

        <Modal :show="modalSimpan" :title="areaDiubah ? `Ubah Data Area ${areaDiubah.code}` : 'Simpan Area Baru'" @close="tutupModal">
            <form id="save_form" @submit.prevent="simpan">
                <div class="mb-2">
                    <Label>Nama Area <span class="text-red-600">*</span></Label>
                    <Input v-model="formSimpan.name" required />
                </div>
                <div class="mb-2">
                    <Label>Depo</Label>
                    <Select v-model="formSimpan.warehouse_id">
                        <option value="">-</option>
                        <option v-for="w in warehouses" :key="w.warehouse_id" :value="w.warehouse_id">{{ w.name }}</option>
                    </Select>
                </div>
                <div class="mb-2">
                    <Label>Keterangan</Label>
                    <Textarea v-model="formSimpan.info" rows="2" />
                </div>
                <small v-if="areaDiubah" class="text-gray-500">Wilayah: {{ [areaDiubah.village, areaDiubah.district, areaDiubah.city, areaDiubah.province].filter(Boolean).join(', ') }}</small>
                <small v-else class="text-gray-500">Wilayah mengikuti filter: <span id="save_region">{{ wilayahSimpan }}</span></small>
            </form>
            <template #footer>
                <Button variant="light" @click="tutupModal">Batal</Button>
                <Button variant="primary" type="submit" form="save_form">Simpan</Button>
            </template>
        </Modal>
    </AppLayout>
</template>
