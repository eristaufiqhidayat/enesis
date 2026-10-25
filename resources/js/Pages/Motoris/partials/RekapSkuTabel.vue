<script setup>
import Badge from '@/Components/ui/atoms/Badge.vue';
import Card from '@/Components/ui/molecules/Card.vue';
import { angkaRingkas } from '@/Support/format.js';

const props = defineProps({
    judul: { type: String, required: true },
    tercapai: { type: Boolean, required: true },
    jumlah: { type: Number, required: true },
    perMotoris: { type: Boolean, required: true },
    baris: { type: Array, required: true },
    total: { type: Object, required: true },
});

const kolom = props.perMotoris ? 7 : 6;
</script>

<template>
    <Card body-class="overflow-x-auto">
        <template #header>
            <div class="text-xs font-semibold" :class="tercapai ? 'text-green-700' : 'text-red-700'">{{ judul }}</div>
            <div class="text-xs text-gray-500">{{ baris.length }} dari {{ jumlah }} total {{ perMotoris ? 'motoris' : 'depo' }}</div>
        </template>
        <table class="data-table">
            <thead>
                <tr>
                    <th scope="col">No</th>
                    <th scope="col">Depo</th>
                    <th v-if="perMotoris" scope="col">Motoris</th>
                    <th scope="col" class="text-end">Total Qty</th>
                    <th scope="col" class="text-end">Hari Kerja</th>
                    <th scope="col" class="text-end">{{ perMotoris ? 'Avg/Day' : 'Avg/Day/Motoris' }}</th>
                    <th scope="col">Kategori</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(b, i) in baris" :key="b.id">
                    <td>{{ i + 1 }}</td>
                    <template v-if="perMotoris">
                        <td>{{ b.depo ?? '-' }}</td>
                        <td>
                            {{ b.nama }}
                            <Badge v-if="!b.aktif">Tidak aktif</Badge>
                        </td>
                    </template>
                    <td v-else>{{ b.nama }}</td>
                    <td class="text-end">{{ angkaRingkas(b.qty) }}</td>
                    <td class="text-end">{{ b.hari_kerja }}</td>
                    <td class="text-end" :title="!perMotoris ? `${b.hari_motoris} hari-motoris bertransaksi` : null">{{ angkaRingkas(b.avg) }}</td>
                    <td><Badge :variant="tercapai ? 'success' : 'danger'">{{ tercapai ? 'Tercapai' : 'Belum' }}</Badge></td>
                </tr>
                <tr v-if="!baris.length">
                    <td :colspan="kolom" class="text-center text-gray-500">Tidak ada data</td>
                </tr>
            </tbody>
            <tfoot v-if="baris.length">
                <tr class="font-semibold">
                    <th scope="row" :colspan="perMotoris ? 3 : 2">TOTAL</th>
                    <td class="text-end">{{ angkaRingkas(total.qty) }}</td>
                    <td class="text-end">{{ total.hari_kerja }}</td>
                    <td class="text-end">{{ angkaRingkas(total.avg) }}</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </Card>
</template>
