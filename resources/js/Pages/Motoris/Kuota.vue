<script setup>
import { ref } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Badge from '@/Components/ui/atoms/Badge.vue';
import Button from '@/Components/ui/atoms/Button.vue';
import FieldError from '@/Components/ui/atoms/FieldError.vue';
import Input from '@/Components/ui/atoms/Input.vue';
import Label from '@/Components/ui/atoms/Label.vue';
import Card from '@/Components/ui/molecules/Card.vue';
import PageHeader from '@/Components/ui/molecules/PageHeader.vue';

const props = defineProps({
    slug: { type: String, required: true },
    judul: { type: String, required: true },
    bulan: { type: String, required: true },
    namaBulan: { type: String, required: true },
    depo: { type: Array, required: true },
    riwayat: { type: Array, required: true },
    bolehUbah: { type: Boolean, default: false },
});

const appName = usePage().props.appName;

// Padanan badge sumber kuota: [label, variant].
const SUMBER = {
    enesis: ['Isian admin', 'primary'],
    marketama: ['Master Depo Marketama', 'secondary'],
    kosong: ['Belum ada', 'warning'],
};

// Kosong = tidak diisi (kuota kembali ke bulan sebelumnya / Marketama); form hanya menyimpan nilai yang berubah.
const form = useForm({
    bulan: props.bulan,
    kuota: Object.fromEntries(props.depo.map((d) => [d.id, d.kuota ?? ''])),
});

const filterBulan = ref(props.bulan);

function tampilkan() {
    router.get('/kuota_motoris', { bulan: filterBulan.value });
}

function simpan() {
    form.post('/kuota_motoris', { preserveScroll: true, onSuccess: () => form.defaults() });
}
</script>

<template>
    <Head :title="`${judul} · ${appName}`" />
    <AppLayout :breadcrumb-slug="slug">
        <PageHeader
            :title="judul"
            subtitle="Kuota sales motoris per depo untuk Motoris Report (Coverage & Performance). Kuota berlaku mulai bulan yang dipilih sampai diubah lagi; depo yang belum diisi memakai kuota dari Master Depo Marketama."
        />

        <Card body-class="overflow-x-auto" class="mb-3">
            <template #header>
                <form class="flex flex-wrap items-end gap-2" @submit.prevent="tampilkan">
                    <div>
                        <Label for="bulan">Berlaku mulai bulan</Label>
                        <Input id="bulan" v-model="filterBulan" type="month" />
                    </div>
                    <Button type="submit" variant="outline-secondary">Tampilkan</Button>
                </form>
            </template>

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
        </Card>

        <Card v-if="riwayat.length" body-class="overflow-x-auto">
            <template #header><span class="font-semibold">Riwayat isian kuota</span></template>
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col">Depo</th>
                        <th scope="col">Berlaku mulai</th>
                        <th scope="col" class="text-end">Kuota</th>
                        <th scope="col">Diubah</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(r, i) in riwayat" :key="i">
                        <td>{{ r.depo ?? '-' }}</td>
                        <td>{{ r.berlaku }}</td>
                        <td class="text-end">{{ r.kuota }}</td>
                        <td class="text-gray-500">{{ r.diubah }}</td>
                    </tr>
                </tbody>
            </table>
        </Card>
    </AppLayout>
</template>
