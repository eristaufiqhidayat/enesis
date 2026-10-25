<script setup>
import { useForm } from '@inertiajs/vue3';
import Badge from '@/Components/ui/atoms/Badge.vue';
import Button from '@/Components/ui/atoms/Button.vue';
import FieldError from '@/Components/ui/atoms/FieldError.vue';
import Input from '@/Components/ui/atoms/Input.vue';

const props = defineProps({
    depo: { type: Array, required: true },
    bulan: { type: String, required: true },
    namaBulan: { type: String, required: true },
    bolehUbah: { type: Boolean, default: false },
});

// Padanan badge sumber kuota: [label, variant].
const SUMBER = {
    enesis: ['Isian admin', 'primary'],
    marketama: ['Master Depo Marketama', 'secondary'],
    kosong: ['Belum ada', 'warning'],
};

// Kosong = tidak diisi (kuota kembali ke bulan sebelumnya / Marketama); form hanya menyimpan nilai yang berubah.
// Form dibuat di partial ini karena baris depo baru ada setelah data ditunda (lihat Motoris/Kuota.vue).
const form = useForm({
    bulan: props.bulan,
    kuota: Object.fromEntries(props.depo.map((d) => [d.id, d.kuota ?? ''])),
});

function simpan() {
    form.post('/kuota_motoris', { preserveScroll: true, onSuccess: () => form.defaults() });
}
</script>

<template>
    <form @submit.prevent="simpan">
        <table class="data-table">
            <thead>
                <tr>
                    <th scope="col">Kota (City Group)</th>
                    <th scope="col">Depo</th>
                    <th scope="col" class="text-end">Kuota Marketama</th>
                    <th scope="col" class="text-end">Kuota {{ namaBulan }}</th>
                    <th scope="col">Sumber</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="d in depo" :key="d.id" :class="{ 'opacity-60': !d.aktif }">
                    <td>{{ d.kota ?? '-' }}</td>
                    <td>
                        {{ d.nama }}
                        <Badge v-if="!d.aktif">Tidak aktif</Badge>
                    </td>
                    <td class="text-end">{{ d.kuota_marketama ?? '-' }}</td>
                    <td class="text-end">
                        <template v-if="bolehUbah">
                            <Input
                                v-model="form.kuota[d.id]"
                                type="number"
                                min="0"
                                step="1"
                                class="w-24 text-end ms-auto"
                                :name="`kuota[${d.id}]`"
                                :aria-label="`Kuota ${d.nama}`"
                            />
                            <FieldError v-if="form.errors[`kuota.${d.id}`]">{{ form.errors[`kuota.${d.id}`] }}</FieldError>
                        </template>
                        <template v-else>{{ d.kuota ?? '-' }}</template>
                    </td>
                    <td>
                        <Badge :variant="SUMBER[d.sumber][1]">{{ SUMBER[d.sumber][0] }}</Badge>
                        <span v-if="d.sejak" class="text-gray-500">sejak {{ d.sejak }}</span>
                    </td>
                </tr>
                <tr v-if="!depo.length">
                    <td colspan="5" class="text-center text-gray-500">Belum ada data depo.</td>
                </tr>
            </tbody>
        </table>

        <div v-if="bolehUbah && depo.length" class="flex flex-wrap items-center justify-between gap-2 p-3">
            <p class="mb-0 text-xs text-gray-500">Yang disimpan hanya depo yang nilainya berubah. Kosongkan isian untuk menghapus kuota bulan ini.</p>
            <Button type="submit" :disabled="form.processing">Simpan kuota mulai {{ namaBulan }}</Button>
        </div>
    </form>
</template>
