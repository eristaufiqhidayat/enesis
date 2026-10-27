<script setup>
// Peta Sebaran Sales: polygon Master Area se-Indonesia dan nama sales di dalam tiap area.
// - zoom < 11: satu lingkaran per depo (jumlah area; tooltip jumlah sales & area tanpa sales).
// - zoom ≥ 11: polygon area di layar diambil dari server (kotak batas), warna menurut penugasan sales.
// - zoom ≥ 13: nama sales tampil di dalam tiap area.
// Lapisan tambahan: posisi GPS terakhir sales (Tracking Last Seen).
import { computed, onMounted, ref, watch } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from '@/Components/ui/atoms/Button.vue';
import Checkbox from '@/Components/ui/atoms/Checkbox.vue';
import Icon from '@/Components/ui/atoms/Icon.vue';
import Input from '@/Components/ui/atoms/Input.vue';
import Label from '@/Components/ui/atoms/Label.vue';
import Select from '@/Components/ui/atoms/Select.vue';
import Alert from '@/Components/ui/molecules/Alert.vue';
import Card from '@/Components/ui/molecules/Card.vue';
import PageHeader from '@/Components/ui/molecules/PageHeader.vue';
import { useLeafletMap } from '@/Composables/useLeafletMap.js';
import { escapeHtml, multiKeLatLng } from '@/Support/peta.js';
import { angka } from '@/Support/format.js';

const props = defineProps({
    slug: { type: String, required: true },
    title: { type: String, required: true },
    dataUrl: { type: String, required: true },
    areaUrl: { type: String, required: true },
    lastSeenUrl: { type: String, required: true },
    masterAreaUrl: { type: String, required: true },
    designUrl: { type: String, required: true },
});

const appName = usePage().props.appName;
const ZOOM_AREA = 11;
const ZOOM_LABEL = 13;
const MAKS_DAFTAR = 300;

const WARNA_AREA = {
    aktif: { color: '#1d4ed8', fillColor: '#3b82f6', fillOpacity: 0.28 },
    nonaktif: { color: '#b45309', fillColor: '#f59e0b', fillOpacity: 0.2 },
    kosong: { color: '#6b7280', fillColor: '#d1d5db', fillOpacity: 0.12, dashArray: '4 3' },
};
const STATUS_GPS = [
    { id: 'hari_ini', label: 'Terlihat hari ini', warna: '#16a34a' },
    { id: 'minggu', label: '1–7 hari lalu', warna: '#f59e0b' },
    { id: 'lama', label: 'Lebih dari 7 hari', warna: '#6b7280' },
];
const warnaGps = Object.fromEntries(STATUS_GPS.map((s) => [s.id, s.warna]));

// ---------- Data ringkasan ----------
const sales = ref([]);
const depo = ref([]);
const diperbarui = ref(null);
const memuat = ref(false);
const galat = ref(null);

const cari = ref('');
const depoDipilih = ref('');
const tampilGps = ref(false);
const salesDipilih = ref(null);

const info = ref('');
const memuatArea = ref(false);

const opsiDepo = computed(() => depo.value.filter((d) => d.id !== null).sort((a, b) => a.nama.localeCompare(b.nama)).map((d) => ({ value: d.id ?? '', label: `${d.nama} (${angka(d.jumlahArea)} area)` })));
const totalArea = computed(() => depo.value.reduce((n, d) => n + d.jumlahArea, 0));
const totalTanpaSales = computed(() => depo.value.reduce((n, d) => n + d.tanpaSales, 0));
const daftarSales = computed(() => {
    const q = cari.value.trim().toLowerCase();
    return sales.value.filter((s) => (!depoDipilih.value || String(s.depoId) === String(depoDipilih.value))
        && (!q || [s.nama, s.depo, s.koordinator].filter(Boolean).join(' ').toLowerCase().includes(q)));
});

async function muat() {
    memuat.value = true;
    galat.value = null;
    try {
        const res = await fetch(props.dataUrl, { headers: { Accept: 'application/json' } });
        if (!res.ok) throw new Error();
        const data = await res.json();
        sales.value = data.sales;
        depo.value = data.depo;
        diperbarui.value = data.diperbarui;
        gambarDepo();
        gambarGps();
        if (petaLeaflet && depo.value.length) petaLeaflet.fitBounds(batasDepo(depo.value), { padding: [20, 20] });
        muatAreaLayar();
    } catch {
        galat.value = 'Gagal memuat data peta.';
    } finally {
        memuat.value = false;
    }
}
onMounted(muat);

// ---------- Peta ----------
let L = null;
let petaLeaflet = null;
let lapisDepo = null;
let lapisArea = null;
let lapisLabel = null;
let lapisGps = null;
let lapisSorot = null;
let kanvas = null;
let pengendali = null;
let jedaGeser = null;

const wadah = ref(null);
useLeafletMap(wadah, {
    pusat: [-2.5, 118],
    zoom: 5,
    onSiap(map, Lib) {
        L = Lib;
        petaLeaflet = map;
        kanvas = L.canvas({ padding: 0.3 });
        lapisDepo = L.layerGroup().addTo(map);
        lapisArea = L.layerGroup().addTo(map);
        lapisLabel = L.layerGroup().addTo(map);
        lapisGps = L.layerGroup();
        lapisSorot = L.layerGroup().addTo(map);
        map.on('moveend', () => {
            clearTimeout(jedaGeser);
            jedaGeser = setTimeout(muatAreaLayar, 250);
        });
        gambarDepo();
        gambarGps();
        if (depo.value.length) map.fitBounds(batasDepo(depo.value), { padding: [20, 20] });
    },
});

const batasDepo = (daftar) => L.latLngBounds(daftar.map((d) => [[d.kotak[1], d.kotak[0]], [d.kotak[3], d.kotak[2]]]).flat());

function gambarDepo() {
    if (!lapisDepo) return;
    lapisDepo.clearLayers();
    depo.value.filter((d) => !depoDipilih.value || String(d.id) === String(depoDipilih.value)).forEach((d) => {
        const ukuran = Math.round(28 + Math.min(34, Math.sqrt(d.jumlahArea)));
        L.marker([d.lat, d.lng], {
            icon: L.divIcon({
                className: '',
                iconSize: [ukuran, ukuran],
                iconAnchor: [ukuran / 2, ukuran / 2],
                html: '<div class="klaster-peta klaster-peta-area" style="width:' + ukuran + 'px;height:' + ukuran + 'px">' + angka(d.jumlahArea) + '</div>',
            }),
        })
            .bindTooltip('<b>' + escapeHtml(d.nama) + '</b><br><small>' + angka(d.jumlahArea) + ' area · ' + angka(d.jumlahSales) + ' sales bertugas<br>'
                + angka(d.tanpaSales) + ' area belum ada sales<br>Klik untuk memperbesar</small>')
            .on('click', () => petaLeaflet.fitBounds(batasDepo([d]), { padding: [20, 20] }))
            .addTo(lapisDepo);
    });
    aturLapisZoom();
}

function aturLapisZoom() {
    if (!petaLeaflet) return;
    const z = petaLeaflet.getZoom();
    const tampil = (lapis, on) => {
        if (on && !petaLeaflet.hasLayer(lapis)) lapis.addTo(petaLeaflet);
        if (!on && petaLeaflet.hasLayer(lapis)) petaLeaflet.removeLayer(lapis);
    };
    tampil(lapisDepo, z < ZOOM_AREA);
    tampil(lapisArea, z >= ZOOM_AREA);
    tampil(lapisLabel, z >= ZOOM_LABEL);
    tampil(lapisGps, tampilGps.value);
}

function jenisArea(a) {
    if (a.sales.some((s) => s.aktif)) return 'aktif';
    return a.sales.length ? 'nonaktif' : 'kosong';
}

function popupArea(a) {
    const daftar = a.sales.length
        ? a.sales.map((s) => '<li>' + escapeHtml(s.nama) + (s.aktif ? '' : ' <span class="text-gray-500">(tidak aktif)</span>') + '</li>').join('')
        : '<li class="text-gray-500">Belum ada sales</li>';
    return '<div style="min-width:230px;font-size:12px">'
        + '<strong style="font-size:13px">' + escapeHtml(a.nama) + '</strong><br><span class="text-gray-500">Master Area: ' + escapeHtml(a.kode) + '</span><hr class="my-1">'
        + '<div><b>Depo:</b> ' + escapeHtml(a.depo ?? '-') + '</div>'
        + (a.wilayah ? '<div><b>Wilayah:</b> ' + escapeHtml(a.wilayah) + '</div>' : '')
        + '<div class="mt-1"><b>Sales:</b><ul class="ms-4 list-disc">' + daftar + '</ul></div>'
        + '<div class="flex flex-wrap gap-1 mt-2">'
        + '<a class="btn btn-sm btn-outline-primary" href="' + props.masterAreaUrl + '?q=' + encodeURIComponent(a.kode) + '">Buka di Master Area</a>'
        + '<a class="btn btn-sm btn-outline-primary" href="' + props.designUrl + '?area=' + a.id + '">Lihat di Design Area</a>'
        + '</div></div>';
}

function labelSales(a) {
    if (!a.sales.length) return null;
    const nama = a.sales.slice(0, 2).map((s) => escapeHtml(s.nama)).join('<br>');
    return nama + (a.sales.length > 2 ? '<br>+' + (a.sales.length - 2) + ' lainnya' : '');
}

function gambarArea(area, lapis, sorot = false) {
    area.forEach((a) => {
        const gaya = sorot
            ? { color: '#dc2626', weight: 3, fillColor: '#ef4444', fillOpacity: 0.25 }
            : { ...WARNA_AREA[jenisArea(a)], weight: 1.2, renderer: kanvas };
        const poly = L.polygon(multiKeLatLng(a.geometri), gaya)
            .bindTooltip('<b>' + escapeHtml(a.kode) + '</b> · ' + escapeHtml(a.nama) + '<br><small>' + (a.sales.length ? a.sales.map((s) => escapeHtml(s.nama)).join(', ') : 'Belum ada sales') + '</small>', { sticky: true })
            .on('click', (e) => L.popup().setLatLng(e.latlng).setContent(popupArea(a)).openOn(petaLeaflet));
        poly.addTo(lapis);
        const teks = labelSales(a);
        if (teks && !sorot) {
            L.marker(poly.getBounds().getCenter(), {
                interactive: false,
                icon: L.divIcon({ className: 'label-area-peta', html: '<span>' + teks + '</span>', iconSize: null }),
            }).addTo(lapisLabel);
        }
    });
}

async function muatAreaLayar() {
    if (!petaLeaflet) return;
    if (petaLeaflet.getZoom() < ZOOM_AREA) {
        lapisArea.clearLayers();
        lapisLabel.clearLayers();
        info.value = `Perbesar peta (zoom ${ZOOM_AREA}+) untuk melihat polygon area; zoom ${ZOOM_LABEL}+ untuk nama sales.`;
        aturLapisZoom();
        return;
    }
    const b = petaLeaflet.getBounds();
    const bbox = [b.getWest(), b.getSouth(), b.getEast(), b.getNorth()].map((n) => n.toFixed(5)).join(',');
    pengendali?.abort();
    pengendali = new AbortController();
    memuatArea.value = true;
    try {
        const res = await fetch(props.areaUrl + '?' + new URLSearchParams({ bbox }), { headers: { Accept: 'application/json' }, signal: pengendali.signal });
        if (!res.ok) throw new Error();
        const data = await res.json();
        lapisArea.clearLayers();
        lapisLabel.clearLayers();
        if (data.terlaluBanyak) {
            info.value = `${angka(data.jumlah)} area di layar — perbesar peta sedikit lagi.`;
        } else {
            gambarArea(data.area, lapisArea);
            const kosong = data.area.filter((a) => !a.sales.length).length;
            info.value = `${angka(data.area.length)} area di layar · ${angka(kosong)} belum ada sales.`
                + (petaLeaflet.getZoom() < ZOOM_LABEL ? ` Perbesar ke zoom ${ZOOM_LABEL}+ untuk nama sales.` : '');
        }
        aturLapisZoom();
    } catch (e) {
        if (e.name !== 'AbortError') info.value = 'Gagal memuat area.';
    } finally {
        memuatArea.value = false;
    }
}

function gambarGps() {
    if (!lapisGps) return;
    lapisGps.clearLayers();
    sales.value.forEach((s) => {
        if (s.lat === null || s.lng === null || !warnaGps[s.status]) return;
        L.circleMarker([s.lat, s.lng], { renderer: kanvas, radius: 5, color: '#ffffff', weight: 1.5, fillColor: warnaGps[s.status], fillOpacity: 0.95 })
            .bindTooltip('<b>' + escapeHtml(s.nama) + '</b><br><small>GPS terakhir: ' + escapeHtml(s.waktu ?? '-') + (s.sejak ? ' (' + escapeHtml(s.sejak) + ')' : '') + '</small>')
            .addTo(lapisGps);
    });
    aturLapisZoom();
}

watch(tampilGps, aturLapisZoom);
watch(depoDipilih, () => {
    gambarDepo();
    const d = depo.value.find((x) => String(x.id) === String(depoDipilih.value));
    if (d && petaLeaflet) petaLeaflet.fitBounds(batasDepo([d]), { padding: [20, 20] });
});

// Pilih sales di daftar: ambil semua areanya, sorot merah, dan perbesar ke sana.
async function pilihSales(s) {
    salesDipilih.value = s;
    lapisSorot.clearLayers();
    const res = await fetch(props.areaUrl + '?' + new URLSearchParams({ karyawan_id: s.id }), { headers: { Accept: 'application/json' } });
    const data = res.ok ? await res.json() : { area: [] };
    if (!data.area.length) {
        info.value = `${s.nama} belum punya area dengan polygon.`;
        return;
    }
    gambarArea(data.area, lapisSorot, true);
    petaLeaflet.fitBounds(L.featureGroup(lapisSorot.getLayers()).getBounds(), { padding: [30, 30], maxZoom: 15 });
}

function lepasSorot() {
    salesDipilih.value = null;
    lapisSorot?.clearLayers();
}
</script>

<template>
    <Head :title="`${title} · ${appName}`" />
    <AppLayout :breadcrumb-slug="slug">
        <PageHeader
            :title="title"
            icon="map"
            :subtitle="diperbarui ? `${angka(totalArea)} area Master Area · ${angka(totalTanpaSales)} belum ada sales · ${angka(sales.length)} sales aktif` : 'Polygon Master Area se-Indonesia dan sales yang ditugaskan di dalamnya'"
        >
            <template #actions>
                <Button variant="outline-primary" :loading="memuat" @click="muat"><Icon name="undo" class="me-1" />Muat ulang</Button>
                <Button :tag="Link" :href="masterAreaUrl" variant="outline-secondary"><Icon name="clipboard-list" class="me-1" />Master Area</Button>
            </template>
        </PageHeader>

        <Alert v-if="galat" variant="danger" class="mb-2">{{ galat }}</Alert>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-2">
            <div class="lg:col-span-3 flex flex-col gap-2">
                <Card body-class="card-body card-body-compact">
                    <template #header><h6 class="mb-0 font-semibold">Saring</h6></template>
                    <Label for="depo_area" class="mb-1">Depo</Label>
                    <Select id="depo_area" v-model="depoDipilih" :options="opsiDepo" placeholder="Seluruh Indonesia" class="mb-2" />
                    <label class="flex items-center gap-2 cursor-pointer text-sm">
                        <Checkbox v-model="tampilGps" />
                        <span>Tampilkan posisi GPS terakhir sales</span>
                    </label>
                    <div v-if="tampilGps" class="flex flex-wrap gap-x-3 gap-y-1 text-xs text-gray-500 mt-1">
                        <span v-for="s in STATUS_GPS" :key="s.id"><span class="legenda-peta legenda-peta-titik" :style="{ background: s.warna }"></span>{{ s.label }}</span>
                    </div>
                </Card>

                <Card body-class="p-0">
                    <template #header>
                        <div class="flex items-center justify-between gap-2">
                            <h6 class="mb-0 font-semibold">Sales ({{ angka(daftarSales.length) }})</h6>
                            <Button v-if="salesDipilih" size="sm" variant="light" @click="lepasSorot">Lepas sorotan</Button>
                        </div>
                    </template>
                    <div class="card-body card-body-compact pb-0">
                        <Input v-model="cari" type="search" class="mb-2" placeholder="Cari nama sales, depo, koordinator…" aria-label="Cari sales" />
                    </div>
                    <div class="max-h-96 overflow-y-auto">
                        <div class="list-group list-group-flush">
                            <div v-if="memuat && !sales.length" class="list-group-item text-center py-4"><span class="spinner me-2"></span>Memuat…</div>
                            <div v-else-if="!daftarSales.length" class="list-group-item text-center text-gray-500 py-4">Tidak ada sales.</div>
                            <button
                                v-for="s in daftarSales.slice(0, MAKS_DAFTAR)"
                                :key="s.id"
                                type="button"
                                class="list-group-item list-group-item-action w-full text-left"
                                :class="{ 'list-group-item-terpilih': salesDipilih?.id === s.id }"
                                :disabled="!s.jumlahArea"
                                @click="pilihSales(s)"
                            >
                                <span class="font-semibold">{{ s.nama }}</span><br>
                                <small class="text-gray-500">{{ s.depo ?? '-' }} · {{ s.jumlahArea ? angka(s.jumlahArea) + ' area' : 'belum ada area' }}</small>
                            </button>
                            <div v-if="daftarSales.length > MAKS_DAFTAR" class="list-group-item text-center text-gray-500 text-xs">
                                {{ angka(daftarSales.length - MAKS_DAFTAR) }} sales lainnya, persempit pencarian.
                            </div>
                        </div>
                    </div>
                </Card>
            </div>

            <div class="lg:col-span-9">
                <Card body-class="card-body p-2">
                    <div ref="wadah" class="h-160 rounded-md" aria-label="Peta sebaran sales dan master area"></div>
                    <div class="flex flex-wrap gap-x-4 gap-y-1 mt-1 text-xs text-gray-500">
                        <span><span class="legenda-peta legenda-peta-aktif"></span>Ada sales aktif (rute HP)</span>
                        <span><span class="legenda-peta legenda-peta-tugas-nonaktif"></span>Hanya penugasan tidak aktif</span>
                        <span><span class="legenda-peta legenda-peta-nonaktif"></span>Belum ada sales</span>
                        <span><span class="legenda-peta legenda-peta-sorot"></span>Area sales terpilih</span>
                    </div>
                    <p class="text-xs text-gray-500 mt-1 mb-0"><span v-if="memuatArea" class="spinner me-1"></span>{{ info }}</p>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
