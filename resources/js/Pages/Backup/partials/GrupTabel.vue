<script setup>
import { computed } from 'vue';
import Badge from '@/Components/ui/atoms/Badge.vue';
import Checkbox from '@/Components/ui/atoms/Checkbox.vue';
import Icon from '@/Components/ui/atoms/Icon.vue';

// Satu grup tabel (Transaksi motoris, Master, …): centang "semua grup", buka-tutup, daftar tabel dengan perkiraan baris.
const props = defineProps({
    grup: { type: Object, required: true },
    // Tabel yang tampil (sudah disaring pencarian); grup.tabel = semua tabel grup.
    tabel: { type: Array, required: true },
    terbuka: { type: Boolean, default: false },
});

const pilih = defineModel('pilih', { type: Array, required: true });
const emit = defineEmits(['toggle']);

const nama = computed(() => props.grup.tabel.map((t) => t.nama));
const jumlahPilih = computed(() => nama.value.filter((t) => pilih.value.includes(t)).length);
const semua = computed(() => jumlahPilih.value === nama.value.length);
const angka = (n) => n.toLocaleString('id-ID');

function pilihSemua(nilai) {
    pilih.value = nilai
        ? [...new Set([...pilih.value, ...nama.value])]
        : pilih.value.filter((t) => !nama.value.includes(t));
}
</script>

<template>
    <div class="grup-tabel">
        <div class="grup-tabel-kepala">
            <Checkbox :model-value="semua" :indeterminate="jumlahPilih > 0 && !semua" :aria-label="`Pilih semua tabel ${grup.nama}`" @update:model-value="pilihSemua" />
            <button type="button" class="grup-tabel-tombol" :aria-expanded="terbuka ? 'true' : 'false'" @click="emit('toggle')">
                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-semibold text-gray-900">{{ grup.nama }}</span>
                    <span class="block text-xs text-gray-500">{{ grup.ket }}</span>
                </span>
                <span class="shrink-0 text-xs font-medium text-gray-500">{{ jumlahPilih }} / {{ nama.length }}</span>
                <Icon name="chevron-right" class="chevron-putar" :class="{ terbuka }" />
            </button>
        </div>
        <div v-if="terbuka" class="grup-tabel-isi">
            <label v-for="t in tabel" :key="t.nama" class="tabel-pilihan">
                <Checkbox v-model="pilih" :value="t.nama" />
                <span class="nama-tabel" :title="t.nama">{{ t.nama }}</span>
                <Badge v-if="t.pribadi" variant="warning" title="Berisi data pribadi">Pribadi</Badge>
                <span v-if="t.baris !== null" class="shrink-0 text-xs text-gray-500">± {{ angka(t.baris) }}</span>
            </label>
        </div>
    </div>
</template>
