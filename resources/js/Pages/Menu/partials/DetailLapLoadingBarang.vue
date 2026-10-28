<script setup>
import { computed } from 'vue';
import Badge from '@/Components/ui/atoms/Badge.vue';
import Icon from '@/Components/ui/atoms/Icon.vue';
import { rupiah, tanggalWaktuPendek } from '@/Support/format.js';

// Detail satu transaksi loading barang di dalam drawer (lihat Menu/Show.vue). Kolom dikelompokkan per informasi;
// label mengikuti kolom yang tampil di tabel, jadi kolom yang disembunyikan dari tabel juga tidak muncul di sini.
const props = defineProps({
    row: { type: Object, required: true },
    columns: { type: Object, required: true },
});

// Nama distributor dibuka sebagai drawer riwayat transaksi distributor (lihat Menu/Show.vue).
const emit = defineEmits(['buka-distributor']);

const GRUP = [
    { judul: 'Transaksi', ikon: 'file-text', kolom: ['trans_code', 'datetime_trans'] },
    { judul: 'Pihak terkait', ikon: 'users', kolom: ['distributor_name', 'koordinator_name', 'warehouse_name'] },
    { judul: 'Kuantitas', ikon: 'box', kolom: ['total_item', 'total_qty', 'total_qty_sale', 'total_qty_return'], angka: true },
    { judul: 'Nilai', ikon: 'calculator', kolom: ['grand_total', 'sale_total', 'return_total', 'margin_total', 'paid'], rupiah: true },
    { judul: 'Pembaruan', ikon: 'clock', kolom: ['updated_by_name', 'updated_at'] },
];

const formatAngka = new Intl.NumberFormat('id-ID');

// Nilai kosong ditampilkan "-"; kolom angka dan rupiah memakai pemisah ribuan (titik).
function nilai(kolom, grup) {
    const isi = props.row[kolom];

    if (isi === null || isi === '' || isi === undefined) {
        return '-';
    }

    if (grup.rupiah) {
        return rupiah(Number(isi));
    }

    return grup.angka ? formatAngka.format(Number(isi)) : isi;
}

// Kolom yang tampil di tabel tetapi tidak termasuk grup di atas ditaruh di "Lainnya" supaya tidak hilang.
const bagian = computed(() => {
    const tampil = Object.keys(props.columns);
    const dipakai = GRUP.flatMap((grup) => grup.kolom);
    const lainnya = tampil.filter((kolom) => !dipakai.includes(kolom));

    return [
        ...GRUP.map((grup) => ({
            ...grup,
            baris: grup.kolom.filter((kolom) => tampil.includes(kolom)).map((kolom) => ({
                kolom,
                label: props.columns[kolom],
                nilai: nilai(kolom, grup),
                // Sama dengan tabel: tanggal biasa, jam ditampilkan sebagai badge.
                waktu: kolom === 'datetime_trans' ? tanggalWaktuPendek(props.row[kolom]) : null,
                tautan: kolom === 'distributor_name' && Boolean(props.row[kolom]),
            })),
        })),
        ...(lainnya.length ? [{ judul: 'Lainnya', ikon: 'file-text', baris: lainnya.map((kolom) => ({ kolom, label: props.columns[kolom], nilai: nilai(kolom, {}) })) }] : []),
    ].filter((grup) => grup.baris.length > 0);
});
</script>

<template>
    <div class="flex flex-col gap-6 pb-8">
        <section v-for="grup in bagian" :key="grup.judul" class="flex flex-col gap-3">
            <h3 class="detail-depo-judul">
                <Icon :name="grup.ikon" class="detail-depo-ikon" />
                <span>{{ grup.judul }}</span>
            </h3>

            <dl class="flex flex-col">
                <div v-for="item in grup.baris" :key="item.kolom" class="detail-depo-baris">
                    <dt class="text-gray-500">{{ item.label }}</dt>
                    <dd class="break-words">
                        <template v-if="item.waktu">
                            {{ item.waktu.tanggal }}
                            <Badge variant="light">{{ item.waktu.jam }}</Badge>
                        </template>
                        <button v-else-if="item.tautan" type="button" class="tautan-depo" @click="emit('buka-distributor', { id: row.distributor_id, nama: row[item.kolom] })">{{ item.nilai }}</button>
                        <template v-else>{{ item.nilai }}</template>
                    </dd>
                </div>
            </dl>
        </section>
    </div>
</template>
