import { angka, angkaRingkas } from '@/Support/format.js';

// Warna achievement sama dengan Marketama: ≥ 100% hijau, 75–<100% kuning, < 75% merah.
export const warnaCapai = (n) => (n >= 100 ? 'text-green-500' : (n >= 75 ? 'text-yellow-600' : 'text-rose-500'));

export const rupiah = (n) => `Rp ${angka(n)}`;

const bagi = (a, b, desimal) => {
    if (!(b > 0)) {
        return 0;
    }

    const faktor = 10 ** desimal;

    return Math.round((a / b) * faktor) / faktor;
};

// Total semua depo dengan rumus yang sama seperti SummaryDepo::ringkas() di backend (rata-rata berbobot, bukan rata-rata dari rata-rata).
export function hitungTotal(kartu, target, hkKoordinator) {
    const jumlah = (kunci) => kartu.reduce((s, k) => s + (Number(k[kunci]) || 0), 0);
    const t = {
        total_sk: jumlah('total_sk'), total_motoris: jumlah('total_motoris'), hari_kerja: jumlah('hari_kerja'),
        call: jumlah('call'), ec: jumlah('ec'), sales: jumlah('sales'), sku: jumlah('sku'), point: jumlah('point'),
        sk_visit: jumlah('sk_visit'), visit_sk: jumlah('visit_sk'),
    };

    t.avg_hari_kerja = bagi(t.hari_kerja, t.total_motoris, 1);
    t.avg_call = bagi(t.call, t.hari_kerja, 1);
    t.avg_ec = bagi(t.ec, t.hari_kerja, 1);
    t.avg_sales = Math.round(bagi(t.sales, t.hari_kerja, 2));
    t.avg_ipt = bagi(t.sku, t.ec, 1);
    t.avg_point = bagi(t.point, t.hari_kerja, 1);
    t.call_achievement = bagi(t.avg_call * 100, target.call, 1);
    t.ec_achievement = bagi(t.avg_ec * 100, target.ec, 1);
    t.ipt_achievement = bagi(t.avg_ipt * 100, target.ipt, 1);
    t.avg_visit_sk = bagi(t.visit_sk, hkKoordinator, 1);

    return t;
}

// Kolom tabel Summary All Depo, dikelompokkan seperti kartu. tipe: angka | ringkas | rupiah | persen.
export const KELOMPOK_KOLOM = [
    { judul: 'Personil aktif', kolom: [
        { kunci: 'total_sk', label: 'Total SK', tipe: 'angka' },
        { kunci: 'total_motoris', label: 'Sales Motoris', tipe: 'angka' },
    ] },
    { judul: 'Aktivitas periode', kolom: [
        { kunci: 'sales', label: 'Total Sales', tipe: 'rupiah', tautan: true },
    ] },
    { judul: 'Rata-rata / hari kerja', kolom: [
        { kunci: 'avg_hari_kerja', label: 'AVG Hari Kerja', tipe: 'ringkas' },
        { kunci: 'avg_call', label: 'AVG Call', tipe: 'ringkas' },
        { kunci: 'call_achievement', label: 'Call Ach.', tipe: 'persen' },
        { kunci: 'avg_ec', label: 'AVG EC', tipe: 'ringkas' },
        { kunci: 'ec_achievement', label: 'EC Ach.', tipe: 'persen' },
        { kunci: 'avg_sales', label: 'AVG Sales', tipe: 'rupiah' },
        { kunci: 'avg_ipt', label: 'AVG IPT', tipe: 'ringkas' },
        { kunci: 'ipt_achievement', label: 'IPT Ach.', tipe: 'persen' },
        { kunci: 'avg_point', label: 'AVG Point', tipe: 'ringkas' },
    ] },
    { judul: 'Kunjungan SK', kolom: [
        { kunci: 'sk_visit', label: 'SK yang Visit', tipe: 'angka' },
        { kunci: 'visit_sk', label: 'Total Visit SK', tipe: 'angka' },
        { kunci: 'avg_visit_sk', label: 'AVG Visit SK', tipe: 'ringkas' },
    ] },
];

export function formatSel(nilai, tipe) {
    switch (tipe) {
        case 'rupiah': return rupiah(nilai);
        case 'ringkas': return angkaRingkas(nilai, 1);
        case 'persen': return `${angkaRingkas(nilai, 1)}%`;
        default: return angka(nilai);
    }
}
