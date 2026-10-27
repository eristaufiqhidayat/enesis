<script setup>
// Peta Sebaran Sales: posisi GPS terakhir semua sales aktif se-Indonesia.
// Peta diperkecil (zoom < 9): satu lingkaran per depo berisi jumlah sales; diperbesar: satu titik per sales.
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
import { escapeHtml, ikonDepo, ikonLingkaran } from '@/Support/peta.js';
import { angka } from '@/Support/format.js';

const props = defineProps({
    slug: { type: String, required: true },
    title: { type: String, required: true },
    dataUrl: { type: String, required: true },
    lastSeenUrl: { type: String, required: true },
});

const appName = usePage().props.appName;

const STATUS = [
    { id: 'hari_ini', label: 'Terlihat hari ini', warna: '#16a34a' },
    { id: 'minggu', label: '1–7 hari lalu', warna: '#f59e0b' },
    { id: 'lama', label: 'Lebih dari 7 hari', warna: '#6b7280' },
    { id: 'belum', label: 'Belum ada lokasi', warna: null },
];
const warnaStatus = Object.fromEntries(STATUS.map((s) => [s.id, s.warna]));
const ZOOM_TITIK = 9;
const MAKS_DAFTAR = 300;

const sales = ref([]);
const depo = ref([]);
const diperbarui = ref(null);
const memuat = ref(false);
const galat = ref(null);

const cari = ref('');
const depoDipilih = ref('');
const statusDipilih = ref(['hari_ini', 'minggu', 'lama']);

const hitung = computed(() => Object.fromEntries(STATUS.map((s) => [s.id, sales.value.filter((x) => x.status === s.id).length])));
const opsiDepo = computed(() => {
    const ada = new Map();
    sales.value.forEach((s) => { if (s.depoId) ada.set(s.depoId, s.depo ?? `Depo #${s.depoId}`); });
    return [...ada].sort((a, b) => a[1].localeCompare(b[1])).map(([value, label]) => ({ value, label }));
});

const tersaring = computed(() => {
    const q = cari.value.trim().toLowerCase();
    return sales.value.filter((s) => statusDipilih.value.includes(s.status)
        && (!depoDipilih.value || String(s.depoId) === String(depoDipilih.value))
        && (!q || [s.nama, s.depo, s.koordinator, s.telepon].filter(Boolean).join(' ').toLowerCase().includes(q)));
});
const diPeta = computed(() => tersaring.value.filter((s) => s.lat !== null && s.lng !== null));

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
        gambar(true);
    } catch {
        galat.value = 'Gagal memuat posisi sales.';
    } finally {
        memuat.value = false;
    }
}
onMounted(muat);

// ---------- Peta ----------
let L = null;
let petaLeaflet = null;
let lapisKlaster = null;
let lapisTitik = null;
let lapisDepo = null;
let kanvas = null;
const penanda = new Map();

const wadah = ref(null);
useLeafletMap(wadah, {
    pusat: [-2.5, 118],
    zoom: 5,
    onSiap(map, Lib) {
        L = Lib;
        petaLeaflet = map;
        kanvas = L.canvas({ padding: 0.5 });
        lapisKlaster = L.layerGroup().addTo(map);
        lapisTitik = L.layerGroup();
        lapisDepo = L.layerGroup();
        map.on('zoomend', tampilSesuaiZoom);
        gambar(true);
    },
});

function popupSales(s) {
    const status = STATUS.find((x) => x.id === s.status);
    return '<div style="min-width:220px;font-size:12px">'
        + '<strong style="font-size:13px">' + escapeHtml(s.nama) + '</strong>'
        + '<br><span style="color:' + status.warna + '">● ' + escapeHtml(status.label) + '</span><hr class="my-1">'
        + '<div><b>Depo:</b> ' + escapeHtml(s.depo ?? '-') + '</div>'
        + '<div><b>Koordinator:</b> ' + escapeHtml(s.koordinator ?? '-') + '</div>'
        + '<div><b>Terakhir terlihat:</b> ' + escapeHtml(s.waktu ?? '-') + (s.sejak ? ' (' + escapeHtml(s.sejak) + ')' : '') + '</div>'
        + '<div><b>Sumber:</b> ' + escapeHtml(s.sumber ?? '-') + (s.baterai ? ' · baterai ' + escapeHtml(s.baterai) : '') + '</div>'
        + (s.lokasiPalsu ? '<div class="text-red-600"><b>Lokasi palsu terdeteksi</b></div>' : '')
        + (s.telepon ? '<div><b>HP:</b> <a href="tel:' + escapeHtml(s.telepon) + '">' + escapeHtml(s.telepon) + '</a></div>' : '')
        + '</div>';
}

// Klaster per depo: lokasi depo bila ada, selain itu titik tengah sales depo itu.
function klaster() {
    const per = new Map();
    diPeta.value.forEach((s) => {
        const k = s.depoId ?? 0;
        if (!per.has(k)) per.set(k, []);
        per.get(k).push(s);
    });
    const lokasiDepo = new Map(depo.value.map((d) => [d.id, d]));
    return [...per].map(([id, daftar]) => {
        const d = lokasiDepo.get(id);
        const lat = d?.lat ?? daftar.reduce((n, s) => n + s.lat, 0) / daftar.length;
        const lng = d?.lng ?? daftar.reduce((n, s) => n + s.lng, 0) / daftar.length;
        return { id, nama: d?.nama ?? daftar[0].depo ?? 'Tanpa depo', lat, lng, daftar };
    });
}

function gambar(fokus = false) {
    if (!petaLeaflet) return;
    lapisKlaster.clearLayers();
    lapisTitik.clearLayers();
    lapisDepo.clearLayers();
    penanda.clear();

    klaster().forEach((k) => {
        const n = k.daftar.length;
        const ukuran = Math.round(26 + Math.min(34, Math.sqrt(n) * 4));
        const perStatus = STATUS.filter((s) => s.warna).map((s) => [s, k.daftar.filter((x) => x.status === s.id).length]).filter(([, j]) => j);
        // Warna klaster = status terbanyak (hijau bila ada yang terlihat hari ini di depo itu).
        const utama = k.daftar.some((x) => x.status === 'hari_ini') ? 'hari_ini' : perStatus.sort((a, b) => b[1] - a[1])[0][0].id;
        L.marker([k.lat, k.lng], {
            icon: L.divIcon({
                className: '',
                iconSize: [ukuran, ukuran],
                iconAnchor: [ukuran / 2, ukuran / 2],
                html: '<div class="klaster-peta" style="width:' + ukuran + 'px;height:' + ukuran + 'px;background:' + warnaStatus[utama] + '">' + n + '</div>',
            }),
        })
            .bindTooltip('<b>' + escapeHtml(k.nama) + '</b><br><small>' + perStatus.map(([s, j]) => j + ' ' + s.label.toLowerCase()).join('<br>') + '</small>')
            .on('click', () => petaLeaflet.fitBounds(L.latLngBounds(k.daftar.map((s) => [s.lat, s.lng])).pad(0.2), { maxZoom: 13 }))
            .addTo(lapisKlaster);
    });

    diPeta.value.forEach((s) => {
        const m = L.circleMarker([s.lat, s.lng], { renderer: kanvas, radius: 6, color: '#ffffff', weight: 1.5, fillColor: warnaStatus[s.status], fillOpacity: 0.95 })
            .bindTooltip(escapeHtml(s.nama))
            .on('click', (e) => L.popup().setLatLng(e.latlng).setContent(popupSales(s)).openOn(petaLeaflet));
        m.addTo(lapisTitik);
        penanda.set(s.id, m);
    });

    depo.value.forEach((d) => {
        if (d.lat === null || d.lng === null) return;
        L.marker([d.lat, d.lng], { icon: ikonLingkaran('#6f42c1', ikonDepo), zIndexOffset: -100 })
            .bindTooltip('<b>' + escapeHtml(d.nama) + '</b><br><small>Depo</small>')
            .addTo(lapisDepo);
    });

    tampilSesuaiZoom();
    if (fokus && diPeta.value.length) {
        petaLeaflet.fitBounds(L.latLngBounds(diPeta.value.map((s) => [s.lat, s.lng])).pad(0.05), { maxZoom: 12 });
    }
}

function tampilSesuaiZoom() {
    const dekat = petaLeaflet.getZoom() >= ZOOM_TITIK;
    [[lapisKlaster, !dekat], [lapisTitik, dekat], [lapisDepo, dekat]].forEach(([lapis, tampil]) => {
        if (tampil && !petaLeaflet.hasLayer(lapis)) lapis.addTo(petaLeaflet);
        if (!tampil && petaLeaflet.hasLayer(lapis)) petaLeaflet.removeLayer(lapis);
    });
}

watch([cari, depoDipilih, statusDipilih], () => gambar(false));
watch(depoDipilih, () => {
    if (petaLeaflet && diPeta.value.length) petaLeaflet.fitBounds(L.latLngBounds(diPeta.value.map((s) => [s.lat, s.lng])).pad(0.1), { maxZoom: 13 });
});

function fokusSales(s) {
    if (s.lat === null || !petaLeaflet) return;
    petaLeaflet.setView([s.lat, s.lng], Math.max(petaLeaflet.getZoom(), 14));
    L.popup().setLatLng([s.lat, s.lng]).setContent(popupSales(s)).openOn(petaLeaflet);
}
</script>

<template>
    <Head :title="`${title} · ${appName}`" />
    <AppLayout :breadcrumb-slug="slug">
        <PageHeader
            :title="title"
            icon="map"
            :subtitle="diperbarui ? `Posisi GPS terakhir ${angka(sales.length)} sales aktif · diperbarui ${diperbarui}` : 'Posisi GPS terakhir semua sales aktif se-Indonesia'"
        >
            <template #actions>
                <Button variant="outline-primary" :loading="memuat" @click="muat"><Icon name="undo" class="me-1" />Muat ulang</Button>
                <Button :tag="Link" :href="lastSeenUrl" variant="outline-secondary"><Icon name="clipboard-list" class="me-1" />Tabel Last Seen</Button>
            </template>
        </PageHeader>

        <Alert v-if="galat" variant="danger" class="mb-2">{{ galat }}</Alert>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-2">
            <div class="lg:col-span-3 flex flex-col gap-2">
                <Card body-class="card-body card-body-compact">
                    <template #header><h6 class="mb-0 font-semibold">Saring</h6></template>
                    <Label for="cari_sales" class="mb-1">Cari</Label>
                    <Input id="cari_sales" v-model="cari" type="search" class="mb-2" placeholder="Nama, depo, koordinator, HP…" />
                    <Label for="depo_sales" class="mb-1">Depo</Label>
                    <Select id="depo_sales" v-model="depoDipilih" :options="opsiDepo" placeholder="Semua depo" class="mb-2" />
                    <div class="text-xs font-semibold mb-1">Terakhir terlihat</div>
                    <label v-for="s in STATUS" :key="s.id" class="flex items-center gap-2 mb-1 cursor-pointer text-sm">
                        <Checkbox v-model="statusDipilih" :value="s.id" />
                        <span class="legenda-peta legenda-peta-titik" :style="s.warna ? { background: s.warna } : {}"></span>
                        <span class="grow">{{ s.label }}</span>
                        <span class="font-semibold">{{ angka(hitung[s.id] ?? 0) }}</span>
                    </label>
                    <p class="text-xs text-gray-500 mt-2 mb-0">"Belum ada lokasi" tidak tampil di peta, hanya di daftar.</p>
                </Card>

                <Card body-class="p-0">
                    <template #header><h6 class="mb-0 font-semibold">Sales ({{ angka(tersaring.length) }})</h6></template>
                    <div class="max-h-96 overflow-y-auto">
                        <div class="list-group list-group-flush">
                            <div v-if="memuat && !sales.length" class="list-group-item text-center py-4"><span class="spinner me-2"></span>Memuat…</div>
                            <div v-else-if="!tersaring.length" class="list-group-item text-center text-gray-500 py-4">Tidak ada sales.</div>
                            <button
                                v-for="s in tersaring.slice(0, MAKS_DAFTAR)"
                                :key="s.id"
                                type="button"
                                class="list-group-item list-group-item-action w-full text-left"
                                :disabled="s.lat === null"
                                @click="fokusSales(s)"
                            >
                                <div class="flex items-center gap-2">
                                    <span class="legenda-peta legenda-peta-titik" :style="warnaStatus[s.status] ? { background: warnaStatus[s.status] } : {}"></span>
                                    <span class="font-semibold truncate grow">{{ s.nama }}</span>
                                </div>
                                <small class="text-gray-500">{{ s.depo ?? '-' }} · {{ s.sejak ?? 'belum ada lokasi' }}</small>
                            </button>
                            <div v-if="tersaring.length > MAKS_DAFTAR" class="list-group-item text-center text-gray-500 text-xs">
                                {{ angka(tersaring.length - MAKS_DAFTAR) }} sales lainnya, persempit pencarian.
                            </div>
                        </div>
                    </div>
                </Card>
            </div>

            <div class="lg:col-span-9">
                <Card body-class="card-body p-2">
                    <div ref="wadah" class="h-160 rounded-md" aria-label="Peta sebaran sales"></div>
                    <p class="text-xs text-gray-500 mt-1 mb-0">
                        Lingkaran berangka = jumlah sales per depo (klik untuk memperbesar). Perbesar peta untuk melihat titik tiap sales; klik titik untuk detailnya.
                    </p>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
