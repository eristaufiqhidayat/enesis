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
import { escapeHtml, ikonDepo, ikonLingkaran, ikonSvg } from '@/Support/peta.js';

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
});

const appName = usePage().props.appName;
const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

const levels = ['province', 'city', 'district', 'village'];
const warna = ['#e6194b', '#3cb44b', '#4363d8', '#f58231', '#911eb4', '#42d4f4', '#f032e6', '#469990', '#9A6324', '#800000', '#808000', '#000075'];
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
    return `${n} titik. ` + (n < 3 ? 'Tambahkan minimal 3 titik.' : 'Klik "Selesai" untuk menyimpan.');
});

const modalSimpan = ref(false);
const formSimpan = reactive({ name: '', warehouse_id: '', info: '' });
const wilayahSimpan = computed(() => levels.map((l) => filter[l]).filter(Boolean).join(', ') || '-');

// Layer & instance Leaflet bukan state reaktif Vue: disimpan di luar ref supaya tidak di-proxy.
let L = null;
let peta = null;
let lapisanArea = null;
let lapisanDepo = null;
let lapisanGambar = null;
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

        map.on('click', (e) => {
            if (!menggambar.value) return;
            titik.value.push([e.latlng.lat, e.latlng.lng]);
            gambarTitik();
        });

        // Hapus area dari popup. Tombol ada di HTML popup Leaflet, jadi listener dipasang saat popup dibuka.
        map.on('popupopen', (e) => {
            const tombol = e.popup.getElement().querySelector('.btn-delete-area');
            if (!tombol) return;
            tombol.addEventListener('click', () => hapusArea(Number(tombol.dataset.id)));
        });
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
        + (props.bisaHapus ? '<button type="button" class="btn btn-sm btn-outline-danger mt-2 btn-delete-area" data-id="' + a.id + '">' + ikonTempatSampah + 'Hapus</button>' : '')
        + '</div>';
}

function gambarArea() {
    lapisanArea.clearLayers();
    overlay.clear();
    areas.value.forEach((a, i) => {
        const latlngs = (a.polygon || []).map(([lng, lat]) => [lat, lng]);
        if (latlngs.length < 3) return;
        const warnaArea = warna[(a.warehouse_id || i) % warna.length];
        const poly = L.polygon(latlngs, { color: warnaArea, weight: 2, fillOpacity: 0.25 }).bindPopup(popupArea(a));
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

function fokusArea(a) {
    const poly = overlay.get(a.id);
    if (!poly) return;
    peta.fitBounds(poly.getBounds(), { maxZoom: 17 });
    poly.openPopup();
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

// ---------- Hapus area ----------
async function hapusArea(id) {
    if (!confirm('Hapus area ini?')) return;
    const res = await fetch(props.destroyUrl + '/' + id, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': csrf } });
    if (!res.ok) { alert('Gagal menghapus area.'); return; }
    peta.closePopup();
    areas.value = areas.value.filter((a) => a.id !== id);
    gambarArea();
}

// ---------- Gambar polygon baru ----------
function gambarTitik() {
    lapisanGambar.clearLayers();
    if (titik.value.length > 1) L.polygon(titik.value, { color: '#dc3545', dashArray: '6 4', fillOpacity: 0.15 }).addTo(lapisanGambar);
    titik.value.forEach((p, i) => L.circleMarker(p, { radius: i === 0 ? 7 : 5, color: '#fff', weight: 2, fillColor: i === 0 ? '#dc3545' : '#0d6efd', fillOpacity: 1 }).addTo(lapisanGambar));
}

// Area tersimpan jadi lebih transparan saat mode menggambar, supaya titik baru terlihat jelas.
watch(menggambar, (on) => {
    lapisanArea?.eachLayer((l) => l.setStyle({ fillOpacity: on ? 0.08 : 0.25, opacity: on ? 0.4 : 1 }));
});

function mulaiGambar() {
    if (!filter.province) { alert('Pilih Provinsi dulu supaya area tersimpan di wilayah yang benar.'); return; }
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
    modalSimpan.value = true;
}

async function simpan() {
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
            <template #header><h6 class="mb-0 font-semibold"><Icon name="shapes" class="me-1" />Gambar Polygon Baru</h6></template>

            <template v-if="bisaUbah">
                <Button v-if="!menggambar" variant="success" id="btn_start_draw" @click="mulaiGambar"><Icon name="pen" class="me-1" />Mulai Gambar</Button>
                <template v-else>
                    <Button variant="primary" id="btn_finish_draw" @click="selesaiGambar"><Icon name="check" class="me-1" />Selesai</Button>
                    <Button variant="outline-warning" id="btn_undo_point" @click="() => { titik.pop(); gambarTitik(); }"><Icon name="undo" class="me-1" />Hapus Titik Terakhir</Button>
                    <Button variant="outline-danger" id="btn_cancel_draw" @click="batalGambar"><Icon name="x" class="me-1" />Batal</Button>
                </template>
            </template>
            <Badge :variant="menggambar ? 'danger' : 'secondary'" id="map_mode_badge">{{ menggambar ? 'Mode: Menggambar' : 'Mode: Lihat' }}</Badge>
            <small class="text-gray-500" id="draw_status">{{ pesanGambar }}</small>
        </Card>

        <Card body-class="card-body p-2">
            <div ref="wadah" class="h-155 rounded-md" :class="{ 'cursor-crosshair': menggambar }"></div>
        </Card>

        <Modal :show="modalSimpan" title="Simpan Area Baru" @close="modalSimpan = false">
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
                <small class="text-gray-500">Wilayah mengikuti filter: <span id="save_region">{{ wilayahSimpan }}</span></small>
            </form>
            <template #footer>
                <Button variant="light" @click="modalSimpan = false">Batal</Button>
                <Button variant="primary" type="submit" form="save_form">Simpan</Button>
            </template>
        </Modal>
    </AppLayout>
</template>
