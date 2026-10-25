<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Badge from '@/Components/ui/atoms/Badge.vue';
import Icon from '@/Components/ui/atoms/Icon.vue';
import Input from '@/Components/ui/atoms/Input.vue';
import Label from '@/Components/ui/atoms/Label.vue';
import Select from '@/Components/ui/atoms/Select.vue';
import Card from '@/Components/ui/molecules/Card.vue';
import PageHeader from '@/Components/ui/molecules/PageHeader.vue';
import { useLeafletMap } from '@/Composables/useLeafletMap.js';
import { alamatDari, escapeHtml, ikonDepo, ikonLingkaran, ikonMotoris, ikonPin, tautanGoogleMap } from '@/Support/peta.js';

const props = defineProps({
    slug: { type: String, required: true },
    title: { type: String, required: true },
    label: { type: String, required: true },
    idParam: { type: String, required: true },
    people: { type: Array, required: true },
    groups: { type: [Object, Array], default: () => ({}) },
    locationsUrl: { type: String, required: true },
    countsUrl: { type: String, required: true },
    tanggal: { type: String, required: true },
    counts: { type: Object, required: true },
});

const appName = usePage().props.appName;
const labelKecil = props.label.toLowerCase();

const wadah = ref(null);
const tanggalDipilih = ref(props.tanggal);
const grup = ref('');
const cari = ref('');
const jumlahPerOrang = ref({ ...props.counts });
const aktifId = ref(null);
const namaTerpilih = ref('-');
const tanggalTampil = ref('-');
const info = ref(`Pilih ${labelKecil}`);

// Layer Leaflet bukan state reaktif Vue: disimpan di luar ref supaya tidak di-proxy.
let rute = null;

const { peta } = useLeafletMap(wadah, {
    onSiap(map, L) {
        rute = L.layerGroup().addTo(map);
        map.on('click', (e) => {
            const { lat, lng } = e.latlng;
            const popup = L.popup().setLatLng(e.latlng)
                .setContent('<strong>&#x1F4CD; Informasi Lokasi</strong><br><i class="text-gray-500">Memuat alamat...</i><br><small>Lat: ' + lat.toFixed(6) + ', Lng: ' + lng.toFixed(6) + '</small>')
                .openOn(map);
            alamatDari(lat, lng).then((addr) => popup.setContent('<strong>&#x1F4CD; Informasi Lokasi</strong><br>' + escapeHtml(addr) + '<br><small class="text-gray-500">Lat: ' + lat.toFixed(6) + ' | Lng: ' + lng.toFixed(6) + '</small>'));
        });
        pilihOtomatis();
    },
});

const jumlah = (id) => jumlahPerOrang.value[id] || 0;

const daftarGrup = computed(() => Object.entries(props.groups));

// Yang punya titik GPS pada tanggal terpilih ditaruh di atas, lalu urut nama.
const daftar = computed(() => [...props.people].sort((a, b) => (jumlah(b.id) > 0) - (jumlah(a.id) > 0) || (a.nama ?? '').localeCompare(b.nama ?? '')));

const tampil = computed(() => {
    const q = cari.value.toLowerCase();
    return daftar.value.filter((o) => (o.nama ?? '').toLowerCase().includes(q) && (grup.value === '' || String(o.group_id) === grup.value));
});

const ringkasan = computed(() => {
    const punyaData = tampil.value.filter((o) => jumlah(o.id) > 0).length;
    return `${punyaData} dari ${tampil.value.length} ${labelKecil} punya data GPS pada tanggal ini.`;
});

function orang(id) {
    return props.people.find((o) => o.id === id);
}

function pilih(o) {
    aktifId.value = o.id;
    muatLokasi();
}

// Pilih otomatis: pertahankan pilihan yang masih tampil dan punya data; kalau tidak, pilih yang pertama punya data.
function pilihOtomatis() {
    const aktif = tampil.value.find((o) => o.id === aktifId.value);
    if (aktif && jumlah(aktif.id) > 0) {
        muatLokasi();
        return;
    }
    const pertama = tampil.value.find((o) => jumlah(o.id) > 0);
    if (pertama) {
        pilih(pertama);
        nextTick(() => document.getElementById(`orang-${pertama.id}`)?.scrollIntoView({ block: 'nearest' }));
        return;
    }
    aktifId.value = null;
    rute?.clearLayers();
    namaTerpilih.value = '-';
    info.value = 'Tidak ada data lokasi pada tanggal ini';
}

function muatLokasi() {
    const aktif = orang(aktifId.value);
    if (!aktif || !tanggalDipilih.value || !peta()) return;

    namaTerpilih.value = aktif.nama;
    tanggalTampil.value = tanggalDipilih.value;
    info.value = 'Memuat...';

    const map = peta();
    const id = aktif.id;
    const tanggal = tanggalDipilih.value;
    fetch(props.locationsUrl + '?' + new URLSearchParams({ [props.idParam]: id, tanggal }))
        .then((r) => r.json())
        .then((data) => {
            // Respons lama (orang atau tanggal sudah diganti, atau peta sudah dilepas) dibuang.
            if (peta() !== map || aktifId.value !== id || tanggalDipilih.value !== tanggal) return;
            info.value = data.total ? `${data.total} titik lokasi` : 'Tidak ada data lokasi';
            if (data.total) gambar(data, aktif.nama);
            else rute.clearLayers();
        })
        .catch(() => { info.value = 'Gagal memuat data'; });
}

async function gantiTanggal() {
    if (!tanggalDipilih.value) return;
    try {
        const r = await fetch(props.countsUrl + '?' + new URLSearchParams({ tanggal: tanggalDipilih.value }));
        if (!r.ok) throw new Error(`HTTP ${r.status}`);
        jumlahPerOrang.value = await r.json();
        pilihOtomatis();
    } catch {
        info.value = 'Gagal memuat data';
    }
}

watch(grup, pilihOtomatis);

function pointPopup(judul, loc, alamat) {
    return '<div style="min-width:200px"><strong>' + judul + '</strong><br>'
        + '<small>Waktu: ' + escapeHtml(loc.waktu) + '</small><br>'
        + '<small class="text-gray-500">&#x1F4CD; ' + (alamat ? escapeHtml(alamat) : '<i>Memuat alamat...</i>') + '</small><br>'
        + '<small class="text-gray-500">Lat: ' + loc.lat + ' | Lng: ' + loc.lng + '</small>'
        + (loc.akurasi ? '<br><small class="text-gray-500">Akurasi: ' + loc.akurasi + 'm</small>' : '')
        + '<br>' + tautanGoogleMap(loc.lat, loc.lng) + '</div>';
}

function gambar(data, nama) {
    const L = window.L;
    const map = peta();
    rute.clearLayers();
    const locs = data.locations;
    if (!locs.length) return;

    const path = locs.map((l) => [parseFloat(l.lat), parseFloat(l.lng)]);
    L.polyline(path, { color: '#0d6efd', opacity: 0.8, weight: 4 }).addTo(rute);

    locs.forEach((loc, i) => {
        const first = i === 0;
        const last = i === locs.length - 1;
        const ll = [parseFloat(loc.lat), parseFloat(loc.lng)];
        const marker = first || last
            ? L.marker(ll, { icon: ikonLingkaran(first ? '#198754' : '#dc3545', ikonMotoris), zIndexOffset: 100 })
            : L.circleMarker(ll, { radius: 5, fillColor: '#0d6efd', color: '#fff', weight: 1, fillOpacity: 0.8 });
        const judul = first ? '&#x1F7E2; Start' : (last ? '&#x1F534; End' : '&#x1F535; Titik ' + (i + 1));
        if (first || last) {
            marker.bindTooltip(escapeHtml(nama) + ' &middot; ' + (first ? 'Start' : 'End'), { permanent: true, direction: 'top', offset: [0, -20], className: 'tracking-name-tooltip' });
        }
        marker.bindPopup(pointPopup(judul, loc));
        let dimuat = false;
        marker.on('popupopen', () => {
            if (dimuat) return;
            dimuat = true;
            alamatDari(loc.lat, loc.lng).then((alamat) => marker.setPopupContent(pointPopup(judul, loc, alamat)));
        });
        marker.addTo(rute);
    });

    if (data.depo && parseFloat(data.depo.latitude) && parseFloat(data.depo.longitude)) {
        const d = [parseFloat(data.depo.latitude), parseFloat(data.depo.longitude)];
        L.marker(d, { icon: ikonLingkaran('#6f42c1', ikonDepo), zIndexOffset: 50 })
            .bindPopup('<div class="text-center"><h6 class="mb-0">' + escapeHtml(data.depo.nama) + '</h6><small class="text-gray-500">Depo</small><br>' + tautanGoogleMap(d[0], d[1]) + '</div>')
            .addTo(rute);
    }

    map.fitBounds(L.latLngBounds(path), { padding: [30, 30] });
}
</script>

<template>
    <Head :title="`${title} · ${appName}`" />
    <AppLayout :breadcrumb-slug="slug">
        <PageHeader :title="title" />

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-3">
            <div class="lg:col-span-4">
                <Card>
                    <template #header><h6 class="mb-0 font-semibold">Filter Tracking</h6></template>

                    <Label for="tanggal_tracking">Tanggal</Label>
                    <Input id="tanggal_tracking" v-model="tanggalDipilih" type="date" class="mb-3" @change="gantiTanggal" />

                    <template v-if="daftarGrup.length">
                        <Label for="filter_grup">Koordinator</Label>
                        <Select id="filter_grup" v-model="grup" class="mb-3">
                            <option value="">Semua koordinator</option>
                            <option v-for="[id, nama] in daftarGrup" :key="id" :value="id">{{ nama }}</option>
                        </Select>
                    </template>

                    <Label for="cari_orang">{{ label }}</Label>
                    <Input id="cari_orang" v-model="cari" type="search" class="mb-2" :placeholder="`Cari ${labelKecil}...`" />
                    <div class="text-xs text-gray-500 mb-2">{{ ringkasan }}</div>

                    <div class="list-group max-h-[50vh] overflow-y-auto">
                        <button
                            v-for="o in tampil"
                            :id="`orang-${o.id}`"
                            :key="o.id"
                            type="button"
                            class="list-group-item list-group-item-action w-full text-left py-2"
                            :class="{ 'opacity-50': jumlah(o.id) === 0, 'bg-blue-50 border-l-[3px] border-blue-600': aktifId === o.id }"
                            @click="pilih(o)"
                        >
                            <div class="flex justify-between items-start gap-2">
                                <span class="font-semibold">{{ o.nama }}</span>
                                <Badge :variant="jumlah(o.id) ? 'primary' : 'light'">{{ jumlah(o.id) ? `${jumlah(o.id)} titik` : 'tidak ada GPS' }}</Badge>
                            </div>
                            <small v-if="o.koordinator_nama" class="text-gray-500 block"><Icon name="user" class="me-1" />{{ o.koordinator_nama }}</small>
                            <small class="text-gray-500">
                                <Icon name="warehouse" class="me-1" />{{ o.depo || '-' }}
                                <span v-if="o.telepon" class="ml-2"><Icon name="phone" class="me-1" />{{ o.telepon }}</span>
                            </small>
                        </button>
                        <div v-if="!people.length" class="text-gray-500 text-xs p-3">Belum ada data. Jalankan <code>php artisan marketama:import</code>.</div>
                    </div>
                </Card>
            </div>

            <div class="lg:col-span-8">
                <Card body-class="card-body p-2">
                    <template #header>
                        <div class="flex flex-wrap gap-3 text-xs">
                            <span><b>{{ label }}:</b> {{ namaTerpilih }}</span>
                            <span><b>Tanggal:</b> {{ tanggalTampil }}</span>
                            <span><b>Info:</b> {{ info }}</span>
                        </div>
                    </template>
                    <div ref="wadah" class="h-[calc(100vh-160px)] min-h-120 rounded-md"></div>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>

<style>
.tracking-name-tooltip { font-weight: 600; }
</style>
