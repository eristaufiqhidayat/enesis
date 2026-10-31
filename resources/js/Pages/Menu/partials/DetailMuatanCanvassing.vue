<script setup>
import { onMounted, ref } from 'vue';
import Badge from '@/Components/ui/atoms/Badge.vue';
import Alert from '@/Components/ui/molecules/Alert.vue';
import DataTable from '@/Components/ui/organisms/DataTable.vue';

// Daftar produk satu muatan canvassing di dalam drawer tabel barang_canvassing (lihat Menu/Show.vue).
// Data diambil dari endpoint item (lihat BarangCanvassingController).
const props = defineProps({
    row: { type: Object, required: true },
});

const KOLOM = {
    product_name: 'Produk',
    product_sku: 'SKU',
    category_name: 'Kategori',
    brand_name: 'Brand',
    unit_name: 'Satuan',
    bawa_qty: 'Qty Bawa',
    total_qty: 'Qty Diterima',
    sale_qty: 'Qty Terjual',
    sisa_qty: 'Sisa',
    return_qty: 'Qty Kembali',
    status_terjual: 'Status',
};

const memuat = ref(true);
const galat = ref(null);
const baris = ref([]);

async function ambil() {
    memuat.value = true;
    galat.value = null;

    try {
        const respon = await fetch(`/barang_canvassing/${props.row.source_id}/item`, { headers: { Accept: 'application/json' } });

        if (!respon.ok) {
            throw new Error(String(respon.status));
        }

        baris.value = (await respon.json()).data;
    } catch {
        galat.value = 'Detail produk belum bisa dimuat. Coba lagi.';
    } finally {
        memuat.value = false;
    }
}

onMounted(ambil);
</script>

<template>
    <div class="flex flex-col gap-3">
        <div class="flex flex-wrap gap-x-5 gap-y-1 text-xs text-gray-600">
            <span>Sales: <b class="text-gray-900">{{ row.salesperson_name }}</b></span>
            <span>Depo: <b class="text-gray-900">{{ row.warehouse_name || '-' }}</b></span>
            <span>Qty Bawa: <b class="text-gray-900">{{ row.total_qty_bawa }}</b></span>
        </div>
        <Alert v-if="galat" variant="warning">{{ galat }}</Alert>
        <DataTable
            v-else
            tinggi-penuh
            tinggi-tetap
            :columns="KOLOM"
            :rows="baris"
            :total="baris.length"
            :memuat="memuat"
            empty-text="Muatan ini belum punya produk."
        >
            <template #cell-return_qty="{ row: r }">{{ r.return_qty ?? '-' }}</template>
            <template #cell-status_terjual="{ row: r }">
                <Badge :variant="r.status_terjual === 'Habis Terjual' ? 'success' : 'warning'">{{ r.status_terjual }}</Badge>
            </template>
        </DataTable>
    </div>
</template>
