<script setup>
import { computed } from 'vue';
import { rupiah } from '@/Support/format.js';

// Detail satu transaksi loading barang di dalam drawer (lihat Menu/Show.vue). Label dan urutan mengikuti kolom tabel.
const props = defineProps({
    row: { type: Object, required: true },
    columns: { type: Object, required: true },
});

const KOLOM_RUPIAH = ['grand_total'];
const KOLOM_ANGKA = ['total_item', 'total_qty'];
const formatAngka = new Intl.NumberFormat('id-ID');

// Nilai kosong ditampilkan "-"; kolom angka dan rupiah memakai pemisah ribuan (titik).
function nilai(kolom) {
    const isi = props.row[kolom];

    if (isi === null || isi === '' || isi === undefined) {
        return '-';
    }

    if (Number.isNaN(Number(isi))) {
        return isi;
    }

    if (KOLOM_RUPIAH.includes(kolom)) {
        return rupiah(Number(isi));
    }

    return KOLOM_ANGKA.includes(kolom) ? formatAngka.format(Number(isi)) : isi;
}

const baris = computed(() => Object.entries(props.columns).map(([kolom, label]) => ({ kolom, label, nilai: nilai(kolom) })));
</script>

<template>
    <dl class="flex flex-col pb-8">
        <div v-for="item in baris" :key="item.kolom" class="detail-depo-baris">
            <dt class="text-gray-500">{{ item.label }}</dt>
            <dd class="break-words">{{ item.nilai }}</dd>
        </div>
    </dl>
</template>
