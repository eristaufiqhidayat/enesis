<script setup>
import { nextTick, ref } from 'vue';
import Button from '@/Components/ui/atoms/Button.vue';
import FieldError from '@/Components/ui/atoms/FieldError.vue';
import Input from '@/Components/ui/atoms/Input.vue';
import Label from '@/Components/ui/atoms/Label.vue';
import Combobox from '@/Components/ui/molecules/Combobox.vue';

// Satu baris isian "produk + qty + Tambah" untuk daftar item muatan (wizard tambah muatan dan mode ubah di drawer).
// options: [{ value, label }] produk yang masih boleh dipilih (pemanggil membuang yang sudah ada di daftar).
// Emit 'tambah' dengan { produk_id, qty_bawa } setelah lolos cek dasar, lalu isian dikosongkan dan fokus kembali ke
// pencarian produk supaya produk berikutnya bisa langsung dicari. Enter di kolom qty sama dengan klik Tambah.
defineProps({
    options: { type: Array, default: () => [] },
    disabled: { type: Boolean, default: false },
    placeholder: { type: String, default: 'Cari produk...' },
    idProduk: { type: String, default: 'produk-baru' },
    idQty: { type: String, default: 'qty-baru' },
});

const emit = defineEmits(['tambah']);

const produk = ref('');
const qty = ref('');
const galat = ref('');
const pencarian = ref(null);

function tambah() {
    galat.value = '';

    if (!produk.value) {
        galat.value = 'Pilih produk dulu.';

        return;
    }

    if (!(Number(qty.value) > 0)) {
        galat.value = 'Qty bawa harus lebih dari 0.';

        return;
    }

    emit('tambah', { produk_id: produk.value, qty_bawa: qty.value });
    produk.value = '';
    qty.value = '';
    nextTick(() => pencarian.value?.fokus());
}

defineExpose({ fokus: () => pencarian.value?.fokus() });
</script>

<template>
    <div>
        <div class="flex flex-col sm:flex-row sm:items-end gap-2">
            <div class="flex-1 min-w-0">
                <Label :for="idProduk">Produk</Label>
                <Combobox
                    :id="idProduk"
                    ref="pencarian"
                    v-model="produk"
                    :options="options"
                    :disabled="disabled"
                    :placeholder="placeholder"
                    :clearable="false"
                />
            </div>
            <div class="sm:w-40">
                <Label :for="idQty">Qty bawa</Label>
                <Input :id="idQty" v-model="qty" type="number" step="any" min="0" :disabled="disabled" @keydown.enter.prevent="tambah" />
            </div>
            <div>
                <Button variant="light" :disabled="disabled" @click="tambah">+ Tambah</Button>
            </div>
        </div>
        <FieldError v-if="galat">{{ galat }}</FieldError>
    </div>
</template>
