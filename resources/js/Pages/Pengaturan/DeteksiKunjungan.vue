<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from '@/Components/ui/atoms/Button.vue';
import FieldError from '@/Components/ui/atoms/FieldError.vue';
import Input from '@/Components/ui/atoms/Input.vue';
import Label from '@/Components/ui/atoms/Label.vue';
import Card from '@/Components/ui/molecules/Card.vue';
import PageHeader from '@/Components/ui/molecules/PageHeader.vue';

// Ambang indikasi "kunjungan berturutan berjarak dekat" (spec 054), dipakai AnomaliJarakKunjungan di drawer Riwayat
// Lacak Motoris. Disimpan di database (bukan config) supaya bisa disesuaikan tanpa deploy ulang.
const props = defineProps({
    pengaturan: { type: Object, required: true },
    bisaUbah: { type: Boolean, default: false },
});

const appName = usePage().props.appName;

const form = useForm({
    jarak_dekat_meter: props.pengaturan.jarak_dekat_meter,
    min_kunjungan_berturutan: props.pengaturan.min_kunjungan_berturutan,
});

function simpan() {
    form.post('/deteksi_kunjungan');
}
</script>

<template>
    <Head :title="`Pengaturan Deteksi Kunjungan · ${appName}`" />
    <AppLayout breadcrumb-slug="deteksi_kunjungan">
        <PageHeader
            title="Pengaturan Deteksi Kunjungan"
            subtitle="Ambang indikasi kunjungan outlet berturutan yang berjarak terlalu dekat (mis. kunjungan ke beberapa outlet berbeda tanpa berpindah lokasi), ditampilkan sebagai peringatan di drawer Riwayat Lacak Motoris."
        />

        <Card class="max-w-md">
            <form class="space-y-3" @submit.prevent="simpan">
                <div>
                    <Label for="jarak_dekat_meter">Jarak dekat (meter)</Label>
                    <Input id="jarak_dekat_meter" v-model.number="form.jarak_dekat_meter" type="number" min="1" :disabled="!bisaUbah" />
                    <FieldError v-if="form.errors.jarak_dekat_meter">{{ form.errors.jarak_dekat_meter }}</FieldError>
                </div>
                <div>
                    <Label for="min_kunjungan_berturutan">Minimum kunjungan berturutan</Label>
                    <Input id="min_kunjungan_berturutan" v-model.number="form.min_kunjungan_berturutan" type="number" min="2" :disabled="!bisaUbah" />
                    <FieldError v-if="form.errors.min_kunjungan_berturutan">{{ form.errors.min_kunjungan_berturutan }}</FieldError>
                </div>
                <Button v-if="bisaUbah" type="submit" :loading="form.processing">Simpan</Button>
            </form>
        </Card>
    </AppLayout>
</template>
