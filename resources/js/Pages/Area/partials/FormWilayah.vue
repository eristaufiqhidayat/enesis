<script setup>
// Combobox Depo, Provinsi, Kota/Kabupaten, Kecamatan. State-nya dari useFormWilayah (formWilayah.js);
// field form diubah langsung lewat v-model karena form adalah objek reactive milik halaman.
import Combobox from '@/Components/ui/molecules/Combobox.vue';
import Label from '@/Components/ui/atoms/Label.vue';

defineProps({
    form: { type: Object, required: true },
    opsi: { type: Object, required: true },
    opsiDepo: { type: Array, required: true },
    opsiProvinsi: { type: Array, required: true },
    idPrefix: { type: String, default: 'wil' },
});
</script>

<template>
    <div>
        <Label :for="`${idPrefix}_depo`" class="mb-1">Depo <span class="text-red-600">*</span></Label>
        <Combobox :id="`${idPrefix}_depo`" v-model="form.warehouse_id" :options="opsiDepo" placeholder="Pilih depo..." />
    </div>
    <slot name="setelah-depo" />
    <div>
        <Label :for="`${idPrefix}_provinsi`" class="mb-1">Provinsi <span class="text-red-600">*</span></Label>
        <Combobox :id="`${idPrefix}_provinsi`" v-model="form.provinsi_id" :options="opsiProvinsi" placeholder="Pilih provinsi..." />
    </div>
    <div>
        <Label :for="`${idPrefix}_kota`" class="mb-1">Kota / Kabupaten <span class="text-red-600">*</span></Label>
        <Combobox :id="`${idPrefix}_kota`" v-model="form.kota_id" :options="opsi.kota" :disabled="!form.provinsi_id" placeholder="Pilih kota / kabupaten..." />
    </div>
    <div>
        <Label :for="`${idPrefix}_kecamatan`" class="mb-1">Kecamatan</Label>
        <Combobox :id="`${idPrefix}_kecamatan`" v-model="form.kecamatan_id" :options="opsi.kecamatan" :disabled="!form.kota_id" placeholder="Semua kecamatan" />
    </div>
</template>
