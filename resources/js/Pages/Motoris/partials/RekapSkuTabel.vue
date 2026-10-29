<script setup>
import { onMounted, onUnmounted, onUpdated, ref } from 'vue';
import Badge from '@/Components/ui/atoms/Badge.vue';
import Card from '@/Components/ui/molecules/Card.vue';
import { angkaRingkas } from '@/Support/format.js';

const props = defineProps({
    judul: { type: String, required: true },
    tercapai: { type: Boolean, required: true },
    jumlah: { type: Number, required: true },
    perMotoris: { type: Boolean, required: true },
    baris: { type: Array, required: true },
    total: { type: Object, required: true },
});

const kolom = props.perMotoris ? 7 : 6;

// Dua kartu berdampingan (lg ke atas) mengisi sisa tinggi layar: area scroll tabel dibatasi sampai bawah layar,
// sehingga judul halaman, filter, dan header tabel (sticky, lihat .tabel-header-sticky) tetap terlihat saat baris di-scroll.
// Tinggi dihitung dari posisi kartu dan jarak dari kartu ke bawah <main> (termasuk catatan cara hitung di bawahnya),
// sama seperti pola sesuaikanTinggi() di SummaryDepoTabel.vue. Di bawah lg kartu ditumpuk, jadi tinggi dibiarkan mengikuti isi.
const scrollEl = ref(null);
const LAYAR_LG = '(min-width: 1024px)';

function sesuaikanTinggi() {
    if (!scrollEl.value) {
        return;
    }

    if (!window.matchMedia(LAYAR_LG).matches) {
        scrollEl.value.style.height = '';

        return;
    }

    const kartu = scrollEl.value.closest('.card');
    const main = scrollEl.value.closest('main');
    const atas = scrollEl.value.getBoundingClientRect().top;
    // Jarak dari bawah kartu ke bawah <main>; +2px jaga-jaga pembulatan subpixel supaya tidak memunculkan scrollbar halaman.
    const bawah = main.getBoundingClientRect().bottom - kartu.getBoundingClientRect().bottom + 2;

    scrollEl.value.style.height = `${Math.max(0, Math.round(window.innerHeight - atas - bawah))}px`;
}

onMounted(() => {
    sesuaikanTinggi();
    window.addEventListener('resize', sesuaikanTinggi);
});

onUpdated(sesuaikanTinggi);

onUnmounted(() => window.removeEventListener('resize', sesuaikanTinggi));
</script>

<template>
    <Card body-class="p-0">
        <template #header>
            <div class="text-xs font-semibold" :class="tercapai ? 'text-green-700' : 'text-red-700'">{{ judul }}</div>
            <div class="text-xs text-gray-500">{{ baris.length }} dari {{ jumlah }} total {{ perMotoris ? 'motoris' : 'depo' }}</div>
        </template>
        <div ref="scrollEl" class="overflow-auto">
        <table class="data-table tabel-header-sticky">
            <thead>
                <tr>
                    <th scope="col">No</th>
                    <th scope="col">Depo</th>
                    <th v-if="perMotoris" scope="col">Motoris</th>
                    <th scope="col" class="text-end">Total Qty</th>
                    <th scope="col" class="text-end">Hari Kerja</th>
                    <th scope="col" class="text-end">{{ perMotoris ? 'Avg/Day' : 'Avg/Day/Motoris' }}</th>
                    <th scope="col">Kategori</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(b, i) in baris" :key="b.id">
                    <td>{{ i + 1 }}</td>
                    <template v-if="perMotoris">
                        <td>{{ b.depo ?? '-' }}</td>
                        <td>
                            {{ b.nama }}
                            <Badge v-if="!b.aktif">Tidak aktif</Badge>
                        </td>
                    </template>
                    <td v-else>{{ b.nama }}</td>
                    <td class="text-end">{{ angkaRingkas(b.qty) }}</td>
                    <td class="text-end">{{ b.hari_kerja }}</td>
                    <td class="text-end" :title="!perMotoris ? `${b.hari_motoris} hari-motoris bertransaksi` : null">{{ angkaRingkas(b.avg) }}</td>
                    <td><Badge :variant="tercapai ? 'success' : 'danger'">{{ tercapai ? 'Tercapai' : 'Belum' }}</Badge></td>
                </tr>
                <tr v-if="!baris.length">
                    <td :colspan="kolom" class="text-center text-gray-500">Tidak ada data</td>
                </tr>
            </tbody>
            <tfoot v-if="baris.length">
                <tr class="font-semibold">
                    <th scope="row" :colspan="perMotoris ? 3 : 2">TOTAL</th>
                    <td class="text-end">{{ angkaRingkas(total.qty) }}</td>
                    <td class="text-end">{{ total.hari_kerja }}</td>
                    <td class="text-end">{{ angkaRingkas(total.avg) }}</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
        </div>
    </Card>
</template>
