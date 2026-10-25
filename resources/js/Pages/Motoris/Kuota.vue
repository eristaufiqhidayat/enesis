<script setup>
import { ref } from 'vue';
import { Deferred, Head, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from '@/Components/ui/atoms/Button.vue';
import Input from '@/Components/ui/atoms/Input.vue';
import Label from '@/Components/ui/atoms/Label.vue';
import Card from '@/Components/ui/molecules/Card.vue';
import PageHeader from '@/Components/ui/molecules/PageHeader.vue';
import TableSkeleton from '@/Components/ui/molecules/TableSkeleton.vue';
import KuotaDepoTabel from './partials/KuotaDepoTabel.vue';
import { useAmbilData } from '@/Support/ambilData.js';

// `depo` dan `riwayat` kosong saat masih ditunda (lihat <Deferred> di bawah).
const props = defineProps({
    slug: { type: String, required: true },
    judul: { type: String, required: true },
    bulan: { type: String, required: true },
    namaBulan: { type: String, required: true },
    depo: { type: Array, default: () => [] },
    riwayat: { type: Array, default: () => [] },
    bolehUbah: { type: Boolean, default: false },
});

const appName = usePage().props.appName;

// Kunjungan partial (Tampilkan) mengambil ulang depo dan riwayat dalam satu request, tanpa skeleton.
const PROPS_KUOTA = ['bulan', 'namaBulan', 'depo', 'riwayat', 'bolehUbah'];

const filterBulan = ref(props.bulan);

// Tombol Tampilkan aktif lagi hanya saat bulan berbeda dari yang sedang tampil.
const { memuat, berubah, ambil } = useAmbilData({
    form: () => ({ bulan: filterBulan.value }),
    diterapkan: () => ({ bulan: props.bulan }),
});

function tampilkan() {
    ambil('/kuota_motoris', { bulan: filterBulan.value }, { only: PROPS_KUOTA });
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
                    <Button type="submit" variant="outline-secondary" :loading="memuat" :disabled="!berubah">Tampilkan</Button>
                </form>
            </template>

            <Deferred data="depo">
                <template #fallback>
                    <TableSkeleton />
                </template>

                <KuotaDepoTabel :depo="depo" :bulan="bulan" :nama-bulan="namaBulan" :boleh-ubah="bolehUbah" />
            </Deferred>
        </Card>

        <Deferred data="riwayat">
            <template #fallback>
                <Card body-class="overflow-x-auto">
                    <TableSkeleton />
                </Card>
            </template>

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
        </Deferred>
    </AppLayout>
</template>
