<script setup>
import { computed, onBeforeUnmount, onMounted, onUpdated, reactive, ref, useSlots, watch } from 'vue';
import Card from '@/Components/ui/molecules/Card.vue';
import Pagination from '@/Components/ui/molecules/Pagination.vue';
import Badge from '@/Components/ui/atoms/Badge.vue';
import Icon from '@/Components/ui/atoms/Icon.vue';
import Input from '@/Components/ui/atoms/Input.vue';
import Select from '@/Components/ui/atoms/Select.vue';
import Button from '@/Components/ui/atoms/Button.vue';

const props = defineProps({
    title: { type: String, default: null },
    columns: { type: Object, required: true },
    rows: { type: Array, default: () => [] },
    total: { type: Number, default: 0 },
    links: { type: Array, default: () => [] },
    badgeColumns: { type: Array, default: () => [] },
    // Warna badge per nilai, mis. { status: { menunggu: 'warning', ditolak: 'danger' } }; kunci '*' = nilai lain yang terisi.
    // Tanpa peta: terisi = success.
    badgeVariants: { type: Object, default: () => ({}) },
    emptyText: { type: String, default: 'Tidak ada data.' },
    // Prop Inertia yang diambil ulang saat pindah halaman tabel (lihat Pagination).
    paginationOnly: { type: Array, default: () => [] },
    // Kolom yang header-nya bisa diklik untuk sort (kosong = tidak ada sort).
    sortable: { type: Array, default: () => [] },
    // Sort yang sedang aktif dari backend: { kolom, arah: 'asc' | 'desc' }, atau null.
    sort: { type: Object, default: null },
    // Kolom yang punya input filter di bawah header (kosong = tidak ada baris filter).
    filterable: { type: Array, default: () => [] },
    // Nilai filter aktif dari backend: { kolom: nilai }.
    filterValues: { type: Object, default: () => ({}) },
    // Kolom angka (bagian dari filterable) yang difilter dengan operator, mis. <, =, >.
    filterAngka: { type: Array, default: () => [] },
    // Operator aktif per kolom angka dari backend: { kolom: op }.
    filterOperator: { type: Object, default: () => ({}) },
    // Kolom tanggal (bagian dari filterable) yang difilter dengan datepicker dan operator.
    filterTanggal: { type: Array, default: () => [] },
    // Kolom yang menempel di kiri saat tabel digeser horizontal, urut dari kiri (mis. ['foto', 'nama']).
    stickyColumns: { type: Array, default: () => [] },
    // Tabel mengisi sisa tinggi layar: header kolom tetap terlihat saat baris di-scroll (scroll vertikal di dalam tabel).
    tinggiPenuh: { type: Boolean, default: false },
    // Dengan tinggiPenuh: tinggi area tabel dipatok (bukan hanya maksimum), jadi tetap setinggi layar walau data kosong.
    tinggiTetap: { type: Boolean, default: false },
    // Tabel mengisi tinggi wadah induknya (induk harus berukuran tetap, mis. flex-1 min-h-0 di dalam modal): hanya area
    // baris yang bergulir, header kolom tetap. Beda dengan tinggiPenuh yang menghitung dari sisa tinggi layar.
    isiWadah: { type: Boolean, default: false },
    // Data sedang diambil ulang (mis. pencarian): baris diganti placeholder skeleton, bukan teks.
    memuat: { type: Boolean, default: false },
    // Seluruh baris bisa diklik (atau Enter saat difokus) dan mengirim 'klik-baris' dengan datanya, mis. untuk membuka drawer detail.
    barisBisaDiklik: { type: Boolean, default: false },
});

// Kolom yang punya pilihan operator: kolom angka dan kolom tanggal.
const kolomOperator = computed(() => [...props.filterAngka, ...props.filterTanggal]);

const OPERATOR_FILTER = [
    { value: '=', label: '=' },
    { value: '!=', label: '≠' },
    { value: '<', label: '<' },
    { value: '<=', label: '≤' },
    { value: '>', label: '>' },
    { value: '>=', label: '≥' },
];

const emit = defineEmits(['sort', 'filter', 'klik-baris']);

// Klik/Enter pada baris (prop barisBisaDiklik); klik pada kontrol di dalam baris (tombol, tautan, input) tidak ikut dihitung.
function klikBaris(row, e) {
    if (!props.barisBisaDiklik || e.target.closest('button, a, input, select, textarea, label')) {
        return;
    }

    emit('klik-baris', row);
}

// Kolom "Aksi" muncul bila halaman mengisi slot #aksi="{ row }".
// Sel kolom tertentu bisa diganti lewat slot #cell-<nama kolom>="{ row }" (mis. tautan, badge ganda).
const slots = useSlots();

function formatTotal(total) {
    return new Intl.NumberFormat('id-ID').format(total);
}

function badgeVariant(column, value) {
    const peta = props.badgeVariants[column];

    return peta?.[value] ?? (value ? peta?.['*'] : undefined) ?? (value ? 'success' : 'secondary');
}

// Arah sort kolom ini ('asc' | 'desc'), atau null bila kolom ini tidak sedang diurutkan.
function arahSort(kolom) {
    return props.sort?.kolom === kolom ? props.sort.arah : null;
}

// Klik berurutan: naik → turun → netral (tanpa sort), lalu naik lagi.
function urutkan(kolom) {
    const arah = arahSort(kolom);

    emit('sort', { kolom, arah: arah === null ? 'asc' : (arah === 'asc' ? 'desc' : null) });
}

function classHeaderSort(kolom) {
    if (!props.sortable.includes(kolom)) {
        return null;
    }

    const arah = arahSort(kolom);

    return ['sortable', arah && 'sort-aktif', arah === 'asc' && 'sort-asc'];
}

function ariaSort(kolom) {
    const arah = arahSort(kolom);

    return arah === null ? 'none' : (arah === 'asc' ? 'ascending' : 'descending');
}

// Nilai input dan operator per kolom. Ditampilkan dari backend, lalu dikirim ulang (debounce) saat user mengubahnya.
const nilaiFilter = reactive({});
const operatorFilter = reactive({});

// Ketikan yang belum terkirim (belum lewat jeda, atau sedang di perjalanan) tidak boleh ditimpa
// oleh respons request sebelumnya, supaya teks yang sedang diketik tidak hilang.
let adaKetikanBelumTerkirim = false;

function sinkronFilter() {
    if (adaKetikanBelumTerkirim) {
        return;
    }

    for (const kolom of props.filterable) {
        nilaiFilter[kolom] = props.filterValues[kolom] ?? '';
    }

    for (const kolom of kolomOperator.value) {
        operatorFilter[kolom] = props.filterOperator[kolom] ?? '=';
    }
}

sinkronFilter();
watch(() => [props.filterValues, props.filterOperator], sinkronFilter, { deep: true });

let jedaFilter = null;

// Jeda setelah ketikan terakhir sebelum filter dikirim ke server, supaya tidak reload di tengah kata.
const JEDA_FILTER_MS = 700;

function jadwalkanFilter() {
    adaKetikanBelumTerkirim = true;
    clearTimeout(jedaFilter);

    // Seluruh nilai dan operator dikirim sekaligus, jadi mengubah dua kolom berurutan tidak saling membatalkan.
    jedaFilter = setTimeout(() => {
        adaKetikanBelumTerkirim = false;
        emit('filter', {
            nilai: Object.fromEntries(props.filterable.map((k) => [k, (nilaiFilter[k] ?? '').trim()])),
            operator: Object.fromEntries(kolomOperator.value.map((k) => [k, operatorFilter[k] ?? '='])),
        });
    }, JEDA_FILTER_MS);
}

function ubahFilter(kolom, nilai) {
    nilaiFilter[kolom] = nilai;
    jadwalkanFilter();
}

function ubahOperator(kolom, op) {
    operatorFilter[kolom] = op;
    jadwalkanFilter();
}

// Flyout filter: hanya satu kolom terbuka. Tertutup saat klik di luar atau tekan Esc.
const flyoutKolom = ref(null);

function bukaFlyout(kolom) {
    flyoutKolom.value = flyoutKolom.value === kolom ? null : kolom;
}

function tutupFlyout() {
    flyoutKolom.value = null;
}

function tutupFlyoutSaatKlikLuar() {
    if (flyoutKolom.value !== null) {
        tutupFlyout();
    }
}

function tutupFlyoutSaatEsc(event) {
    if (event.key === 'Escape') {
        tutupFlyout();
    }
}

function adaFilterAktif(kolom) {
    return (nilaiFilter[kolom] ?? '') !== '';
}

function hapusFilter(kolom) {
    nilaiFilter[kolom] = '';

    if (kolomOperator.value.includes(kolom)) {
        operatorFilter[kolom] = '=';
    }

    tutupFlyout();
    jadwalkanFilter();
}

// Kolom sticky disusun berurutan: offset `left` tiap kolom = total lebar kolom sticky di sebelah kirinya.
// Lebar diukur dari header (sel tbody memakai kolom yang sama, jadi lebarnya ikut).
const tabel = ref(null);

function kelasSticky(kolom) {
    return props.stickyColumns.includes(kolom) ? 'kolom-sticky' : null;
}

function aturSticky() {
    if (!tabel.value || props.stickyColumns.length === 0) {
        return;
    }

    let kiri = 0;

    Object.keys(props.columns).forEach((kolom, index) => {
        if (!props.stickyColumns.includes(kolom)) {
            return;
        }

        const sel = tabel.value.querySelectorAll(`tr > :nth-child(${index + 1})`);

        sel.forEach((el) => {
            el.style.left = `${kiri}px`;
        });

        kiri += sel[0]?.offsetWidth ?? 0;
    });
}

// Tinggi tabel = sisa tinggi layar dari posisi tabel sampai bawah viewport, dikurangi footer kartu, margin bawah
// kartu (mis. mb-3 dari pemanggil), dan padding bawah <main> (1.5rem, lihat app.css) supaya halaman tidak ikut
// ter-scroll. Dihitung ulang saat data/footer berubah dan saat ukuran jendela berubah. Sama dengan pola
// sesuaikanTinggi() di SummaryDepoTabel.vue.
const scrollEl = ref(null);
const footerEl = ref(null);
const PADDING_BAWAH_MAIN = 24 + 2;

function sesuaikanTinggi() {
    if (!props.tinggiPenuh || !scrollEl.value) {
        return;
    }

    const kartu = footerEl.value?.closest('.card');
    const atas = Math.round(scrollEl.value.getBoundingClientRect().top);
    const marginKartu = parseFloat(kartu ? getComputedStyle(kartu).marginBottom : 0) || 0;
    // Di dalam drawer/modal sisa di bawah kartu hanya padding .modal-body (plus padding body kartu dan border),
    // bukan padding <main>, supaya drawer tidak ikut ter-scroll.
    const modal = scrollEl.value.closest('.modal-body');
    const paddingBodyKartu = parseFloat(getComputedStyle(scrollEl.value.closest('.card-body') ?? document.body).paddingBottom) || 0;
    const sisa = modal ? (parseFloat(getComputedStyle(modal).paddingBottom) || 0) + paddingBodyKartu + 4 : PADDING_BAWAH_MAIN;
    const bawah = (footerEl.value?.closest('.card-footer')?.offsetHeight ?? 0) + marginKartu + sisa;
    const tinggi = `calc(100vh - ${atas}px - ${bawah}px)`;

    scrollEl.value.style[props.tinggiTetap ? 'height' : 'maxHeight'] = tinggi;
}

function perbaruiTampilan() {
    aturSticky();
    sesuaikanTinggi();
}

onMounted(() => {
    document.addEventListener('click', tutupFlyoutSaatKlikLuar);
    document.addEventListener('keydown', tutupFlyoutSaatEsc);
    window.addEventListener('resize', sesuaikanTinggi);
    perbaruiTampilan();
});

onUpdated(perbaruiTampilan);

onBeforeUnmount(() => {
    clearTimeout(jedaFilter);
    document.removeEventListener('click', tutupFlyoutSaatKlikLuar);
    document.removeEventListener('keydown', tutupFlyoutSaatEsc);
    window.removeEventListener('resize', sesuaikanTinggi);
});
</script>


<template>
    <Card :class="{ 'card-isi-wadah': isiWadah }" :body-class="isiWadah ? 'card-body card-body-isi-wadah' : 'overflow-x-auto'">
        <template v-if="slots.header || title" #header>
            <slot name="header">{{ title }}</slot>
        </template>

        <div ref="scrollEl" class="overflow-auto" :class="{ 'h-full': isiWadah }">
        <table ref="tabel" class="data-table" :class="{ 'tabel-header-sticky': tinggiPenuh || isiWadah }">
            <thead>
                <tr>
                    <th
                        v-for="[kolom, label] in Object.entries(columns)"
                        :key="kolom"
                        :class="[classHeaderSort(kolom), kelasSticky(kolom)]"
                        :aria-sort="sortable.includes(kolom) ? ariaSort(kolom) : null"
                    >
                        <div class="th-isi">
                            <button v-if="sortable.includes(kolom)" type="button" class="sort-button" @click="urutkan(kolom)">
                                {{ label }}
                                <Icon :name="arahSort(kolom) ? 'chevron-down' : 'chevrons-down'" class="sort-icon" />
                            </button>
                            <template v-else>{{ label }}</template>
                            <button
                                v-if="filterable.includes(kolom)"
                                type="button"
                                class="filter-button"
                                :class="{ 'filter-aktif': adaFilterAktif(kolom) }"
                                :aria-label="`Filter ${label}`"
                                :aria-expanded="flyoutKolom === kolom"
                                @click.stop="bukaFlyout(kolom)"
                            >
                                <Icon name="filter" class="filter-icon" />
                            </button>
                        </div>
                        <div v-if="flyoutKolom === kolom" class="filter-flyout" @click.stop>
                            <p class="filter-flyout-judul">Filter {{ label }}</p>
                            <div v-if="kolomOperator.includes(kolom)" class="filter-angka">
                                <Select
                                    class="col-filter-op"
                                    :model-value="operatorFilter[kolom]"
                                    :options="OPERATOR_FILTER"
                                    :aria-label="`Operator ${label}`"
                                    @update:model-value="ubahOperator(kolom, $event)"
                                />
                                <Input
                                    :type="filterTanggal.includes(kolom) ? 'date' : 'text'"
                                    :inputmode="filterAngka.includes(kolom) ? 'decimal' : null"
                                    class="col-filter"
                                    :model-value="nilaiFilter[kolom]"
                                    :placeholder="label"
                                    :aria-label="`Nilai ${label}`"
                                    @update:model-value="ubahFilter(kolom, $event)"
                                />
                            </div>
                            <Input
                                v-else
                                type="search"
                                class="col-filter"
                                :model-value="nilaiFilter[kolom]"
                                :placeholder="label"
                                :aria-label="`Nilai ${label}`"
                                @update:model-value="ubahFilter(kolom, $event)"
                            />
                            <div class="filter-flyout-aksi">
                                <Button variant="outline-secondary" :disabled="!adaFilterAktif(kolom)" @click="hapusFilter(kolom)">Hapus filter</Button>
                            </div>
                        </div>
                    </th>
                    <th v-if="slots.aksi">Aksi</th>
                </tr>
            </thead>
            <tbody v-if="memuat" class="animate-pulse" aria-busy="true" aria-live="polite">
                <tr v-for="baris in 8" :key="baris">
                    <td v-for="column in Object.keys(columns)" :key="column"><div class="h-3 rounded bg-gray-200"></div></td>
                    <td v-if="slots.aksi"><div class="h-3 w-12 rounded bg-gray-200"></div></td>
                </tr>
            </tbody>
            <tbody v-else>
                <tr
                    v-for="(row, index) in rows"
                    :key="index"
                    :class="{ 'baris-klik': barisBisaDiklik }"
                    :tabindex="barisBisaDiklik ? 0 : null"
                    @click="klikBaris(row, $event)"
                    @keydown.enter.self="barisBisaDiklik && emit('klik-baris', row)"
                >
                    <td v-for="column in Object.keys(columns)" :key="column" :class="kelasSticky(column)">
                        <slot v-if="slots[`cell-${column}`]" :name="`cell-${column}`" :row="row" />
                        <Badge v-else-if="badgeColumns.includes(column)" :variant="badgeVariant(column, row[column])">{{ row[column] || '-' }}</Badge>
                        <template v-else>{{ row[column] }}</template>
                    </td>
                    <td v-if="slots.aksi" class="whitespace-nowrap">
                        <slot name="aksi" :row="row" />
                    </td>
                </tr>
                <tr v-if="!rows.length">
                    <td :colspan="Object.keys(columns).length + (slots.aksi ? 1 : 0)" class="text-center text-gray-500 py-4">{{ emptyText }}</td>
                </tr>
            </tbody>
        </table>
        </div>

        <template #footer>
            <div ref="footerEl" class="flex justify-between items-center">
                <small class="text-gray-500">Total {{ formatTotal(total) }} data</small>
                <Pagination :links="links" :only="paginationOnly" />
            </div>
        </template>
    </Card>
</template>
