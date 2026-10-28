<script setup>
import { onMounted, ref } from 'vue';
import Badge from '@/Components/ui/atoms/Badge.vue';
import Alert from '@/Components/ui/molecules/Alert.vue';
import DataTable from '@/Components/ui/organisms/DataTable.vue';
import { angka, rupiah, tanggalWaktuPendek } from '@/Support/format.js';

// Riwayat transaksi satu distributor di dalam drawer kedua Lap. Loading Barang (lihat Menu/Show.vue).
// Data diambil dari endpoint riwayat-distributor (lihat LapLoadingBarangController); id dipakai bila ada,
// karena nama distributor bisa kembar antar depo.
const props = defineProps({
    distributor: { type: Object, required: true },
});

const KOLOM = {
    trans_code: 'Trans Code',
    datetime_trans: 'Datetime Trans',
    koordinator_name: 'Koordinator Name',
    warehouse_name: 'Warehouse Name',
    total_qty: 'Total Qty',
    grand_total: 'Grand Total',
};

const memuat = ref(true);
const galat = ref(null);
const total = ref(0);
const baris = ref([]);

async function ambil() {
    memuat.value = true;
    galat.value = null;

    try {
        const query = new URLSearchParams(props.distributor.id ? { distributor_id: props.distributor.id } : { distributor_name: props.distributor.nama });
        const respon = await fetch(`/lap_loading_barang/riwayat-distributor?${query}`, { headers: { Accept: 'application/json' } });

        if (!respon.ok) {
            throw new Error(String(respon.status));
        }

        const hasil = await respon.json();
        total.value = hasil.total;
        baris.value = hasil.data;
    } catch {
        galat.value = 'Riwayat transaksi belum bisa dimuat. Coba lagi.';
    } finally {
        memuat.value = false;
    }
}

onMounted(ambil);
</script>

<template>
    <div class="flex flex-col gap-3 pb-8">
        <Alert v-if="galat" variant="warning">{{ galat }}</Alert>
        <template v-else>
            <Alert v-if="total > baris.length" variant="info">Menampilkan {{ angka(baris.length) }} transaksi terbaru dari {{ angka(total) }} transaksi.</Alert>

            <DataTable
                :columns="KOLOM"
                :rows="baris"
                :total="total"
                :memuat="memuat"
                empty-text="Belum ada transaksi untuk distributor ini."
            >
                <template #cell-datetime_trans="{ row }">
                    <template v-if="tanggalWaktuPendek(row.datetime_trans)">
                        {{ tanggalWaktuPendek(row.datetime_trans).tanggal }}
                        <Badge variant="light">{{ tanggalWaktuPendek(row.datetime_trans).jam }}</Badge>
                    </template>
                    <template v-else>{{ row.datetime_trans }}</template>
                </template>
                <template #cell-total_qty="{ row }">{{ row.total_qty === null ? '-' : angka(row.total_qty) }}</template>
                <template #cell-grand_total="{ row }">{{ row.grand_total === null ? '-' : rupiah(row.grand_total) }}</template>
            </DataTable>
        </template>
    </div>
</template>
