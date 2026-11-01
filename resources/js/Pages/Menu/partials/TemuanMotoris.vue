<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import Icon from '@/Components/ui/atoms/Icon.vue';
import { tingkatTertinggi } from '@/Support/pusatKendali.js';

// Tombol "Temuan" di modal Riwayat Lacak Motoris: satu tempat untuk semua indikasi (lokasi palsu, lompatan GPS, jarak
// dekat, di luar rute, ...). Panel daftarnya melayang (absolute) supaya tidak mendorong daftar kunjungan dan peta.
// Klik satu temuan mengirim 'fokus' agar peta memperbesar titiknya dan daftar menyorot kunjungan terkait.
const props = defineProps({
    // Dari susunTemuan() di Support/pusatKendali.js.
    temuan: { type: Array, required: true },
    // false selama data tanggal itu belum selesai dimuat (tombol disembunyikan, bukan "Tidak ada temuan").
    siap: { type: Boolean, default: false },
});

const emit = defineEmits(['fokus']);

const buka = ref(false);
const wadah = ref(null);
const tingkat = computed(() => tingkatTertinggi(props.temuan));
const LABEL = { bahaya: 'Bahaya', waspada: 'Waspada', info: 'Info' };

function tutupDiLuar(e) {
    if (wadah.value && !wadah.value.contains(e.target)) buka.value = false;
}

function pilih(t) {
    emit('fokus', t);
}

// Fase capture: klik di peta atau elemen yang menghentikan propagasi tetap menutup panel.
onMounted(() => document.addEventListener('click', tutupDiLuar, true));
onBeforeUnmount(() => document.removeEventListener('click', tutupDiLuar, true));
// Tanggal berganti (temuan baru): panel lama ditutup.
watch(() => props.temuan, () => { buka.value = false; });
</script>

<template>
    <div v-if="siap" ref="wadah" class="temuan-motoris">
        <button
            type="button"
            class="temuan-tombol"
            :class="temuan.length ? `temuan-tombol-${tingkat}` : 'temuan-tombol-bersih'"
            :aria-expanded="buka"
            :disabled="!temuan.length"
            @click="buka = !buka"
        >
            <Icon :name="temuan.length ? 'flag' : 'badge-check'" class="temuan-ikon" />
            <template v-if="temuan.length">{{ temuan.length }} temuan <Icon name="chevron-down" class="temuan-ikon" :class="{ 'rotate-180': buka }" /></template>
            <template v-else>Tidak ada temuan</template>
        </button>
        <Transition name="flyover">
            <div v-if="buka" class="temuan-panel" role="region" aria-label="Daftar temuan">
                <ul class="m-0 space-y-1 p-0">
                    <li v-for="t in temuan" :key="t.kunci">
                        <button type="button" class="temuan-item" :class="`temuan-item-${t.tingkat}`" @click="pilih(t)">
                            <span class="temuan-tingkat" :class="`temuan-tingkat-${t.tingkat}`">{{ LABEL[t.tingkat] }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-semibold">{{ t.judul }}</span>
                                <span class="block text-xs text-gray-600">{{ t.rincian }}</span>
                                <span v-if="t.titik.length || t.kunjunganId.length" class="mt-0.5 block text-xs text-blue-600">Lihat di peta dan daftar</span>
                            </span>
                        </button>
                    </li>
                </ul>
                <p class="mb-0 mt-2 text-xs text-gray-500">Ini indikasi dari pola data, bukan bukti kecurangan.</p>
            </div>
        </Transition>
    </div>
</template>
