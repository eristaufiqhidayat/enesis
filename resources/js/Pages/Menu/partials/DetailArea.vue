<script setup>
import { computed } from 'vue';
import Icon from '@/Components/ui/atoms/Icon.vue';

// Infografis satu area di dalam drawer (lihat Menu/Show.vue). Semua bagian tampil berurutan dan digulir;
// data diambil dari baris tabel master_area.
const props = defineProps({
    area: { type: Object, required: true },
});

function angka(nilai) {
    return nilai === null || nilai === '' || nilai === undefined ? '-' : new Intl.NumberFormat('id-ID').format(Number(nilai));
}

function teks(nilai) {
    return nilai === null || nilai === '' || nilai === undefined ? '-' : nilai;
}

const bagian = computed(() => {
    const a = props.area;

    return [
        {
            judul: 'Ringkasan',
            ikon: 'map',
            stat: [
                { label: 'Total toko', nilai: angka(a.total_toko) },
            ],
            baris: [
                { label: 'Kode', nilai: teks(a.kode) },
                { label: 'Nama', nilai: teks(a.nama) },
                { label: 'Nama sales', nilai: teks(a.nama_sales) },
            ],
        },
        {
            judul: 'Lokasi',
            ikon: 'map-pin',
            baris: [
                { label: 'Provinsi', nilai: teks(a.provinsi) },
                { label: 'Kota/Kab', nilai: teks(a.kota_kabupaten) },
                { label: 'Kecamatan', nilai: teks(a.kecamatan) },
                { label: 'Kelurahan/Desa', nilai: teks(a.kelurahan) },
            ],
        },
        {
            judul: 'Depo',
            ikon: 'store',
            baris: [
                { label: 'Depo', nilai: teks(a.depo) },
                { label: 'Wilayah depo', nilai: teks(a.wilayah_depo) },
                { label: 'Jarak terdekat ke depo', nilai: teks(a.jarak_terdekat_ke_depo) },
                { label: 'Jarak terjauh ke depo', nilai: teks(a.jarak_terjauh_ke_depo) },
            ],
        },
        {
            judul: 'Pembaruan',
            ikon: 'clock',
            baris: [
                { label: 'Diupdate oleh', nilai: teks(a.diupdate_oleh) },
                { label: 'Diupdate pada', nilai: teks(a.diupdate_pada) },
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

            <dl v-if="item.baris" class="flex flex-col">
                <div v-for="baris in item.baris" :key="baris.label" class="detail-depo-baris">
                    <dt class="text-gray-500">{{ baris.label }}</dt>
                    <dd class="break-words">{{ baris.nilai }}</dd>
                </div>
            </dl>
        </section>
    </div>
</template>
