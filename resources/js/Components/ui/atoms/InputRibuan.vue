<script setup>
import { nextTick, ref, watch } from 'vue';

// Input angka bulat dengan pemisah ribuan titik (format Indonesia: 5.750). Nilai yang di-emit hanya digit
// (mis. "5750") supaya langsung cocok dengan validasi backend. Atribut lain (id, required, dll.) diteruskan ke <input>.
const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
});

const emit = defineEmits(['update:modelValue']);

const tampil = ref('');

function formatRibuan(digit) {
    return digit === '' ? '' : new Intl.NumberFormat('id-ID').format(Number(digit));
}

watch(
    () => props.modelValue,
    (nilai) => {
        const digit = String(nilai ?? '').replace(/\D/g, '');

        if (digit !== tampil.value.replace(/\D/g, '')) {
            tampil.value = formatRibuan(digit);
        }
    },
    { immediate: true },
);

// Posisi kursor dihitung dari jumlah digit di kiri kursor, supaya tetap di tempat yang sama setelah titik disisipkan.
async function ubah(event) {
    const el = event.target;
    const digitSebelumKursor = el.value.slice(0, el.selectionStart).replace(/\D/g, '').length;
    const digit = el.value.replace(/\D/g, '');

    tampil.value = formatRibuan(digit);
    emit('update:modelValue', digit);

    await nextTick();

    let posisi = 0;
    let terhitung = 0;

    while (posisi < tampil.value.length && terhitung < digitSebelumKursor) {
        if (/\d/.test(tampil.value[posisi])) {
            terhitung++;
        }
        posisi++;
    }

    el.setSelectionRange(posisi, posisi);
}
</script>

<template>
    <input
        type="text"
        inputmode="numeric"
        autocomplete="off"
        :value="tampil"
        class="form-control form-control-sm"
        @input="ubah"
    >
</template>
