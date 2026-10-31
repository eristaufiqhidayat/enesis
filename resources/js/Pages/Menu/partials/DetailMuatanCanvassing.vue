<script setup>
import { computed, onMounted, ref } from 'vue';
import Badge from '@/Components/ui/atoms/Badge.vue';
import Button from '@/Components/ui/atoms/Button.vue';
import FieldError from '@/Components/ui/atoms/FieldError.vue';
import Icon from '@/Components/ui/atoms/Icon.vue';
import Input from '@/Components/ui/atoms/Input.vue';
import Alert from '@/Components/ui/molecules/Alert.vue';
import Modal from '@/Components/ui/molecules/Modal.vue';
import BarisTambahProduk from '@/Components/ui/molecules/BarisTambahProduk.vue';
import DataTable from '@/Components/ui/organisms/DataTable.vue';
import { useToast } from '@/Composables/useToast.js';
import { angkaRingkas } from '@/Support/format.js';

// Daftar produk satu muatan canvassing di dalam drawer tabel barang_canvassing (lihat Menu/Show.vue).
// Data diambil dari endpoint item (lihat BarangCanvassingController). Selama muatan masih Disiapkan dan akun punya
// hak ubah (`dapat_diubah` dari server), item bisa diubah: qty, hapus produk, tambah produk, lalu Simpan (PUT .../item).
// Emit 'diubah' setelah simpan sukses supaya tabel daftar dimuat ulang. Muatan Disiapkan juga bisa dibatalkan
// (`dapat_dibatalkan`: hak hapus): setelah konfirmasi dikirim DELETE .../{id} dan emit 'dibatalkan' agar drawer menutup.
const props = defineProps({
    row: { type: Object, required: true },
});

const emit = defineEmits(['diubah', 'dibatalkan']);

const toast = useToast();
const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
const urlItem = `/barang_canvassing/${props.row.source_id}/item`;

const KOLOM = {
    product_name: 'Produk',
    product_sku: 'SKU',
    unit_name: 'Satuan',
    bawa_qty: 'Qty Bawa',
    total_qty: 'Qty Diterima',
    sale_qty: 'Qty Terjual',
    sisa_qty: 'Sisa',
    return_qty: 'Qty Kembali',
    status_terjual: 'Status',
};
const KOLOM_UBAH = { product_name: 'Produk', product_sku: 'SKU', qty: 'Qty Bawa', aksi: '' };

const memuat = ref(true);
const galat = ref(null);
const baris = ref([]);
const dapatDiubah = ref(false);
const dapatDibatalkan = ref(false);
const konfirmasiBatal = ref(false);
const membatalkan = ref(false);

// Mode ubah: draf = salinan item yang sedang diedit, produk = pilihan untuk baris tambah.
const modeUbah = ref(false);
const draf = ref([]);
const produk = ref([]);
const menyimpan = ref(false);
// Daftar produk untuk mode ubah sedang dimuat (ada jeda sebelum tampilan berganti): tombol Ubah item menampilkan spinner.
const memulaiUbah = ref(false);
const galatBaris = ref({});
const galatUmum = ref('');

async function ambil() {
    memuat.value = true;
    galat.value = null;

    try {
        const respon = await fetch(urlItem, { headers: { Accept: 'application/json' } });

        if (!respon.ok) {
            throw new Error(String(respon.status));
        }

        const data = await respon.json();
        baris.value = data.data;
        dapatDiubah.value = data.dapat_diubah === true;
        dapatDibatalkan.value = data.dapat_dibatalkan === true;
    } catch {
        galat.value = 'Detail produk belum bisa dimuat. Coba lagi.';
    } finally {
        memuat.value = false;
    }
}

onMounted(ambil);

// Total qty bawa mengikuti isi terbaru (setelah ubah), bukan ringkasan baris tabel yang dibuka.
const totalBawa = computed(() => (baris.value.length
    ? angkaRingkas(baris.value.reduce((jumlah, b) => jumlah + Number(b.qty_bawa_mentah), 0), 2)
    : props.row.total_qty_bawa));

async function mulaiUbah() {
    if (memulaiUbah.value) return;

    memulaiUbah.value = true;
    galatUmum.value = '';
    galatBaris.value = {};
    draf.value = baris.value.map((b) => ({
        produk_id: b.produk_id,
        nama: b.product_name,
        sku: b.product_sku,
        qty: String(b.qty_bawa_mentah),
    }));

    try {
        const respon = await fetch(`/barang_canvassing/opsi?depo=${encodeURIComponent(props.row.depo_id)}`, { headers: { Accept: 'application/json' } });

        if (!respon.ok) {
            throw new Error(String(respon.status));
        }

        produk.value = (await respon.json()).produk;
        modeUbah.value = true;
    } catch {
        toast.galat('Daftar produk belum bisa dimuat. Coba lagi.');
    } finally {
        memulaiUbah.value = false;
    }
}

function batalUbah() {
    modeUbah.value = false;
}

// Produk yang sudah ada di draf tidak ditawarkan lagi.
const opsiProduk = computed(() => {
    const dipakai = new Set(draf.value.map((d) => String(d.produk_id)));

    return produk.value
        .filter((p) => !dipakai.has(String(p.id)))
        .map((p) => ({ value: p.id, label: p.kode ? `${p.kode} · ${p.nama}` : p.nama }));
});

function tambahProduk({ produk_id: id, qty_bawa: qty }) {
    const p = produk.value.find((x) => String(x.id) === String(id));

    draf.value.push({ produk_id: Number(id), nama: p?.nama ?? `Produk ${id}`, sku: p?.kode ?? '', qty: String(qty) });
}

function hapusProduk(index) {
    draf.value.splice(index, 1);
}

const barisUbah = computed(() => draf.value.map((d, index) => ({
    product_name: d.nama,
    product_sku: d.sku,
    nama: d.nama,
    indeks: index,
    galat: galatBaris.value[index] ?? null,
})));

async function batalkanMuatan() {
    membatalkan.value = true;

    try {
        const respon = await fetch(`/barang_canvassing/${props.row.source_id}`, {
            method: 'DELETE',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
        });

        if (respon.ok) {
            toast.sukses(`Muatan ${props.row.canvassing_no} dibatalkan.`);
            konfirmasiBatal.value = false;
            emit('dibatalkan');

            return;
        }

        const data = await respon.json().catch(() => ({}));
        konfirmasiBatal.value = false;

        if (respon.status === 404) {
            // Sudah dibatalkan orang lain atau di luar cakupan: tidak ada lagi yang bisa dilihat di drawer.
            toast.galat('Muatan tidak ditemukan atau sudah dibatalkan.');
            emit('dibatalkan');
        } else {
            // 409: sudah diterima motoris. Tampilkan isi terbaru (hanya-baca) dan segarkan tabel daftar.
            toast.galat(data.message ?? 'Muatan belum bisa dibatalkan. Coba lagi.');
            await ambil();
            emit('diubah');
        }
    } catch {
        konfirmasiBatal.value = false;
        toast.galat('Muatan belum bisa dibatalkan. Periksa koneksi lalu coba lagi.');
    } finally {
        membatalkan.value = false;
    }
}

async function simpan() {
    galatUmum.value = '';
    galatBaris.value = {};

    if (!draf.value.length) {
        galatUmum.value = 'Muatan harus punya minimal satu produk.';

        return;
    }

    const salah = {};
    draf.value.forEach((d, i) => {
        if (!(Number(d.qty) > 0)) salah[i] = 'Qty harus lebih dari 0.';
    });
    galatBaris.value = salah;
    if (Object.keys(salah).length) return;

    menyimpan.value = true;
    try {
        const respon = await fetch(urlItem, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({ items: draf.value.map((d) => ({ produk_id: d.produk_id, qty_bawa: d.qty })) }),
        });

        if (respon.ok) {
            toast.sukses('Item muatan diperbarui.');
            modeUbah.value = false;
            await ambil();
            emit('diubah');

            return;
        }

        const data = await respon.json().catch(() => ({}));

        if (respon.status === 409) {
            // Muatan sudah diterima motoris saat diedit: buang draf dan tampilkan isi terbaru (hanya-baca).
            toast.galat(data.message ?? 'Muatan sudah diterima motoris, tidak bisa diubah.');
            modeUbah.value = false;
            await ambil();
            emit('diubah');
        } else if (respon.status === 422) {
            // Galat per baris: kunci "items.N.qty_bawa" / "items.N.produk_id"; selebihnya "items".
            for (const [kunci, pesan] of Object.entries(data.errors ?? {})) {
                const cocok = /^items\.(\d+)\./.exec(kunci);

                if (cocok) galatBaris.value = { ...galatBaris.value, [cocok[1]]: pesan[0] };
                else galatUmum.value = pesan[0];
            }
        } else {
            galatUmum.value = 'Item belum bisa disimpan. Coba lagi.';
        }
    } catch {
        galatUmum.value = 'Item belum bisa disimpan. Periksa koneksi lalu coba lagi.';
    } finally {
        menyimpan.value = false;
    }
}
</script>

<template>
    <div class="flex flex-col gap-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="flex flex-wrap gap-x-5 gap-y-1 text-xs text-gray-600">
                <span>Motoris: <b class="text-gray-900">{{ row.salesperson_name }}</b></span>
                <span>Depo: <b class="text-gray-900">{{ row.warehouse_name || '-' }}</b></span>
                <span>Qty Bawa: <b class="text-gray-900">{{ totalBawa }}</b></span>
            </div>
            <div v-if="(dapatDiubah || dapatDibatalkan) && !galat" class="flex items-center gap-2">
                <template v-if="modeUbah">
                    <Button variant="light" :disabled="menyimpan" @click="batalUbah">Batal</Button>
                    <Button :loading="menyimpan" :disabled="menyimpan" @click="simpan">Simpan</Button>
                </template>
                <template v-else>
                    <Button v-if="dapatDiubah" variant="light" :loading="memulaiUbah" :disabled="memuat" @click="mulaiUbah"><Icon name="pen" class="me-1" /> Ubah item</Button>
                    <Button v-if="dapatDibatalkan" variant="outline-danger" :disabled="memuat || memulaiUbah" @click="konfirmasiBatal = true"><Icon name="trash" class="me-1" /> Batalkan muatan</Button>
                </template>
            </div>
        </div>
        <Alert v-if="galat" variant="warning">{{ galat }}</Alert>

        <template v-else-if="modeUbah">
            <BarisTambahProduk :options="opsiProduk" :disabled="menyimpan" @tambah="tambahProduk" />
            <FieldError v-if="galatUmum">{{ galatUmum }}</FieldError>
            <DataTable :columns="KOLOM_UBAH" :rows="barisUbah" :total="barisUbah.length" empty-text="Belum ada produk.">
                <template #cell-qty="{ row: r }">
                    <Input v-model="draf[r.indeks].qty" type="number" step="any" min="0" class="w-28" :aria-label="`Qty bawa ${r.nama}`" :disabled="menyimpan" />
                    <FieldError v-if="r.galat">{{ r.galat }}</FieldError>
                </template>
                <template #cell-aksi="{ row: r }">
                    <Button variant="outline-danger" :disabled="menyimpan" :aria-label="`Hapus ${r.nama} dari muatan`" @click="hapusProduk(r.indeks)">Hapus</Button>
                </template>
            </DataTable>
        </template>

        <DataTable
            v-else
            tinggi-penuh
            tinggi-tetap
            :columns="KOLOM"
            :rows="baris"
            :total="baris.length"
            :memuat="memuat"
            empty-text="Muatan ini belum punya produk."
        >
            <template #cell-return_qty="{ row: r }">{{ r.return_qty ?? '-' }}</template>
            <template #cell-status_terjual="{ row: r }">
                <Badge :variant="r.status_terjual === 'Habis Terjual' ? 'success' : 'warning'">{{ r.status_terjual }}</Badge>
            </template>
        </DataTable>

        <Modal :show="konfirmasiBatal" :title="`Batalkan muatan ${row.canvassing_no}?`" @close="konfirmasiBatal = false">
            <p class="mb-0">Muatan untuk <b>{{ row.salesperson_name }}</b> tidak akan tampil lagi di daftar dan tidak bisa diterima motoris di aplikasi HP. Setelah itu Anda bisa membuat muatan pengganti.</p>
            <template #footer>
                <Button variant="light" :disabled="membatalkan" @click="konfirmasiBatal = false">Kembali</Button>
                <Button variant="outline-danger" :loading="membatalkan" :disabled="membatalkan" @click="batalkanMuatan">Ya, batalkan</Button>
            </template>
        </Modal>
    </div>
</template>
