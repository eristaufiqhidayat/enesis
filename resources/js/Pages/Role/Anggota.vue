<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Badge from '@/Components/ui/atoms/Badge.vue';
import Button from '@/Components/ui/atoms/Button.vue';
import Checkbox from '@/Components/ui/atoms/Checkbox.vue';
import FieldError from '@/Components/ui/atoms/FieldError.vue';
import Input from '@/Components/ui/atoms/Input.vue';
import Label from '@/Components/ui/atoms/Label.vue';
import Select from '@/Components/ui/atoms/Select.vue';
import Card from '@/Components/ui/molecules/Card.vue';
import PageHeader from '@/Components/ui/molecules/PageHeader.vue';
import Pagination from '@/Components/ui/molecules/Pagination.vue';
import { angka } from '@/Support/format.js';

const props = defineProps({
    role: { type: Object, required: true },
    cakupan: { type: Object, required: true },
    anggota: { type: Object, required: true },
    q: { type: String, default: '' },
    roleLain: { type: Object, default: () => ({}) },
    riwayat: { type: Array, default: () => [] },
    bisaUbah: { type: Boolean, default: false },
});

const page = usePage();
const appName = page.props.appName;

const cakupanLabel = computed(() => props.cakupan[props.role.cakupan]?.[0] ?? props.role.cakupan);
const subtitle = computed(() => `${angka(props.anggota.total)} akun · cakupan data: ${cakupanLabel.value}`);
const bisaPindah = computed(() => props.bisaUbah && Object.keys(props.roleLain).length > 0);

const q = ref(props.q);
function cari() {
    router.get(`/role/${props.role.id}/anggota`, { q: q.value }, { preserveState: true, replace: true });
}

const pindah = useForm({ user_id: [], ke: Object.keys(props.roleLain)[0] ?? '' });
function pindahkan() {
    pindah.post(`/role/${props.role.id}/pindah`, { preserveScroll: true, onSuccess: () => pindah.reset('user_id') });
}
</script>

<template>
    <Head :title="`Anggota role ${role.nama} · ${appName}`" />
    <AppLayout breadcrumb-slug="role">
        <PageHeader :title="`Anggota role ${role.nama}`" :subtitle="subtitle">
            <template #actions>
                <Button :tag="Link" variant="outline-secondary" :href="`/role/${role.id}/edit`">Atur hak akses</Button>
            </template>
        </PageHeader>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-3">
            <form class="xl:col-span-2" @submit.prevent="pindahkan">
                <Card body-class="overflow-x-auto">
                    <template #header>
                        <div class="flex flex-wrap items-end justify-between gap-2">
                            <div class="w-64">
                                <Label for="q" class="sr-only">Cari anggota</Label>
                                <Input id="q" v-model="q" type="search" placeholder="Cari username / karyawan…" @keydown.enter.prevent="cari" />
                            </div>
                            <div v-if="bisaPindah" class="flex flex-wrap items-end gap-2">
                                <div>
                                    <Label for="ke">Pindahkan yang dipilih ke</Label>
                                    <Select id="ke" v-model="pindah.ke">
                                        <option v-for="(nama, id) in roleLain" :key="id" :value="id">{{ nama }}</option>
                                    </Select>
                                </div>
                                <Button type="submit" :disabled="pindah.processing">Pindahkan</Button>
                            </div>
                        </div>
                        <FieldError v-if="pindah.errors.user_id">{{ pindah.errors.user_id }}</FieldError>
                        <FieldError v-if="pindah.errors.ke">{{ pindah.errors.ke }}</FieldError>
                    </template>

                    <table class="data-table">
                        <thead>
                            <tr>
                                <th v-if="bisaUbah" scope="col" class="w-8"><span class="sr-only">Pilih</span></th>
                                <th scope="col">Username</th>
                                <th scope="col">Karyawan</th>
                                <th scope="col">Depo</th>
                                <th scope="col">Status</th>
                                <th scope="col">Login terakhir</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="a in anggota.data" :key="a.id">
                                <td v-if="bisaUbah"><Checkbox v-model="pindah.user_id" :value="a.id" :aria-label="`Pilih ${a.username}`" /></td>
                                <td>
                                    <a v-if="a.url" :href="a.url" class="font-semibold">{{ a.username }}</a>
                                    <span v-else class="font-semibold">{{ a.username }}</span>
                                </td>
                                <td>{{ a.karyawan ?? '-' }}</td>
                                <td>{{ a.depo ?? '-' }}</td>
                                <td><Badge :variant="a.aktif ? 'success' : 'secondary'">{{ a.aktif ? 'Aktif' : 'Tidak aktif' }}</Badge></td>
                                <td>{{ a.login_terakhir }}</td>
                            </tr>
                            <tr v-if="!anggota.data.length">
                                <td :colspan="bisaUbah ? 6 : 5" class="text-center text-gray-500 py-4">Belum ada akun dengan role ini.</td>
                            </tr>
                        </tbody>
                    </table>

                    <template v-if="anggota.last_page > 1" #footer>
                        <div class="flex justify-end"><Pagination :links="anggota.links" /></div>
                    </template>
                </Card>
            </form>

            <Card>
                <template #header><h6 class="mb-0 font-semibold">Riwayat perubahan role</h6></template>
                <ol class="list-none m-0 p-0 flex flex-col gap-3">
                    <li v-for="(r, i) in riwayat" :key="i" class="flex gap-2">
                        <span aria-hidden="true" class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-blue-600"></span>
                        <span>
                            <span class="block">{{ r.keterangan }}</span>
                            <span class="text-xs text-gray-500">{{ r.aktor }} · {{ r.waktu }}</span>
                        </span>
                    </li>
                    <li v-if="!riwayat.length" class="text-gray-500">Belum ada perubahan.</li>
                </ol>
            </Card>
        </div>
    </AppLayout>
</template>
