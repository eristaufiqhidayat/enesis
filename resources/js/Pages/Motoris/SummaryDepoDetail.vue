<script setup>
import { computed } from 'vue';
import { Deferred, Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Badge from '@/Components/ui/atoms/Badge.vue';
import Button from '@/Components/ui/atoms/Button.vue';
import Card from '@/Components/ui/molecules/Card.vue';
import PageHeader from '@/Components/ui/molecules/PageHeader.vue';
import TableSkeleton from '@/Components/ui/molecules/TableSkeleton.vue';
import { angka, angkaRingkas } from '@/Support/format.js';

// total dan tim kosong saat masih ditunda (lihat <Deferred> di bawah).
const props = defineProps({
    slug: { type: String, required: true },
    judul: { type: String, required: true },
    depo: { type: Object, required: true },
    total: { type: Object, default: () => ({}) },
    tim: { type: Array, default: () => [] },
    periode: { type: Object, required: true },
    filter: { type: Object, required: true },
});

const appName = usePage().props.appName;
const periodeText = computed(() => (props.periode.sama ? props.periode.dari : `${props.periode.dari} – ${props.periode.sampai}`));
const rupiah = (n) => `Rp ${angka(n)}`;
</script>

<template>
    <Head :title="`${judul} · ${appName}`" />
    <AppLayout :breadcrumb-slug="slug">
        <PageHeader :title="depo.nama" :subtitle="depo.distributor ?? null">
            <template #actions>
                <Button :tag="Link" variant="outline-secondary" :href="`/summary_all_depo?${new URLSearchParams(filter)}`">Kembali ke Summary All Depo</Button>
            </template>
        </PageHeader>

        <Card>
            <div class="text-xs text-gray-500 mb-2">Periode: <b class="text-gray-700">{{ periodeText }}</b></div>

            <Deferred :data="['total', 'tim']">
            <template #fallback>
                <TableSkeleton />
            </template>

            <div class="rounded-md border border-blue-100 bg-blue-50/60 px-3 py-2 mb-3 text-xs">
                <div class="font-semibold text-blue-700 uppercase tracking-wide mb-1 border-l-2 border-blue-600 ps-2">Total keseluruhan depo</div>
                <div class="flex flex-wrap gap-x-5 gap-y-1 text-gray-600">
                    <span>Hari Kerja: <b class="text-gray-900">{{ angka(total.hari_kerja) }}</b></span>
                    <span>Total Call: <b class="text-gray-900">{{ angka(total.call) }}</b></span>
                    <span>Total EC: <b class="text-green-600">{{ angka(total.ec) }}</b></span>
                    <span>Total Sales: <b class="text-green-600">{{ rupiah(total.sales) }}</b></span>
                    <span>Total Point: <b class="text-orange-500">{{ angka(total.point) }}</b></span>
                </div>
                <div class="flex flex-wrap gap-x-5 gap-y-1 mt-1 text-gray-600">
                    <span>AVG Call/Day: <b class="text-sky-500">{{ angkaRingkas(total.avg_call, 1) }}</b></span>
                    <span>AVG EC/Day: <b class="text-green-600">{{ angkaRingkas(total.avg_ec, 1) }}</b></span>
                    <span>AVG Sales/Day: <b class="text-green-600">{{ rupiah(total.avg_sales) }}</b></span>
                    <span>AVG IPT: <b class="text-gray-900">{{ angkaRingkas(total.avg_ipt, 1) }}</b></span>
                    <span>AVG Point/Day: <b class="text-orange-500">{{ angkaRingkas(total.avg_point, 1) }}</b></span>
                </div>
            </div>

            <section v-for="t in tim" :key="t.koordinator_id ?? 0" class="mb-4">
                <div class="flex items-center gap-2 border-l-2 border-blue-600 bg-blue-50/40 px-2 py-1.5 text-sm">
                    <span class="font-semibold">{{ t.nama }}</span>
                    <span class="text-xs text-gray-500">AVG Visit / Day: <b class="text-gray-700">{{ angkaRingkas(t.avg_visit, 1) }}</b></span>
                </div>
                <div class="overflow-x-auto">
                    <table class="data-table text-xs">
                        <thead>
                            <tr>
                                <th scope="col" rowspan="2">No</th>
                                <th scope="col" rowspan="2">Nama Sales</th>
                                <th scope="col" rowspan="2" class="text-center">Status</th>
                                <th scope="colgroup" colspan="6" class="text-center">Total</th>
                                <th scope="colgroup" colspan="5" class="text-center">Rata-rata / Hari Kerja</th>
                            </tr>
                            <tr>
                                <th scope="col" class="text-center">Hari Kerja</th>
                                <th scope="col" class="text-center">Call</th>
                                <th scope="col" class="text-center">EC</th>
                                <th scope="col" class="text-end">Sales</th>
                                <th scope="col" class="text-end">SKU</th>
                                <th scope="col" class="text-center">Point</th>
                                <th scope="col" class="text-end">AVG Call</th>
                                <th scope="col" class="text-end">AVG EC</th>
                                <th scope="col" class="text-end">AVG Sales</th>
                                <th scope="col" class="text-center">AVG IPT</th>
                                <th scope="col" class="text-end">AVG Point</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(s, i) in t.sales" :key="s.id" :class="{ 'text-gray-400': !s.aktif }">
                                <td>{{ i + 1 }}</td>
                                <td>{{ s.nama }}</td>
                                <td class="text-center"><Badge :variant="s.aktif ? 'success' : 'secondary'">{{ s.aktif ? 'Aktif' : 'Non-aktif' }}</Badge></td>
                                <td class="text-center"><Badge>{{ s.hari_kerja }}</Badge></td>
                                <td class="text-center"><Badge variant="primary">{{ angka(s.call) }}</Badge></td>
                                <td class="text-center"><Badge variant="success">{{ angka(s.ec) }}</Badge></td>
                                <td class="text-end whitespace-nowrap">{{ rupiah(s.sales) }}</td>
                                <td class="text-end">{{ angka(s.sku) }}</td>
                                <td class="text-center"><Badge variant="warning">{{ angka(s.point) }}</Badge></td>
                                <td class="text-end">{{ angkaRingkas(s.avg_call, 1) }}</td>
                                <td class="text-end">{{ angkaRingkas(s.avg_ec, 1) }}</td>
                                <td class="text-end whitespace-nowrap">{{ rupiah(s.avg_sales) }}</td>
                                <td class="text-center"><Badge variant="warning">{{ angkaRingkas(s.avg_ipt, 1) }}</Badge></td>
                                <td class="text-end">{{ angkaRingkas(s.avg_point, 1) }}</td>
                            </tr>
                            <tr v-if="!t.sales.length">
                                <td colspan="14" class="text-center text-gray-500">Tidak ada sales di tim ini.</td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="font-semibold bg-blue-50">
                                <th scope="row" colspan="3" class="text-center">Akumulasi</th>
                                <td class="text-center"><Badge>{{ angka(t.akumulasi.hari_kerja) }}</Badge></td>
                                <td class="text-center"><Badge variant="primary">{{ angka(t.akumulasi.call) }}</Badge></td>
                                <td class="text-center"><Badge variant="success">{{ angka(t.akumulasi.ec) }}</Badge></td>
                                <td class="text-end whitespace-nowrap">{{ rupiah(t.akumulasi.sales) }}</td>
                                <td class="text-end">{{ angka(t.akumulasi.sku) }}</td>
                                <td class="text-center"><Badge variant="warning">{{ angka(t.akumulasi.point) }}</Badge></td>
                                <td class="text-end">{{ angkaRingkas(t.akumulasi.avg_call, 1) }}</td>
                                <td class="text-end">{{ angkaRingkas(t.akumulasi.avg_ec, 1) }}</td>
                                <td class="text-end whitespace-nowrap">{{ rupiah(t.akumulasi.avg_sales) }}</td>
                                <td class="text-center"><Badge variant="warning">{{ angkaRingkas(t.akumulasi.avg_ipt, 1) }}</Badge></td>
                                <td class="text-end">{{ angkaRingkas(t.akumulasi.avg_point, 1) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </section>
            <p v-if="!tim.length" class="text-gray-500">Belum ada sales motoris di depo ini.</p>
            </Deferred>
        </Card>
    </AppLayout>
</template>
