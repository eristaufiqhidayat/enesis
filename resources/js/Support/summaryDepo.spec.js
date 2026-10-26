import { describe, expect, it } from 'vitest';
import { formatSel, hitungTotal, warnaCapai } from './summaryDepo.js';

const target = { call: 45, ec: 35, ipt: 3 };

// Dua depo dengan angka dari SummaryDepoTest (Sidoarjo) dan satu depo kosong.
const kartu = [
    { total_sk: 1, total_motoris: 2, hari_kerja: 5, call: 5, ec: 2, sales: 170000, sku: 6, point: 17, sk_visit: 1, visit_sk: 3 },
    { total_sk: 0, total_motoris: 1, hari_kerja: 0, call: 0, ec: 0, sales: 0, sku: 0, point: 0, sk_visit: 0, visit_sk: 0 },
];

describe('hitungTotal', () => {
    it('menjumlahkan kolom total dan menghitung rata-rata berbobot dengan rumus backend', () => {
        const t = hitungTotal(kartu, target, 2);

        expect([t.total_sk, t.total_motoris, t.hari_kerja, t.call, t.ec, t.sales]).toEqual([1, 3, 5, 5, 2, 170000]);
        expect([t.avg_call, t.avg_ec, t.avg_sales, t.avg_ipt, t.avg_point]).toEqual([1, 0.4, 34000, 3, 3.4]);
        expect(t.avg_hari_kerja).toBe(1.7); // 5 ÷ 3 sales motoris
        expect([t.call_achievement, t.ec_achievement, t.ipt_achievement]).toEqual([2.2, 1.1, 100]);
        expect(t.avg_visit_sk).toBe(1.5); // 3 visit ÷ 2 hari koordinator
    });

    it('tidak membagi dengan nol untuk daftar kosong', () => {
        const t = hitungTotal([], target, 0);

        expect([t.avg_call, t.avg_ipt, t.avg_visit_sk, t.ec_achievement]).toEqual([0, 0, 0, 0]);
    });
});

describe('warnaCapai', () => {
    it('hijau ≥ 100, kuning 75–<100, merah di bawahnya', () => {
        expect([warnaCapai(100), warnaCapai(75), warnaCapai(74.9)]).toEqual(['text-green-500', 'text-yellow-600', 'text-rose-500']);
    });
});

describe('formatSel', () => {
    it('memformat sesuai tipe kolom', () => {
        expect(formatSel(170000, 'rupiah')).toBe('Rp 170.000');
        expect(formatSel(2.2, 'persen')).toBe('2,2%');
        expect(formatSel(1234, 'angka')).toBe('1.234');
    });
});
