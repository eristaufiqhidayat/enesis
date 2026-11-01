<script setup>
import { durasiSingkat, rupiah } from '@/Support/format.js';

// Strip KPI hari itu di modal Riwayat Lacak Motoris (kinerja sekilas): kunjungan, beli, tidak beli, penjualan, jam kerja.
// `ringkasan` dari ringkasanKunjungan() di Support/pusatKendali.js.
defineProps({
    ringkasan: { type: Object, required: true },
});
</script>

<template>
    <dl class="kpi-hari" aria-label="Ringkasan hari ini">
        <div class="kpi-item">
            <dt class="kpi-label">Kunjungan</dt>
            <dd class="kpi-nilai">{{ ringkasan.total }}</dd>
        </div>
        <div class="kpi-item">
            <dt class="kpi-label">Beli</dt>
            <dd class="kpi-nilai kpi-nilai-baik">{{ ringkasan.beli }} <span class="kpi-catatan">{{ ringkasan.persenBeli }}%</span></dd>
        </div>
        <div class="kpi-item">
            <dt class="kpi-label">Tidak beli</dt>
            <dd class="kpi-nilai kpi-nilai-waspada">{{ ringkasan.tidak }}</dd>
        </div>
        <div class="kpi-item">
            <dt class="kpi-label">Penjualan</dt>
            <dd class="kpi-nilai">{{ rupiah(ringkasan.penjualan) }}</dd>
        </div>
        <div class="kpi-item">
            <dt class="kpi-label">Jam kerja</dt>
            <dd class="kpi-nilai">
                <template v-if="ringkasan.mulai">{{ ringkasan.mulai }}–{{ ringkasan.selesai }}</template>
                <span v-if="ringkasan.durasiMenit !== null" class="kpi-catatan">{{ durasiSingkat(ringkasan.durasiMenit) }}</span>
            </dd>
        </div>
    </dl>
</template>
