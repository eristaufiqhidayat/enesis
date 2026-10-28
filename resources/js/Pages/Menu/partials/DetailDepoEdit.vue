<script setup>
import { reactive } from 'vue';
import Button from '@/Components/ui/atoms/Button.vue';
import Input from '@/Components/ui/atoms/Input.vue';
import Label from '@/Components/ui/atoms/Label.vue';

// Form edit data depo (dibuka dari tombol Edit di drawer detail depo). Penyimpanan belum tersambung:
// data master_depo masih berasal dari impor Marketama, jadi tombol Simpan dinonaktifkan.
const props = defineProps({
    depo: { type: Object, required: true },
});

const emit = defineEmits(['batal']);

const FIELD = [
    { kunci: 'nama', label: 'Nama' },
    { kunci: 'projek', label: 'Projek' },
    { kunci: 'kota_besar', label: 'Kota besar' },
    { kunci: 'minimum_sku', label: 'Minimum SKU', tipe: 'number' },
    { kunci: 'alamat', label: 'Alamat' },
    { kunci: 'latitude', label: 'Latitude', tipe: 'number' },
    { kunci: 'longitude', label: 'Longitude', tipe: 'number' },
    { kunci: 'kuota_koordinator', label: 'Kuota koordinator', tipe: 'number' },
    { kunci: 'kuota_sales_motoris', label: 'Kuota sales motoris', tipe: 'number' },
    { kunci: 'target_call', label: 'Target call', tipe: 'number' },
    { kunci: 'target_ec', label: 'Target EC', tipe: 'number' },
    { kunci: 'outlet_1_sku', label: 'Outlet 1 SKU', tipe: 'number' },
    { kunci: 'durasi_tracking_gps', label: 'Durasi tracking GPS' },
    { kunci: 'insentif_per_poin', label: 'Insentif per poin', tipe: 'number' },
    { kunci: 'minimal_value_transaksi', label: 'Minimal value transaksi', tipe: 'number' },
];

const form = reactive(Object.fromEntries(FIELD.map((field) => [field.kunci, props.depo[field.kunci] ?? ''])));
</script>

<template>
    <form class="flex flex-col gap-4 pb-8" @submit.prevent>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div v-for="field in FIELD" :key="field.kunci" class="flex flex-col gap-1">
                <Label :for="`edit-${field.kunci}`">{{ field.label }}</Label>
                <Input :id="`edit-${field.kunci}`" v-model="form[field.kunci]" :type="field.tipe ?? 'text'" />
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-end gap-2 border-t border-gray-200 pt-4">
            <p class="me-auto text-xs text-gray-500">Penyimpanan belum tersedia: data depo masih berasal dari impor Marketama.</p>
            <Button variant="outline-secondary" @click="emit('batal')">Batal</Button>
            <Button type="submit" disabled>Simpan</Button>
        </div>
    </form>
</template>
