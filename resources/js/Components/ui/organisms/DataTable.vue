<script setup>
import Card from '@/Components/ui/molecules/Card.vue';
import Pagination from '@/Components/ui/molecules/Pagination.vue';
import Badge from '@/Components/ui/atoms/Badge.vue';

defineProps({
    title: { type: String, default: null },
    columns: { type: Object, required: true },
    rows: { type: Array, default: () => [] },
    total: { type: Number, default: 0 },
    links: { type: Array, default: () => [] },
    badgeColumns: { type: Array, default: () => [] },
});

function formatTotal(total) {
    return new Intl.NumberFormat('id-ID').format(total);
}

function badgeVariant(value) {
    return value ? 'success' : 'secondary';
}
</script>

<template>
    <Card body-class="overflow-x-auto">
        <template v-if="title" #header>{{ title }}</template>

        <table class="data-table">
            <thead>
                <tr>
                    <th v-for="label in Object.values(columns)" :key="label">{{ label }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(row, index) in rows" :key="index">
                    <td v-for="column in Object.keys(columns)" :key="column">
                        <Badge v-if="badgeColumns.includes(column)" :variant="badgeVariant(row[column])">{{ row[column] || '-' }}</Badge>
                        <template v-else>{{ row[column] }}</template>
                    </td>
                </tr>
                <tr v-if="!rows.length">
                    <td :colspan="Object.keys(columns).length" class="text-center text-gray-500 py-4">Tidak ada data.</td>
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
