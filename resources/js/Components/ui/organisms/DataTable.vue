<script setup>
import { useSlots } from 'vue';
import Card from '@/Components/ui/molecules/Card.vue';
import Pagination from '@/Components/ui/molecules/Pagination.vue';
import Badge from '@/Components/ui/atoms/Badge.vue';

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
});

// Kolom "Aksi" muncul bila halaman mengisi slot #aksi="{ row }".
const slots = useSlots();

function formatTotal(total) {
    return new Intl.NumberFormat('id-ID').format(total);
}

function badgeVariant(column, value) {
    const peta = props.badgeVariants[column];

    return peta?.[value] ?? (value ? peta?.['*'] : undefined) ?? (value ? 'success' : 'secondary');
}
</script>

<template>
    <Card body-class="overflow-x-auto">
        <template v-if="title" #header>{{ title }}</template>

        <table class="data-table">
            <thead>
                <tr>
                    <th v-for="label in Object.values(columns)" :key="label">{{ label }}</th>
                    <th v-if="slots.aksi">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(row, index) in rows" :key="index">
                    <td v-for="column in Object.keys(columns)" :key="column">
                        <Badge v-if="badgeColumns.includes(column)" :variant="badgeVariant(column, row[column])">{{ row[column] || '-' }}</Badge>
                        <template v-else>{{ row[column] }}</template>
                    </td>
                    <td v-if="slots.aksi" class="whitespace-nowrap">
                        <slot name="aksi" :row="row" />
                    </td>
                </tr>
                <tr v-if="!rows.length">
                    <td :colspan="Object.keys(columns).length + (slots.aksi ? 1 : 0)" class="text-center text-gray-500 py-4">Tidak ada data.</td>
                </tr>
            </tbody>
        </table>

        <template #footer>
            <div class="flex justify-between items-center">
                <small class="text-gray-500">Total {{ formatTotal(total) }} data</small>
                <Pagination :links="links" />
            </div>
        </template>
    </Card>
</template>
