<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Badge from '@/Components/ui/atoms/Badge.vue';
import Button from '@/Components/ui/atoms/Button.vue';
import Icon from '@/Components/ui/atoms/Icon.vue';
import Card from '@/Components/ui/molecules/Card.vue';
import PageHeader from '@/Components/ui/molecules/PageHeader.vue';
import { angka } from '@/Support/format.js';

const props = defineProps({
    roles: { type: Array, required: true },
    cakupan: { type: Object, required: true },
    bisaTambah: { type: Boolean, default: false },
});

const appName = usePage().props.appName;
const jumlahMenu = (r) => (r.jumlah_menu === null ? 'Penuh' : `${angka(r.jumlah_menu)} menu`);
</script>

<template>
    <Head :title="`Role & Hak Akses · ${appName}`" />
    <AppLayout breadcrumb-slug="role">
        <PageHeader
            title="Role & Hak Akses"
            subtitle="Role menentukan menu yang bisa dibuka, aksi yang boleh dilakukan, cakupan data, dan tempat login. Setiap akun punya satu role."
        >
            <template v-if="bisaTambah" #actions>
                <Button :tag="Link" href="/role/tambah"><Icon name="plus" class="me-1" /> Tambah role</Button>
            </template>
        </PageHeader>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3 mb-3">
            <Card v-for="r in roles" :key="r.id">
                <div class="flex items-start justify-between gap-2 mb-2">
                    <div>
                        <h6 class="mb-0 font-semibold text-base">{{ r.nama }}</h6>
                        <div class="text-gray-500">{{ r.keterangan }}</div>
                    </div>
                    <Badge :variant="r.bawaan ? 'secondary' : 'primary'">{{ r.bawaan ? 'BAWAAN' : 'BUATAN' }}</Badge>
                </div>
                <dl class="grid grid-cols-3 gap-2 mb-2">
                    <div><dt class="text-xs text-gray-500">Cakupan data</dt><dd class="font-semibold mb-0">{{ cakupan[r.cakupan]?.[0] ?? r.cakupan }}</dd></div>
                    <div><dt class="text-xs text-gray-500">Hak akses</dt><dd class="font-semibold mb-0">{{ jumlahMenu(r) }}</dd></div>
                    <div><dt class="text-xs text-gray-500">Akun</dt><dd class="font-semibold mb-0">{{ angka(r.jumlah_akun) }}</dd></div>
                </dl>
                <div class="flex flex-wrap gap-1 mb-3">
                    <Badge v-if="r.login_web" variant="light">Web</Badge>
                    <Badge v-if="r.login_mobile" variant="light">Aplikasi HP</Badge>
                </div>
                <div class="flex gap-2 border-t border-gray-100 pt-2">
                    <Button :tag="Link" variant="outline-secondary" :href="`/role/${r.id}/edit`">Atur hak akses</Button>
                    <Button :tag="Link" variant="light" :href="`/role/${r.id}/anggota`">Lihat anggota</Button>
                </div>
            </Card>
        </div>

        <Card>
            <div class="flex flex-wrap gap-x-5 gap-y-1">
                <strong>Cakupan data:</strong>
                <span v-for="[kode, [label, desc]] in Object.entries(cakupan)" :key="kode"><strong>{{ label }}</strong> — {{ desc }}</span>
            </div>
        </Card>
    </AppLayout>
</template>
