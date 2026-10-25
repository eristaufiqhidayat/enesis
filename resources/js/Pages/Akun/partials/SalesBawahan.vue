<script setup>
import { computed, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import Badge from '@/Components/ui/atoms/Badge.vue';
import Button from '@/Components/ui/atoms/Button.vue';
import Icon from '@/Components/ui/atoms/Icon.vue';
import Input from '@/Components/ui/atoms/Input.vue';
import Label from '@/Components/ui/atoms/Label.vue';
import Card from '@/Components/ui/molecules/Card.vue';
import Modal from '@/Components/ui/molecules/Modal.vue';

// Sales di bawah satu koordinator: daftar, tambah (cari sales lalu pilih), dan lepas.
const props = defineProps({
    akun: { type: Object, required: true },
    salesBawahan: { type: Array, required: true },
    bisaLihatSales: { type: Boolean, default: false },
    bisaAturSales: { type: Boolean, default: false },
});

const page = usePage();
const base = `/user_koordinator/${props.akun.id}/sales`;
const aktifCount = computed(() => props.salesBawahan.filter((s) => s.aktif).length);
const modalTambah = ref(false);
const cariSales = ref('');
const hasilSales = ref([]);
let timer;

function ubahCari(nilai) {
    cariSales.value = nilai;
    clearTimeout(timer);
    timer = setTimeout(async () => {
        const q = nilai.trim();
        if (q.length < 2) {
            hasilSales.value = [];
            return;
        }

        const res = await fetch(`${base}/cari?q=${encodeURIComponent(q)}`, { headers: { Accept: 'application/json' } });
        hasilSales.value = res.ok ? await res.json() : [];
    }, 250);
}

function infoSales(s) {
    return `${s.username} · ${s.karyawan ?? '-'} · Depo ${s.depo ?? '-'}` + (s.aktif ? '' : ' · tidak aktif');
}

function tambah(s) {
    router.post(base, { sales_id: s.id }, {
        preserveScroll: true,
        onSuccess: () => {
            modalTambah.value = false;
            cariSales.value = '';
            hasilSales.value = [];
        },
    });
}

function lepas(s) {
    router.delete(`${base}/${s.id}`, { preserveScroll: true });
}
</script>

<template>
    <Card class="mb-3" body-class="overflow-x-auto">
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h6 class="mb-0 font-semibold">
                    Sales di bawah koordinator ini <span class="text-gray-500 font-normal">· {{ aktifCount }} aktif dari {{ salesBawahan.length }} akun</span>
                </h6>
                <div class="flex flex-wrap gap-2">
                    <Button v-if="bisaLihatSales && salesBawahan.length" :tag="Link" variant="outline-secondary" :href="`/user_sales?koordinator=${akun.karyawanId}`">Buka di User Sales</Button>
                    <Button v-if="bisaAturSales" @click="modalTambah = true"><Icon name="plus" class="me-1" /> Tambah sales</Button>
                </div>
            </div>
        </template>

        <table class="data-table">
            <thead>
                <tr>
                    <th scope="col">Username</th><th scope="col">Karyawan</th><th scope="col">Depo</th><th scope="col">Status</th><th scope="col">HP aktif</th><th scope="col">Login terakhir</th>
                    <th v-if="bisaAturSales" scope="col" class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="s in salesBawahan" :key="s.id">
                    <td>
                        <Link v-if="bisaLihatSales" :href="`/user_sales/${s.id}`" class="font-semibold">{{ s.username }}</Link>
                        <template v-else>{{ s.username }}</template>
                    </td>
                    <td>{{ s.nama ?? '-' }}</td>
                    <td>{{ s.depo ?? '-' }}</td>
                    <td><Badge :variant="s.aktif ? 'success' : 'secondary'">{{ s.aktif ? 'Aktif' : 'Tidak aktif' }}</Badge></td>
                    <td>{{ s.hp ? `${s.hp}${s.hpVersi ? ` · v${s.hpVersi}` : ''}` : '—' }}</td>
                    <td>{{ s.loginTerakhir ?? '—' }}</td>
                    <td v-if="bisaAturSales" class="text-end">
                        <Button variant="light" :title="`Lepas ${s.username} dari koordinator ini`" @click="lepas(s)">Lepas</Button>
                    </td>
                </tr>
                <tr v-if="!salesBawahan.length">
                    <td :colspan="bisaAturSales ? 7 : 6" class="text-center text-gray-500 py-3">Belum ada akun sales di bawah koordinator ini.</td>
                </tr>
            </tbody>
        </table>
        <p v-if="salesBawahan.length >= 100" class="text-xs text-gray-500 mt-2 mb-0">Menampilkan 100 akun pertama; daftar lengkap ada di User Sales.</p>
        <p class="text-xs text-gray-500 mt-2 mb-0">Diatur dari Enesis dan tidak tertimpa saat data karyawan ditarik ulang dari aplikasi lama.</p>
    </Card>

    <Modal v-if="bisaAturSales" :show="modalTambah" :title="`Tambah sales ke ${akun.karyawan ?? akun.username}`" @close="modalTambah = false">
        <Label for="cari_sales">Cari akun sales</Label>
        <Input id="cari_sales" type="search" :model-value="cariSales" placeholder="Ketik username atau nama sales…" autocomplete="off" @update:model-value="ubahCari" />
        <div class="text-xs text-gray-500 mt-1">Sales yang sudah punya koordinator lain akan dipindah ke koordinator ini.</div>
        <p v-if="page.props.errors?.sales_id" class="text-red-600 text-xs mt-1 mb-0">{{ page.props.errors.sales_id }}</p>
        <div class="list-group mt-2" role="listbox" aria-label="Hasil cari sales">
            <div v-if="cariSales.trim().length >= 2 && !hasilSales.length" class="list-group-item text-gray-500">Tidak ada akun sales yang cocok.</div>
            <template v-for="s in hasilSales" :key="s.id">
                <div v-if="s.sudah" class="list-group-item text-gray-500">{{ infoSales(s) }} — sudah di bawah koordinator ini</div>
                <button
                    v-else
                    type="button"
                    class="list-group-item list-group-item-action text-start"
                    role="option"
                    @click="tambah(s)"
                >
                    {{ infoSales(s) }} — {{ s.koordinator ? `pindah dari ${s.koordinator}` : 'belum punya koordinator' }}
                </button>
            </template>
        </div>
        <template #footer>
            <Button variant="light" @click="modalTambah = false">Tutup</Button>
        </template>
    </Modal>
</template>
