<script setup>
// Auto Grid: wilayah satu kota/kecamatan dibagi kotak 1–15 km di server, dipotong batas kelurahan resmi.
// Browser hanya menampilkan dan memilih; simpan mengirim parameter grid + kunci kotak terpilih.
import { computed, ref, watch } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from '@/Components/ui/atoms/Button.vue';
import Checkbox from '@/Components/ui/atoms/Checkbox.vue';
import Icon from '@/Components/ui/atoms/Icon.vue';
import Input from '@/Components/ui/atoms/Input.vue';
import Label from '@/Components/ui/atoms/Label.vue';
import Radio from '@/Components/ui/atoms/Radio.vue';
import Select from '@/Components/ui/atoms/Select.vue';
import Alert from '@/Components/ui/molecules/Alert.vue';
import Card from '@/Components/ui/molecules/Card.vue';
import PageHeader from '@/Components/ui/molecules/PageHeader.vue';
import StatRow from '@/Components/ui/molecules/StatRow.vue';
import FormWilayah from '@/Pages/Area/partials/FormWilayah.vue';
import ItemGrid from '@/Pages/Area/partials/ItemGrid.vue';
import { useFormWilayah } from '@/Pages/Area/partials/formWilayah.js';
import { warnaKecamatan } from '@/Pages/Area/partials/kelurahan.js';
import { useLeafletMap } from '@/Composables/useLeafletMap.js';
import { bersihkanQuery } from '@/Support/query.js';
import { escapeHtml, ikonDepo, ikonLingkaran, multiKeLatLng } from '@/Support/peta.js';

const props = defineProps({
    slug: { type: String, required: true },
    title: { type: String, required: true },
    provinsi: { type: Array, required: true },
    depo: { type: Array, required: true },
    wilayahDepo: { type: Array, default: () => [] },
    ukuran: { type: Array, required: true },
    bisaTambah: { type: Boolean, default: false },
    kembaliUrl: { type: String, required: true },
    kelurahanUrl: { type: String, required: true },
    opsiUrl: { type: String, required: true },
    pratinjauUrl: { type: String, required: true },
    simpanUrl: { type: String, required: true },
});

const appName = usePage().props.appName;
const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
const MAKS_DAFTAR = 300;

const MODE = [
    { id: 'kelurahan', label: 'Potong per kelurahan', ket: 'Tiap area pasti di dalam 1 kelurahan. Kotak di perbatasan kelurahan jadi beberapa area.' },
    { id: 'kotak', label: 'Kotak utuh', ket: 'Tiap area = 1 kotak (hanya dipotong batas wilayah). Satu area bisa mencakup beberapa kelurahan.' },
];

// ---------- Pengaturan ----------
const { form, opsi, opsiDepo, opsiProvinsi, depoTerpilih } = useFormWilayah(props);
const grid = ref({ ukuran: '4', mode: 'kelurahan', wilayah_depo_id: '' });
const opsiUkuran = computed(() => props.ukuran.map((u) => ({ value: u, label: u + ' km' })));
const opsiWilayahDepo = computed(() => props.wilayahDepo
    .filter((w) => String(w.depo_id) === String(form.warehouse_id))
    .map((w) => ({ value: w.id, label: w.nama })));

watch(depoTerpilih, () => {
    grid.value.wilayah_depo_id = '';
    gambarDepo();
});

// ---------- Hasil ----------
const unit = ref([]);
const dipilih = ref(new Set());
const memuat = ref(false);
const menyimpan = ref(false);
const peringatan = ref(null);
const galat = ref(null);
const hasilSimpan = ref(null);
const info = ref(null);
// Parameter grid yang sedang tampil; simpan memakai ini, bukan isian form yang mungkin sudah diubah.
const parameter = ref(null);
const cari = ref('');
const saring = ref('');

const statusUnit = (u) => (u.area ? 'tumpang' : 'siap');
const ringkasan = computed(() => ({
    total: unit.value.length,
    siap: unit.value.filter((u) => !u.area).length,
    tumpang: unit.value.filter((u) => u.area).length,
    luas: unit.value.filter((u) => dipilih.value.has(u.kunci)).reduce((n, u) => n + u.luas_km2, 0),
}));

const tampil = computed(() => {
    const q = cari.value.trim().toLowerCase();
    return unit.value.filter((u) => (!saring.value || statusUnit(u) === saring.value)
        && (!q || (u.nama + ' ' + u.kelurahan.map((k) => k.nama + ' ' + k.kecamatan).join(' ')).toLowerCase().includes(q)));
});
const bisaDipilihTampil = computed(() => tampil.value.filter((u) => !u.area));
const semuaTerpilih = computed(() => bisaDipilihTampil.value.length > 0 && bisaDipilihTampil.value.every((u) => dipilih.value.has(u.kunci)));
const sebagianTerpilih = computed(() => !semuaTerpilih.value && bisaDipilihTampil.value.some((u) => dipilih.value.has(u.kunci)));

function pilihSemua(on) {
    const baru = new Set(dipilih.value);
    bisaDipilihTampil.value.forEach((u) => (on ? baru.add(u.kunci) : baru.delete(u.kunci)));
    dipilih.value = baru;
}

function togglePilih(u) {
    if (u.area) return;
    const baru = new Set(dipilih.value);
    baru.has(u.kunci) ? baru.delete(u.kunci) : baru.add(u.kunci);
    dipilih.value = baru;
}

async function generate() {
    if (!form.kota_id) { alert('Pilih Kota / Kabupaten terlebih dahulu.'); return; }
    memuat.value = true;
    peringatan.value = null;
    galat.value = null;
    hasilSimpan.value = null;
    const params = bersihkanQuery({ kota_id: form.kota_id, kecamatan_id: form.kecamatan_id, ukuran: grid.value.ukuran, mode: grid.value.mode });
    try {
        const res = await fetch(props.pratinjauUrl + '?' + new URLSearchParams(params), { headers: { Accept: 'application/json' } });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) {
            galat.value = data.errors ? Object.values(data.errors).flat().join(' ') : 'Gagal membuat grid.';
            return;
        }
        unit.value = data.unit;
        parameter.value = params;
        peringatan.value = data.peringatan;
        info.value = { wilayah: data.kecamatan ? `Kec. ${data.kecamatan}, ${data.kota}` : data.kota, baris: data.baris, kolom: data.kolom };
        dipilih.value = new Set(data.unit.filter((u) => !u.area).map((u) => u.kunci));
        gambarGrid(true);
    } catch {
        galat.value = 'Gagal membuat grid.';
    } finally {
        memuat.value = false;
    }
}

async function simpan() {
    if (!form.warehouse_id) { alert('Pilih Depo yang akan memiliki area baru.'); return; }
    const kunci = unit.value.filter((u) => dipilih.value.has(u.kunci)).map((u) => u.kunci);
    if (!kunci.length || !parameter.value) return;
    if (!confirm(`Buat ${kunci.length} area baru untuk depo ${depoTerpilih.value?.name ?? ''}?`)) return;

    menyimpan.value = true;
    try {
        const res = await fetch(props.simpanUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({ ...parameter.value, warehouse_id: form.warehouse_id, wilayah_depo_id: grid.value.wilayah_depo_id || null, kunci }),
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) {
            alert(data.errors ? Object.values(data.errors).flat().join('\n') : (data.message || 'Gagal menyimpan area.'));
            return;
        }
        await generate();
        hasilSimpan.value = data;
    } finally {
        menyimpan.value = false;
    }
}

// ---------- Peta ----------
let L = null;
let peta = null;
let renderer = null;
let lapisanGrid = null;
let lapisanDepo = null;
const overlay = new Map();

const wadah = ref(null);
useLeafletMap(wadah, {
    pusat: [-6.2, 106.8],
    zoom: 10,
    onSiap(map, Lib) {
        L = Lib;
        peta = map;
        // Ribuan kotak lebih ringan digambar di canvas daripada SVG.
        renderer = L.canvas({ padding: 0.5 });
        lapisanGrid = L.featureGroup().addTo(map);
        lapisanDepo = L.layerGroup().addTo(map);
        gambarDepo();
    },
});

function gaya(u) {
    if (u.area) return { renderer, color: '#d97706', weight: 1, dashArray: '4 3', fillColor: '#f59e0b', fillOpacity: 0.12 };
    const on = dipilih.value.has(u.kunci);
    return { renderer, color: '#ffffff', weight: 1, fillColor: on ? warnaKecamatan(u.kelurahan[0].kecamatan) : '#6c757d', fillOpacity: on ? 0.45 : 0.1 };
}

function tooltip(u) {
    const kel = u.kelurahan.slice(0, 4).map((k) => escapeHtml(k.nama) + ' (' + k.luas_km2.toLocaleString('id-ID') + ' km²)').join('<br>');
    return '<b>' + escapeHtml(u.nama) + '</b><br><small>' + u.luas_km2.toLocaleString('id-ID') + ' km² · Kec. ' + escapeHtml(u.kelurahan[0].kecamatan) + '</small>'
        + '<br><small>' + kel + (u.kelurahan.length > 4 ? '<br>+' + (u.kelurahan.length - 4) + ' lainnya' : '') + '</small>'
        + (u.area ? '<br><small>Tumpang tindih: ' + escapeHtml(u.area.code) + (u.area.warehouse ? ' (' + escapeHtml(u.area.warehouse) + ')' : '') + '</small>' : '');
}

function gambarGrid(fokus = false) {
    if (!lapisanGrid) return;
    lapisanGrid.clearLayers();
    overlay.clear();
    unit.value.forEach((u) => {
        const lapis = L.polygon(multiKeLatLng(u.geometri), gaya(u))
            .bindTooltip(tooltip(u), { sticky: true })
            .on('click', () => togglePilih(u));
        lapis.addTo(lapisanGrid);
        overlay.set(u.kunci, lapis);
    });
    if (fokus && lapisanGrid.getLayers().length) peta.fitBounds(lapisanGrid.getBounds(), { padding: [20, 20] });
}

function gambarDepo() {
    if (!lapisanDepo) return;
    lapisanDepo.clearLayers();
    const depo = depoTerpilih.value;
    if (!depo?.lat || !depo?.lng) return;
    L.marker([depo.lat, depo.lng], { icon: ikonLingkaran('#6f42c1', ikonDepo), zIndexOffset: 500 })
        .bindTooltip('<b>' + escapeHtml(depo.name) + '</b><br><small>Depo</small>')
        .addTo(lapisanDepo);
}

watch(dipilih, () => {
    unit.value.forEach((u) => overlay.get(u.kunci)?.setStyle(gaya(u)));
});

function fokus(u) {
    const lapis = overlay.get(u.kunci);
    if (!lapis) return;
    peta.fitBounds(lapis.getBounds(), { maxZoom: 16 });
    lapis.openTooltip(lapis.getBounds().getCenter());
}
</script>

<template>
    <Head :title="`${title} · ${appName}`" />
    <AppLayout :breadcrumb-slug="slug">
        <PageHeader :title="title" subtitle="Bagi wilayah jadi kotak 1–15 km, dipotong batas kelurahan resmi Kemendagri 2025">
            <template #actions>
                <Link :href="kelurahanUrl" class="text-gray-500 hover:underline">Per Kelurahan</Link>
                <Link :href="kembaliUrl" class="text-gray-500 hover:underline">Kembali ke Design Area</Link>
            </template>
        </PageHeader>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-2">
            <div class="lg:col-span-4 flex flex-col gap-2">
                <Card body-class="card-body card-body-compact">
                    <template #header><h6 class="mb-0 font-semibold">Pengaturan Grid</h6></template>

                    <div class="flex flex-col gap-2">
                        <FormWilayah :form="form" :opsi="opsi" :opsi-depo="opsiDepo" :opsi-provinsi="opsiProvinsi" id-prefix="grid">
                            <template #setelah-depo>
                                <div>
                                    <Label for="grid_wilayah_depo" class="mb-1">Wilayah Depo</Label>
                                    <Select
                                        id="grid_wilayah_depo"
                                        v-model="grid.wilayah_depo_id"
                                        :options="opsiWilayahDepo"
                                        :placeholder="opsiWilayahDepo.length ? '- Tanpa wilayah depo -' : '- Tidak ada wilayah depo -'"
                                        :disabled="!opsiWilayahDepo.length"
                                    />
                                </div>
                            </template>
                        </FormWilayah>
                        <div>
                            <Label for="grid_ukuran" class="mb-1">Ukuran Kotak <span class="text-red-600">*</span></Label>
                            <Select id="grid_ukuran" v-model="grid.ukuran" :options="opsiUkuran" />
                        </div>
                        <div>
                            <Label class="mb-1">Mode</Label>
                            <label v-for="m in MODE" :key="m.id" class="flex items-start gap-2 mb-1 cursor-pointer">
                                <Radio v-model="grid.mode" :value="m.id" class="mt-0.5" />
                                <span><span class="font-semibold">{{ m.label }}</span><br><small class="text-gray-500">{{ m.ket }}</small></span>
                            </label>
                        </div>
                        <Button id="btn_generate" variant="primary" :loading="memuat" :disabled="!form.kota_id" @click="generate">
                            <Icon name="map" class="me-1" />Generate Grid
                        </Button>
                        <small class="text-gray-500">Laut dan wilayah di luar kota tidak ikut karena tidak ada di batas kelurahan. Pecahan kecil di tepi digabung ke kotak tetangganya.</small>
                    </div>
                </Card>

                <Alert v-if="galat" variant="danger"><Icon name="info" class="me-1" />{{ galat }}</Alert>
                <Alert v-if="peringatan" variant="warning"><Icon name="info" class="me-1" />{{ peringatan }}</Alert>
                <Alert v-if="hasilSimpan" :variant="hasilSimpan.dibuat ? 'success' : 'warning'">
                    <div v-if="hasilSimpan.dibuat"><b>{{ hasilSimpan.dibuat }} area dibuat</b> ({{ hasilSimpan.kode[0] }} s.d. {{ hasilSimpan.kode[1] }}).</div>
                    <div v-else>Tidak ada area baru yang dibuat.</div>
                    <div v-if="hasilSimpan.dilewati.length" class="mt-1 text-xs">{{ hasilSimpan.dilewati.length }} dilewati karena tumpang tindih dengan area yang sudah ada.</div>
                </Alert>

                <Card v-if="info" body-class="card-body card-body-compact">
                    <template #header><h6 class="mb-0 font-semibold">Ringkasan · {{ info.wilayah }}</h6></template>
                    <StatRow label="Grid">{{ info.baris }} baris × {{ info.kolom }} kolom</StatRow>
                    <StatRow label="Total area">{{ ringkasan.total }}</StatRow>
                    <StatRow label="Siap dibuat" value-class="text-green-700">{{ ringkasan.siap }}</StatRow>
                    <StatRow label="Tumpang tindih area lama" value-class="text-amber-700">{{ ringkasan.tumpang }}</StatRow>
                    <StatRow label="Dipilih">{{ dipilih.size }} · {{ ringkasan.luas.toLocaleString('id-ID', { maximumFractionDigits: 1 }) }} km²</StatRow>
                </Card>
            </div>

            <div class="lg:col-span-8 flex flex-col gap-2">
                <Card body-class="card-body p-2">
                    <div ref="wadah" class="h-130 rounded-md"></div>
                    <div class="flex flex-wrap gap-3 mt-2 text-xs text-gray-600">
                        <span class="flex items-center gap-1"><span class="inline-block w-3 h-3 rounded-sm bg-blue-600"></span>Dipilih (warna per kecamatan)</span>
                        <span class="flex items-center gap-1"><span class="inline-block w-3 h-3 rounded-sm bg-gray-300"></span>Tidak dipilih</span>
                        <span class="flex items-center gap-1"><span class="inline-block w-3 h-3 rounded-sm border border-amber-600 bg-amber-200"></span>Tumpang tindih area lama</span>
                        <span>Klik kotak untuk memilih / membatalkan.</span>
                    </div>
                </Card>

                <Card body-class="p-0">
                    <template #header>
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h6 class="mb-0 font-semibold">Daftar Area ({{ tampil.length }})</h6>
                            <Button
                                v-if="bisaTambah"
                                id="btn_simpan_grid"
                                variant="success"
                                :loading="menyimpan"
                                :disabled="!dipilih.size || !form.warehouse_id"
                                :title="!form.warehouse_id ? 'Pilih depo dulu' : ''"
                                @click="simpan"
                            >
                                <Icon name="check" class="me-1" />Buat {{ dipilih.size }} Area
                            </Button>
                        </div>
                    </template>

                    <div class="card-body card-body-compact pb-2 grid grid-cols-1 sm:grid-cols-3 gap-2 items-center">
                        <label class="flex items-center gap-2 text-sm">
                            <Checkbox :model-value="semuaTerpilih" :indeterminate="sebagianTerpilih" :disabled="!bisaDipilihTampil.length" @update:model-value="pilihSemua" />
                            Pilih semua yang siap
                        </label>
                        <Input v-model="cari" type="search" placeholder="Cari kelurahan / kecamatan..." />
                        <Select v-model="saring">
                            <option value="">Semua status</option>
                            <option value="siap">Siap dibuat</option>
                            <option value="tumpang">Tumpang tindih</option>
                        </Select>
                    </div>

                    <div class="h-90 overflow-y-auto border-t border-gray-100">
                        <div class="list-group list-group-flush">
                            <div v-if="memuat" class="list-group-item text-center py-4"><span class="spinner me-2"></span>Membuat grid...</div>
                            <div v-else-if="!unit.length" class="list-group-item text-center text-gray-500 py-4">Pilih kota lalu klik "Generate Grid".</div>
                            <div v-else-if="!tampil.length" class="list-group-item text-center text-gray-500 py-4">Tidak ada area.</div>
                            <template v-else>
                                <ItemGrid
                                    v-for="u in tampil.slice(0, MAKS_DAFTAR)"
                                    :key="u.kunci"
                                    :unit="u"
                                    :terpilih="dipilih.has(u.kunci)"
                                    @pilih="togglePilih(u)"
                                    @fokus="fokus(u)"
                                />
                                <div v-if="tampil.length > MAKS_DAFTAR" class="list-group-item text-center text-gray-500 text-xs">
                                    {{ tampil.length - MAKS_DAFTAR }} area lainnya tidak ditampilkan di daftar (tetap ada di peta), persempit dengan pencarian
                                </div>
                            </template>
                        </div>
                    </div>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
