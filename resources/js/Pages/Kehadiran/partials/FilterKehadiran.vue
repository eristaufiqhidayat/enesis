<script setup>
import { computed, reactive, watch } from 'vue';
import Button from '@/Components/ui/atoms/Button.vue';
import Input from '@/Components/ui/atoms/Input.vue';
import Label from '@/Components/ui/atoms/Label.vue';
import Select from '@/Components/ui/atoms/Select.vue';
import { useAmbilData } from '@/Support/ambilData.js';
import { bersihkanQuery } from '@/Support/query.js';

// Filter kehadiran (padanan partial Blade kehadiran/_filter). Submit = GET ke URL yang sama tanpa full reload,
// jadi query string di URL ikut berubah dan bisa di-bookmark.
const props = defineProps({
    action: { type: String, required: true },
    filter: { type: Object, required: true },
    // Hanya halaman yang punya tab status (absensi) yang mengisi ini; null = tidak ikut dikirim.
    status: { type: String, default: null },
    // Hanya halaman kunjungan (checkin outlet) yang mengisi ini; null = tidak ada kolom outlet.
    cariOutlet: { type: String, default: null },
    labelOrang: { type: String, default: 'Sales' },
    daftarDepo: { type: [Object, Array], default: () => ({}) },
    daftarKoordinator: { type: [Object, Array], default: () => ({}) },
    daftarSumber: { type: Object, required: true },
});

const form = reactive({});
const adaOutlet = computed(() => props.cariOutlet !== null);

function isiDariProps(filter) {
    Object.assign(form, {
        dari: filter.dari,
        sampai: filter.sampai,
        q: filter.q ?? '',
        depo: filter.depo ?? '',
        koordinator: filter.koordinator ?? '',
        sumber: filter.sumber,
        outlet: props.cariOutlet ?? '',
    });
}

isiDariProps(props.filter);
// Halaman dipakai ulang saat navigasi Inertia (mis. tombol tab status atau tombol kembali), jadi form ikut props terbaru.
watch(() => [props.filter, props.cariOutlet], () => isiDariProps(props.filter));

const adaKoordinator = computed(() => Object.keys(props.daftarKoordinator).length > 0);
const labelOrangKecil = computed(() => props.labelOrang.toLowerCase());

// Tombol Tampilkan aktif lagi hanya saat isian berbeda dari filter yang sedang tampil.
const { memuat, berubah, ambil } = useAmbilData({
    form: () => ({ ...form }),
    diterapkan: () => ({
        dari: props.filter.dari,
        sampai: props.filter.sampai,
        q: props.filter.q ?? '',
        depo: props.filter.depo ?? '',
        koordinator: props.filter.koordinator ?? '',
        sumber: props.filter.sumber,
        outlet: props.cariOutlet ?? '',
    }),
});

function submit() {
    ambil(props.action, bersihkanQuery({ ...form, status: props.status }), { preserveState: true, preserveScroll: true });
}
</script>

<template>
    <form class="flex flex-wrap items-end gap-2" role="search" @submit.prevent="submit">
        <div>
            <Label for="dari">Dari</Label>
            <Input id="dari" v-model="form.dari" type="date" />
        </div>
        <div>
            <Label for="sampai">Sampai</Label>
            <Input id="sampai" v-model="form.sampai" type="date" />
        </div>
        <div class="grow min-w-48">
            <Label for="q">Nama {{ labelOrangKecil }}</Label>
            <Input id="q" v-model="form.q" type="search" :placeholder="`Cari nama ${labelOrangKecil}…`" />
        </div>
        <div v-if="adaOutlet" class="grow min-w-48">
            <Label for="outlet">Outlet</Label>
            <Input id="outlet" v-model="form.outlet" type="search" placeholder="Nama atau kode outlet…" />
        </div>
        <div>
            <Label for="depo">Depo</Label>
            <Select id="depo" v-model="form.depo">
                <option value="">Semua depo</option>
                <option v-for="[id, nama] in Object.entries(daftarDepo)" :key="id" :value="id">{{ nama }}</option>
            </Select>
        </div>
        <div v-if="adaKoordinator">
            <Label for="koordinator">Koordinator</Label>
            <Select id="koordinator" v-model="form.koordinator">
                <option value="">Semua koordinator</option>
                <option v-for="[id, nama] in Object.entries(daftarKoordinator)" :key="id" :value="id">{{ nama }}</option>
            </Select>
        </div>
        <div>
            <Label for="sumber">Sumber</Label>
            <Select id="sumber" v-model="form.sumber">
                <option v-for="(label, kode) in daftarSumber" :key="kode" :value="kode">{{ label }}</option>
            </Select>
        </div>
        <Button type="submit" :loading="memuat" :disabled="!berubah">Tampilkan</Button>
    </form>
</template>
