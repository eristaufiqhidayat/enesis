import { describe, expect, it } from 'vitest';
import { durasiSingkat, jarakSingkat, ringkasanPenjualan } from './format.js';

describe('durasiSingkat', () => {
    it('menulis kurang dari 1 menit untuk 0 menit', () => {
        expect(durasiSingkat(0)).toBe('kurang dari 1 menit');
    });

    it('menulis menit di bawah satu jam', () => {
        expect(durasiSingkat(1)).toBe('1 menit');
        expect(durasiSingkat(12)).toBe('12 menit');
        expect(durasiSingkat(59)).toBe('59 menit');
    });

    it('menulis jam dan sisa menit mulai 60 menit', () => {
        expect(durasiSingkat(60)).toBe('1 j');
        expect(durasiSingkat(65)).toBe('1 j 5 mnt');
        expect(durasiSingkat(130)).toBe('2 j 10 mnt');
    });
});

describe('jarakSingkat', () => {
    it('menulis meter di bawah 1 km', () => {
        expect(jarakSingkat(850)).toBe('850 m');
        expect(jarakSingkat(999.4)).toBe('999 m');
    });

    it('menulis kilometer satu desimal dengan koma', () => {
        expect(jarakSingkat(3400)).toBe('3,4 km');
        expect(jarakSingkat(1000)).toBe('1,0 km');
        expect(jarakSingkat(12750)).toBe('12,8 km');
    });
});

describe('ringkasanPenjualan', () => {
    it('menulis nilai, jenis, dan unit untuk satu transaksi', () => {
        const teks = ringkasanPenjualan({ transaksi: 1, total: 35500, sku: 3, qty: 5 }).replace(/\u00a0/g, ' ');

        expect(teks).toBe('Rp 35.500 · 3 jenis · 5 unit');
    });

    it('mengawali jumlah transaksi bila lebih dari satu dan menulis desimal kuantitas', () => {
        const teks = ringkasanPenjualan({ transaksi: 2, total: 3500, sku: 3, qty: 5.5 }).replace(/\u00a0/g, ' ');

        expect(teks).toBe('2 transaksi · Rp 3.500 · 3 jenis · 5,5 unit');
    });
});
