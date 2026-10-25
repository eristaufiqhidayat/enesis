<script setup>
import { computed, ref } from 'vue';
import { Deferred, Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from '@/Components/ui/atoms/Button.vue';
import Input from '@/Components/ui/atoms/Input.vue';
import Label from '@/Components/ui/atoms/Label.vue';
import Select from '@/Components/ui/atoms/Select.vue';
import Card from '@/Components/ui/molecules/Card.vue';
import PageHeader from '@/Components/ui/molecules/PageHeader.vue';
import TableSkeleton from '@/Components/ui/molecules/TableSkeleton.vue';
import Tabs from '@/Components/ui/molecules/Tabs.vue';
import Absensi from './partials/Absensi.vue';
import Coverage from './partials/Coverage.vue';
import Performance from './partials/Performance.vue';
import { bersihkanQuery } from '@/Support/query.js';

// `laporan` ({ baris, total }) kosong saat masih ditunda (lihat <Deferred> di bawah).
const props = defineProps({
    slug: { type: String, required: true },
    judul: { type: String, required: true },
    tab: { type: String, required: true },
    tabs: { type: Array, required: true },
    bulan: { type: String, required: true },
    periode: { type: Object, required: true },
    filter: { type: Object, required: true },
    bolehKuota: { type: Boolean, default: false },
    daftarDepo: { type: [Object, Array], default: () => ({}) },
    daftarKoordinator: { type: [Object, Array], default: () => ({}) },
    laporan: { type: Object, default: () => ({}) },
});

// Kunjungan partial (pindah tab, Tampilkan) mengambil ulang laporan dalam satu request, tanpa skeleton.
const PROPS_LAPORAN = ['tab', 'bulan', 'periode', 'filter', 'bolehKuota', 'daftarDepo', 'daftarKoordinator', 'laporan'];

const appName = usePage().props.appName;

const subtitle = computed(() => `Dihitung dari data aplikasi motoris dan riwayat aplikasi lama · periode ${props.periode.dari} – ${props.periode.sampai} · HK standar ${props.periode.hkStandar} hari`);

// Isian filter mengikuti query yang sedang aktif; hanya tab Absensi yang punya filter sales, depo, dan koordinator.
const filterBulan = ref(props.bulan);
const filterQ = ref(props.filter.q ?? '');
const filterDepo = ref(props.filter.depo ? String(props.filter.depo) : '');
const filterKoordinator = ref(props.filter.koordinator ? String(props.filter.koordinator) : '');

function pindahTab(kode) {
    router.get('/motoris_report', { tab: kode, bulan: props.bulan }, { only: PROPS_LAPORAN });
}

function saring() {
    const tambahan = props.tab === 'absensi'
        ? { q: filterQ.value, depo: filterDepo.value, koordinator: filterKoordinator.value }
        : {};

    router.get('/motoris_report', bersihkanQuery({ tab: props.tab, bulan: filterBulan.value, ...tambahan }), { only: PROPS_LAPORAN });
}
</script>

<template>
    <Head :title="`${judul} · ${appName}`" />
    <AppLayout :breadcrumb-slug="slug">
        <PageHeader :title="judul" :subtitle="subtitle">
            <template v-if="bolehKuota && tab !== 'absensi'" #actions>
                <Button :tag="Link" variant="outline-secondary" :href="`/kuota_motoris?bulan=${bulan}`">Atur kuota motoris</Button>
            </template>
        </PageHeader>

        <Tabs :tabs="tabs" :model-value="tab" @update:model-value="pindahTab" />

        <Card body-class="overflow-x-auto">
            <template #header>
                <form class="flex flex-wrap items-end gap-2" role="search" @submit.prevent="saring">
                    <div>
                        <Label for="bulan">Bulan</Label>
                        <Input id="bulan" v-model="filterBulan" type="month" />
                    </div>
                    <template v-if="tab === 'absensi'">
                        <div class="grow min-w-[12rem]">
                            <Label for="q">Nama sales</Label>
                            <Input id="q" v-model="filterQ" type="search" placeholder="Cari nama sales…" />
                        </div>
                        <div>
                            <Label for="depo">Depo</Label>
                            <Select id="depo" v-model="filterDepo">
                                <option value="">Semua depo</option>
                                <option v-for="(nama, id) in daftarDepo" :key="id" :value="String(id)">{{ nama }}</option>
                            </Select>
                        </div>
                        <div v-if="Object.keys(daftarKoordinator).length">
                            <Label for="koordinator">Koordinator</Label>
                            <Select id="koordinator" v-model="filterKoordinator">
                                <option value="">Semua koordinator</option>
                                <option v-for="(nama, id) in daftarKoordinator" :key="id" :value="String(id)">{{ nama }}</option>
                            </Select>
                        </div>
                    </template>
                    <Button type="submit">Tampilkan</Button>
                </form>
            </template>

            <Deferred data="laporan">
                <template #fallback>
                    <TableSkeleton />
                </template>

                <Coverage v-if="tab === 'coverage'" :baris="laporan.baris" :total="laporan.total" />
                <Performance v-else-if="tab === 'performance'" :baris="laporan.baris" :total="laporan.total" />
                <Absensi v-else :baris="laporan.baris" />
            </Deferred>
        </Card>
    </AppLayout>
</template>
