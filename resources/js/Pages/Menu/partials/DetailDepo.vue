<script setup>
import { computed } from 'vue';
import Badge from '@/Components/ui/atoms/Badge.vue';
import Icon from '@/Components/ui/atoms/Icon.vue';
import DetailDepoPeta from './DetailDepoPeta.vue';

// Infografis satu depo di dalam drawer (lihat Menu/Show.vue). Semua bagian tampil berurutan dan digulir;
// data diambil dari baris tabel master_depo.
const props = defineProps({
    depo: { type: Object, required: true },
});

const formatRupiah = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 });

function angka(nilai) {
    return nilai === null || nilai === '' || nilai === undefined ? '-' : new Intl.NumberFormat('id-ID').format(Number(nilai));
}

function rupiah(nilai) {
    return nilai === null || nilai === '' || nilai === undefined ? '-' : formatRupiah.format(Number(nilai));
}

function teks(nilai) {
    return nilai === null || nilai === '' || nilai === undefined ? '-' : nilai;
}

// Nilai / kuota dalam persen, dibatasi 0–100. Kuota kosong atau nol → null (bar tidak ditampilkan).
function persen(nilai, kuota) {
    const k = Number(kuota);

    if (!k) {
        return null;
    }

    return Math.min(100, Math.round((Number(nilai) / k) * 100));
}

function progress(label, nilai, kuota) {
    return { label, nilai: angka(nilai), kuota: angka(kuota), persen: persen(nilai, kuota) };
}

const bagian = computed(() => {
    const d = props.depo;

    return [
        {
            judul: 'Ringkasan',
            ikon: 'store',
            stat: [
                { label: 'Sales aktif', nilai: angka(d.jumlah_sales_aktif) },
                { label: 'Koordinator', nilai: angka(d.total_koordinator) },
                { label: 'Wilayah depo', nilai: angka(d.jumlah_wilayah_depo) },
            ],
            baris: [
                { label: 'Kode', nilai: teks(d.kode) },
                { label: 'Projek', nilai: teks(d.projek) },
                { label: 'Kota besar', nilai: teks(d.kota_besar) },
                { label: 'Status', nilai: d.status, badge: true },
            ],
        },
        {
            judul: 'Lokasi',
            ikon: 'map-pin',
            baris: [
                { label: 'Provinsi', nilai: teks(d.provinsi) },
                { label: 'Kota/Kab', nilai: teks(d.kota_kab) },
                { label: 'Kecamatan', nilai: teks(d.kecamatan) },
                { label: 'Kelurahan/Desa', nilai: teks(d.kelurahan_desa) },
                { label: 'Alamat', nilai: teks(d.alamat) },
                { label: 'Multi polygon sales', nilai: d.multi_polygon_sales, badge: true, varian: d.multi_polygon_sales === 'Ya' ? 'success' : 'secondary' },
            ],
            peta: true,
        },
        {
            judul: 'Tim & Kuota',
            ikon: 'users',
            progress: [
                progress('Sales aktif terhadap kuota sales motoris', d.jumlah_sales_aktif, d.kuota_sales_motoris),
                progress('Koordinator terhadap kuota koordinator', d.total_koordinator, d.kuota_koordinator),
            ],
            baris: [
                { label: 'Durasi tracking GPS', nilai: d.durasi_tracking_gps, badge: true, varian: d.durasi_tracking_gps ? 'primary' : 'secondary' },
            ],
        },
        {
            judul: 'Target & Insentif',
            ikon: 'chart-pie',
            stat: [
                { label: 'Target call', nilai: angka(d.target_call) },
                { label: 'Target EC', nilai: angka(d.target_ec) },
                { label: 'Outlet 1 SKU', nilai: angka(d.outlet_1_sku) },
            ],
            baris: [
                { label: 'Minimum SKU', nilai: angka(d.minimum_sku) },
                { label: 'Insentif per poin', nilai: rupiah(d.insentif_per_poin) },
                { label: 'Minimal value transaksi', nilai: rupiah(d.minimal_value_transaksi) },
            ],
        },
    ];
});
</script>

<template>
    <div class="flex flex-col gap-6 pb-8">
        <section v-for="item in bagian" :key="item.judul" class="flex flex-col gap-3">
            <h3 class="detail-depo-judul">
                <Icon :name="item.ikon" class="detail-depo-ikon" />
                <span>{{ item.judul }}</span>
            </h3>

            <div v-if="item.stat" class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                <div v-for="stat in item.stat" :key="stat.label" class="detail-depo-stat">
                    <div class="detail-depo-stat-label">{{ stat.label }}</div>
                    <div class="detail-depo-stat-nilai">{{ stat.nilai }}</div>
                </div>
            </div>

            <div v-if="item.progress" class="flex flex-col gap-4">
                <div v-for="prog in item.progress" :key="prog.label">
                    <div class="flex justify-between gap-2 text-sm">
                        <span class="text-gray-600">{{ prog.label }}</span>
                        <span class="font-semibold whitespace-nowrap">{{ prog.nilai }} / {{ prog.kuota }}</span>
                    </div>
                    <div v-if="prog.persen !== null" class="detail-depo-batang" role="progressbar" :aria-valuenow="prog.persen" aria-valuemin="0" aria-valuemax="100">
                        <div class="detail-depo-batang-isi" :style="{ width: `${prog.persen}%` }"></div>
                    </div>
                    <div v-else class="text-xs text-gray-500">Kuota belum diisi.</div>
                </div>
            </div>

            <DetailDepoPeta v-if="item.peta" :depo="depo" />

            <dl v-if="item.baris" class="flex flex-col">
                <div v-for="baris in item.baris" :key="baris.label" class="detail-depo-baris">
                    <dt class="text-gray-500">{{ baris.label }}</dt>
                    <dd class="break-words">
                        <Badge v-if="baris.badge" :variant="baris.varian ?? (baris.nilai ? 'success' : 'secondary')">{{ baris.nilai || '-' }}</Badge>
                        <template v-else>{{ baris.nilai }}</template>
                    </dd>
                </div>
            </dl>
        </section>
    </div>
</template>
