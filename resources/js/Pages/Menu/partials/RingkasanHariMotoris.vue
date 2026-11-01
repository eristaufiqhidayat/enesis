<script setup>
import { computed } from 'vue';
import { desimal, durasiSingkat, rupiah } from '@/Support/format.js';
import { persenTarget } from '@/Support/pusatKendali.js';

// Strip KPI hari itu di modal Riwayat Lacak Motoris (kinerja sekilas): Call, Effective Call, dan IPT terhadap targetnya,
// tidak beli, penjualan, jam kerja. Effective Call sudah mewakili kunjungan yang beli, jadi tile Beli hanya tampil
// sebagai cadangan bila KPI dari server tidak ada. `ringkasan` dari ringkasanKunjungan() di Support/pusatKendali.js;
// `kpi` dari endpoint locations ({ call, ec, ipt, target: { call, ec, ipt } }), null bila tidak tersedia (tanpa target/EC).
const props = defineProps({
    ringkasan: { type: Object, required: true },
    kpi: { type: Object, default: null },
});

// Call, EC, dan IPT (rata-rata jenis SKU per transaksi) beserta realisasi terhadap targetnya; EC dan IPT hanya bila endpoint mengirim KPI.
const sasaran = computed(() => {
    const butir = [{ kunci: 'call', label: 'Call (Kunjungan)', nilai: props.kpi?.call ?? props.ringkasan.total, target: props.kpi?.target?.call ?? null }];
    if (props.kpi) {
        butir.push({ kunci: 'ec', label: 'Effective Call', nilai: props.kpi.ec, target: props.kpi.target?.ec ?? null });
        butir.push({ kunci: 'ipt', label: 'IPT (SKU/transaksi)', nilai: props.kpi.ipt, target: props.kpi.target?.ipt ?? null, desimal: true });
    }

    return butir.map((b) => ({ ...b, persen: persenTarget(b.nilai, b.target) }));
});
</script>

<template>
    <dl class="kpi-hari" aria-label="Ringkasan hari ini">
        <div v-for="b in sasaran" :key="b.kunci" class="kpi-item" :data-kpi="b.kunci">
            <dt class="kpi-label">{{ b.label }}</dt>
            <dd class="kpi-nilai">
                {{ b.desimal ? desimal(b.nilai) : b.nilai }}<span v-if="b.target" class="kpi-catatan">/ {{ b.desimal ? desimal(b.target) : b.target }}</span>
                <span v-if="b.persen !== null" class="kpi-persen" :class="{ 'kpi-persen-capai': b.persen >= 100 }">{{ b.persen }}%</span>
            </dd>
            <div v-if="b.persen !== null" class="kpi-bar" role="progressbar" :aria-valuenow="Math.min(b.persen, 100)" aria-valuemin="0" aria-valuemax="100" :aria-label="`Realisasi ${b.label} terhadap target`">
                <div class="kpi-bar-isi" :class="{ 'kpi-bar-isi-capai': b.persen >= 100 }" :style="{ width: `${Math.min(b.persen, 100)}%` }"></div>
            </div>
        </div>
        <div v-if="!kpi" class="kpi-item">
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
