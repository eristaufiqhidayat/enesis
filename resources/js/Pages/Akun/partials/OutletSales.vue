<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import Badge from '@/Components/ui/atoms/Badge.vue';
import Button from '@/Components/ui/atoms/Button.vue';
import Input from '@/Components/ui/atoms/Input.vue';
import Label from '@/Components/ui/atoms/Label.vue';
import Card from '@/Components/ui/molecules/Card.vue';
import Pagination from '@/Components/ui/molecules/Pagination.vue';
import { angka } from '@/Support/format.js';

// Area rute dan outlet seorang sales (aturan sama dengan rute di aplikasi HP).
const props = defineProps({
    outletSales: { type: Object, required: true },
});

const cariOutlet = ref(props.outletSales.q);
const jumlahRute = computed(() => props.outletSales.area.filter((a) => a.rute).length);

function cari() {
    router.get(`${window.location.pathname}#outlet-sales`, cariOutlet.value.trim() ? { cari_outlet: cariOutlet.value.trim() } : {}, { preserveScroll: true });
}

const lokasi = (o) => [o.kelurahan, o.kecamatan].filter(Boolean).join(', ');
</script>

<template>
    <Card id="outlet-sales" class="mb-3" body-class="overflow-x-auto">
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h6 class="mb-0 font-semibold">
                    Outlet di bawah sales ini <span class="text-gray-500 font-normal">· {{ jumlahRute }} area aktif · {{ angka(outletSales.outlet.total) }} outlet{{ outletSales.q !== '' ? ' cocok' : '' }}</span>
                </h6>
                <form class="flex gap-2" role="search" @submit.prevent="cari">
                    <Label for="cari_outlet" class="sr-only">Cari outlet</Label>
                    <Input id="cari_outlet" v-model="cariOutlet" type="search" placeholder="Cari nama, kode, alamat outlet…" />
                    <Button type="submit" variant="outline-secondary">Cari</Button>
                </form>
            </div>
        </template>

        <template v-if="outletSales.area.length">
            <p class="text-xs text-gray-500 mb-1">Area sales · <span class="text-blue-700 font-semibold">biru = area aktif</span> (outletnya tampil di tabel &amp; di rute HP) · abu-abu = area tidak aktif</p>
        </template>
        <div class="flex flex-wrap gap-2 mb-3" aria-label="Area sales">
            <Badge v-for="a in outletSales.area" :key="a.areaId" :variant="a.rute ? 'primary' : 'secondary'" :title="a.rute ? 'Area rute' : 'Area tidak aktif'">
                {{ a.nama ?? `Area #${a.areaId}` }} · {{ angka(a.jumlahOutlet) }} outlet{{ a.rute ? '' : ' · tidak aktif' }}
            </Badge>
            <span v-if="!outletSales.area.length" class="text-gray-500">Sales ini belum punya area, jadi belum punya outlet.</span>
        </div>

        <table v-if="outletSales.area.length" class="data-table">
            <thead><tr><th scope="col">Kode</th><th scope="col">Outlet</th><th scope="col">Area</th><th scope="col">Alamat</th><th scope="col">Verifikasi</th><th scope="col">Lokasi</th></tr></thead>
            <tbody>
                <tr v-for="o in outletSales.outlet.data" :key="o.kode ?? o.nama">
                    <td>{{ o.kode ?? '-' }}</td>
                    <td class="font-semibold">{{ o.nama ?? '-' }}</td>
                    <td>{{ o.area ?? '-' }}</td>
                    <td>
                        {{ o.alamat ?? '-' }}
                        <div v-if="lokasi(o)" class="text-gray-500">{{ lokasi(o) }}</div>
                    </td>
                    <td>{{ o.verifikasi ?? '-' }}</td>
                    <td>
                        <a v-if="o.latitude && o.longitude" :href="`https://www.google.com/maps?q=${o.latitude},${o.longitude}`" target="_blank" rel="noopener">Peta</a>
                        <span v-else class="text-gray-500">—</span>
                    </td>
                </tr>
                <tr v-if="!outletSales.outlet.data.length">
                    <td colspan="6" class="text-center text-gray-500 py-3">{{ outletSales.q !== '' ? 'Tidak ada outlet yang cocok.' : 'Belum ada outlet di area rute sales ini.' }}</td>
                </tr>
            </tbody>
        </table>

        <template #footer>
            <div class="flex flex-wrap justify-between items-center gap-2">
                <small class="text-gray-500">Area rute = area yang ditandai aktif pada data karyawan (atau semua area bila tidak ada yang aktif), sama seperti rute di aplikasi HP.</small>
                <Pagination :links="outletSales.outlet.links" />
            </div>
        </template>
    </Card>
</template>
