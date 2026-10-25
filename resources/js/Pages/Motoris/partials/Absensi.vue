<script setup>
import Badge from '@/Components/ui/atoms/Badge.vue';
import Pagination from '@/Components/ui/molecules/Pagination.vue';

// baris = paginator Laravel (data, from, links, last_page).
defineProps({ baris: { type: Object, required: true } });
</script>

<template>
    <table class="data-table">
        <thead>
            <tr>
                <th scope="col">No</th>
                <th scope="col">Nama Sales Motoris</th>
                <th scope="col">Nama Koordinator</th>
                <th scope="col">Nama Depo</th>
                <th scope="col" class="text-end">Hari Kerja</th>
                <th scope="col" class="text-end">Total Kehadiran</th>
                <th scope="col" class="text-end">Total Tidak Hadir</th>
            </tr>
        </thead>
        <tbody>
            <tr v-for="(b, i) in baris.data" :key="i">
                <td>{{ baris.from + i }}</td>
                <td>
                    {{ b.nama }}
                    <Badge v-if="!b.aktif">Tidak aktif</Badge>
                </td>
                <td>{{ b.koordinator ?? '-' }}</td>
                <td>{{ b.depo ?? '-' }}</td>
                <td class="text-end">{{ b.hari_kerja }}</td>
                <td class="text-end">{{ b.hadir }}</td>
                <td class="text-end">{{ b.tidak_hadir }}</td>
            </tr>
            <tr v-if="!baris.data.length">
                <td colspan="7" class="text-center text-gray-500">Tidak ada sales untuk filter ini.</td>
            </tr>
        </tbody>
    </table>
    <div v-if="baris.last_page > 1" class="p-3">
        <Pagination :links="baris.links" :only="['laporan']" />
    </div>
</template>
