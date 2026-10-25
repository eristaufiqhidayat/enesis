<script setup>
import { computed } from 'vue';
import { angka, desimal } from '@/Support/format.js';

const props = defineProps({
    baris: { type: Array, required: true },
    total: { type: Object, required: true },
});

const kolomKota = computed(() => (props.total.kota === 'TOTAL 0 CITY' ? 'City' : props.total.kota.replace('TOTAL ', '')));
const persen = (nilai) => (nilai === null ? '-' : `${desimal(nilai)}%`);
</script>

<template>
    <table class="data-table">
        <thead>
            <tr>
                <th scope="col">{{ kolomKota }}</th>
                <th scope="col" class="text-end">Quota Motoris</th>
                <th scope="col" class="text-end">Already Transaction</th>
                <th scope="col" class="text-end">Active End Periode</th>
                <th scope="col" class="text-end">Vacant</th>
                <th scope="col" class="text-end">Coverage %</th>
            </tr>
        </thead>
        <tbody>
            <tr v-for="(b, i) in baris" :key="i">
                <td>{{ b.kota }}</td>
                <td class="text-end">{{ angka(b.kuota) }}</td>
                <td class="text-end">{{ angka(b.sudah_transaksi) }}</td>
                <td class="text-end">{{ angka(b.aktif) }}</td>
                <td class="text-end">{{ angka(b.vacant) }}</td>
                <td class="text-end">{{ persen(b.coverage) }}</td>
            </tr>
            <tr v-if="!baris.length">
                <td colspan="6" class="text-center text-gray-500">Belum ada data depo.</td>
            </tr>
        </tbody>
        <tfoot v-if="baris.length">
            <tr class="font-semibold">
                <th scope="row">{{ total.kota }}</th>
                <td class="text-end">{{ angka(total.kuota) }}</td>
                <td class="text-end">{{ angka(total.sudah_transaksi) }}</td>
                <td class="text-end">{{ angka(total.aktif) }}</td>
                <td class="text-end">{{ angka(total.vacant) }}</td>
                <td class="text-end">{{ persen(total.coverage) }}</td>
            </tr>
        </tfoot>
    </table>
</template>
