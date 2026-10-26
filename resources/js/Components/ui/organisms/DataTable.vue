<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, useSlots, watch } from 'vue';
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

const emit = defineEmits(['sort', 'filter']);

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

onMounted(() => {
    document.addEventListener('click', tutupFlyoutSaatKlikLuar);
    document.addEventListener('keydown', tutupFlyoutSaatEsc);
});

onBeforeUnmount(() => {
    clearTimeout(jedaFilter);
    document.removeEventListener('click', tutupFlyoutSaatKlikLuar);
    document.removeEventListener('keydown', tutupFlyoutSaatEsc);
});
</script>


<template>
    <Card body-class="overflow-x-auto">
        <template v-if="slots.header || title" #header>
            <slot name="header">{{ title }}</slot>
        </template>

        <table class="data-table">
            <thead>
                <tr>
                    <th
                        v-for="[kolom, label] in Object.entries(columns)"
                        :key="kolom"
                        :class="classHeaderSort(kolom)"
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
            <tbody>
                <tr v-for="(row, index) in rows" :key="index">
                    <td v-for="column in Object.keys(columns)" :key="column">
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

        <template #footer>
            <div class="flex justify-between items-center">
                <small class="text-gray-500">Total {{ formatTotal(total) }} data</small>
                <Pagination :links="links" :only="paginationOnly" />
            </div>
        </template>
    </Card>
</template>
