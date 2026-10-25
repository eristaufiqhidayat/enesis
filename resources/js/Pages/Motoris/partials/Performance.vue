<script setup>
import { computed } from 'vue';
import { angka, desimal } from '@/Support/format.js';

const props = defineProps({
    baris: { type: Array, required: true },
    total: { type: Object, required: true },
});

const kolomKota = computed(() => (props.total.kota === 'TOTAL 0 CITY' ? 'City' : props.total.kota.replace('TOTAL ', '')));
</script>

<template>
    <table class="data-table">
        <thead>
            <tr>
                <th scope="col">{{ kolomKota }}</th>
                <th scope="col" class="text-end">Motoris Kuota</th>
                <th scope="col" class="text-end">Motoris Already Transaction</th>
                <th scope="col" class="text-end">Motoris Actual End Periode</th>
                <th scope="col" class="text-end">HK Standar</th>
                <th scope="col" class="text-end">Act HK</th>
                <th scope="col" class="text-end">Target</th>
                <th scope="col" class="text-end">Call</th>
                <th scope="col" class="text-end">EC</th>
                <th scope="col" class="text-end">IPT</th>
                <th scope="col" class="text-end">Value</th>
            </tr>
        </thead>
        <tbody>
            <tr v-for="(b, i) in baris" :key="i">
                <td>{{ b.kota }}</td>
                <td class="text-end">{{ angka(b.kuota) }}</td>
                <td class="text-end">{{ angka(b.sudah_transaksi) }}</td>
                <td class="text-end">{{ angka(b.aktif) }}</td>
                <td class="text-end">{{ b.hk_standar }}</td>
                <td class="text-end">{{ desimal(b.act_hk) }}</td>
                <td class="text-end">{{ angka(b.target) }}</td>
                <td class="text-end">{{ angka(b.call) }}</td>
                <td class="text-end">{{ angka(b.ec) }}</td>
                <td class="text-end">{{ desimal(b.ipt) }}</td>
                <td class="text-end whitespace-nowrap">Rp {{ angka(b.value) }}</td>
            </tr>
            <tr v-if="!baris.length">
                <td colspan="11" class="text-center text-gray-500">Belum ada data depo.</td>
            </tr>
        </tbody>
        <tfoot v-if="baris.length">
            <tr class="font-semibold">
                <th scope="row">{{ total.kota }}</th>
                <td class="text-end">{{ angka(total.kuota) }}</td>
                <td class="text-end">{{ angka(total.sudah_transaksi) }}</td>
                <td class="text-end">{{ angka(total.aktif) }}</td>
                <td class="text-end">{{ total.hk_standar }}</td>
                <td class="text-end">{{ desimal(total.act_hk) }}</td>
                <td class="text-end">{{ angka(total.target) }}</td>
                <td class="text-end">{{ angka(total.call) }}</td>
                <td class="text-end">{{ angka(total.ec) }}</td>
                <td class="text-end">{{ desimal(total.ipt) }}</td>
                <td class="text-end whitespace-nowrap">Rp {{ angka(total.value) }}</td>
            </tr>
        </tfoot>
    </table>
</template>
