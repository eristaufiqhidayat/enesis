<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import Icon from '@/Components/ui/atoms/Icon.vue';

// Tombol ikon info yang membuka popover penjelasan saat diklik (bukan hover, lihat Tooltip.vue untuk itu).
// Posisi panel (kiri/kanan, atas/bawah) dihitung saat dibuka berdasar sisa ruang di layar, supaya tidak terpotong.
const props = defineProps({
    title: { type: String, default: null },
    align: { type: String, default: 'end' },
});

const terbuka = ref(false);
const wrapper = ref(null);
const sisi = ref(props.align);
const arah = ref('bawah');

function hitungPosisi() {
    const rect = wrapper.value?.getBoundingClientRect();
    if (!rect) return;

    const lebarPanel = Math.min(320, window.innerWidth - 16);
    const cukupKanan = rect.right - lebarPanel >= 8;
    const cukupKiri = rect.left + lebarPanel <= window.innerWidth - 8;
    sisi.value = props.align === 'end'
        ? (cukupKanan ? 'end' : (cukupKiri ? 'start' : 'end'))
        : (cukupKiri ? 'start' : (cukupKanan ? 'end' : 'start'));

    arah.value = window.innerHeight - rect.bottom < 180 && rect.top > 180 ? 'atas' : 'bawah';
}

function toggle() {
    if (!terbuka.value) hitungPosisi();
    terbuka.value = !terbuka.value;
}

function tutupDiLuar(e) {
    if (wrapper.value && !wrapper.value.contains(e.target)) terbuka.value = false;
}

onMounted(() => document.addEventListener('click', tutupDiLuar));
onBeforeUnmount(() => document.removeEventListener('click', tutupDiLuar));
</script>

<template>
    <span class="relative inline-flex" ref="wrapper">
        <button type="button" class="info-popover-btn" aria-label="Informasi" @click="toggle">
            <Icon name="info" />
        </button>
        <div
            class="info-popover-panel"
            :class="[sisi === 'end' ? 'right-0' : 'left-0', arah === 'atas' ? 'bottom-full mb-2' : 'top-full mt-2', { hidden: !terbuka }]"
        >
            <h6 v-if="title" class="info-popover-title">{{ title }}</h6>
            <slot />
        </div>
    </span>
</template>
