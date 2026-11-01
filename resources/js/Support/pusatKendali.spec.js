import { describe, expect, it } from 'vitest';
import { geserTanggal, kunjunganBermasalah, ringkasanKunjungan, saringKunjungan, susunTemuan, tandaKunjungan, tingkatKunjungan, tingkatTertinggi } from './pusatKendali.js';

const dasar = { di_luar_rute: false, lokasi_palsu: false, outlet_ditandai: false, lat: -7.4, lng: 112.7, penjualan: null };
const kunjungan = [
    { ...dasar, id: 1, outlet: 'Toko A', jam: '09:00', keluar: '09:30', beli: true, penjualan: { total: 35500 }, di_luar_rute: true },
    { ...dasar, id: 2, outlet: 'Toko B', jam: '10:15', keluar: null, beli: false, lat: null, lng: null },
    { ...dasar, id: 3, outlet: 'Toko C', jam: '14:00', keluar: '15:10', beli: true, penjualan: { total: 4500 }, outlet_ditandai: true },
];
const runtun = { jumlah: 2, kunjungan_id: [1, 3], outlet_id: [70, 71], saran: { kategori: 'pola_kunjungan_tidak_wajar', catatan: '2 kunjungan outlet berturutan berjarak kurang dari 5 m.' } };

describe('geserTanggal', () => {
    it('menggeser lintas bulan dan tahun tanpa terpengaruh zona waktu', () => {
        expect(geserTanggal('2026-11-01', -1)).toBe('2026-10-31');
        expect(geserTanggal('2026-12-31', 1)).toBe('2027-01-01');
        expect(geserTanggal('2028-03-01', -1)).toBe('2028-02-29');
    });
});

describe('ringkasanKunjungan', () => {
    it('menghitung beli, tidak beli, persen, penjualan, dan jam kerja', () => {
        expect(ringkasanKunjungan(kunjungan)).toEqual({
            total: 3, beli: 2, tidak: 1, persenBeli: 67, penjualan: 40000, mulai: '09:00', selesai: '15:10', durasiMenit: 370,
        });
    });

    it('memakai jam masuk bila kunjungan terakhir tanpa jam keluar, dan kosong bila tanpa kunjungan', () => {
        expect(ringkasanKunjungan(kunjungan.slice(0, 2)).selesai).toBe('10:15');
        expect(ringkasanKunjungan([])).toMatchObject({ total: 0, persenBeli: 0, mulai: null, selesai: null, durasiMenit: null });
    });
});

describe('penyaringan kunjungan', () => {
    it('mengenali kunjungan bermasalah: di luar rute, ditandai, tanpa koordinat, atau dalam runtun', () => {
        expect(kunjungan.map((k) => kunjunganBermasalah(k))).toEqual([true, true, true]);
        expect(kunjunganBermasalah(dasar)).toBe(false);
        expect(kunjunganBermasalah({ ...dasar, id: 9 }, [{ ...runtun, kunjungan_id: [9] }])).toBe(true);
    });

    it('menyaring menurut filter', () => {
        expect(saringKunjungan(kunjungan, 'semua')).toHaveLength(3);
        expect(saringKunjungan(kunjungan, 'beli').map((k) => k.id)).toEqual([1, 3]);
        expect(saringKunjungan(kunjungan, 'tidak').map((k) => k.id)).toEqual([2]);
        expect(saringKunjungan([dasar, ...kunjungan], 'bermasalah')).toHaveLength(3);
    });
});

describe('susunTemuan', () => {
    const anomali = {
        tingkat: 'indikasi', titik_mock: 0, kunjungan_mock: 0,
        lompatan: 20, kembali_ke_asal: 11, terjauh: { km: 10.5, menit: 1, kmh: 627, jam: '10:17' },
        segmen: [{ dari: { lat: -6.6, lng: 106.8 }, ke: { lat: -6.5, lng: 106.85 } }],
        menyimpang: 2, menyimpang_terjauh: { km: 4.2, jam: '11:00' }, titik_menyimpang: [{ lat: -6.7, lng: 106.9 }],
    };

    it('kosong bila tidak ada indikasi apa pun', () => {
        expect(susunTemuan({ anomali: null, indikasiJarak: [], kunjungan: [dasar] })).toEqual([]);
    });

    it('menyusun temuan GPS dengan titik untuk peta', () => {
        const temuan = susunTemuan({ anomali });

        expect(temuan.map((t) => t.kunci)).toEqual(['lompatan-gps', 'gps-menyimpang']);
        expect(temuan[0]).toMatchObject({ tingkat: 'waspada', judul: '20 lompatan posisi mustahil', titik: [[-6.6, 106.8], [-6.5, 106.85]] });
        expect(temuan[0].rincian).toContain('Terjauh 10,5 km dalam 1 menit (627 km/jam) pada 10:17');
        expect(temuan[1].titik).toEqual([[-6.7, 106.9]]);
    });

    it('menaikkan tingkat ke bahaya bila HP menandai lokasi palsu', () => {
        const temuan = susunTemuan({ anomali: { ...anomali, tingkat: 'terdeteksi', titik_mock: 3, kunjungan_mock: 1 }, kunjungan: [{ ...dasar, id: 5, outlet: 'X', lokasi_palsu: true }] });

        expect(temuan[0]).toMatchObject({ kunci: 'lokasi-palsu', tingkat: 'bahaya', kunjunganId: [5] });
        expect(temuan[0].rincian).toContain('3 titik GPS dan 1 kunjungan');
        expect(tingkatTertinggi(temuan)).toBe('bahaya');
    });

    it('memuat runtun jarak dekat, outlet ditandai, di luar rute, dan tanpa koordinat dengan kunjungan terkait', () => {
        const temuan = susunTemuan({ indikasiJarak: [runtun], kunjungan });
        const per = Object.fromEntries(temuan.map((t) => [t.kunci, t]));

        expect(temuan.map((t) => t.tingkat)).toEqual(['bahaya', 'bahaya', 'waspada', 'info']);
        expect(per['jarak-dekat-0']).toMatchObject({ rincian: runtun.saran.catatan, kunjunganId: [1, 3], titik: [[-7.4, 112.7], [-7.4, 112.7]] });
        expect(per['outlet-ditandai']).toMatchObject({ judul: '1 kunjungan ke outlet yang ditandai', rincian: 'Toko C' });
        expect(per['di-luar-rute'].kunjunganId).toEqual([1]);
        expect(per['tanpa-koordinat']).toMatchObject({ kunjunganId: [2], titik: [] });
    });
});

describe('tandaKunjungan', () => {
    it('hanya memuat peringatan, tidak hasil beli / tidak beli', () => {
        expect(tandaKunjungan({ ...dasar, id: 1, beli: false })).toEqual([]);
    });

    it('mengurutkan peringatan dengan tingkatnya dan menentukan tingkat tepi kartu', () => {
        const k = { ...dasar, id: 1, di_luar_rute: true, lokasi_palsu: true, lat: null, lng: null };

        expect(tandaKunjungan(k, [runtun]).map((t) => [t.label, t.tingkat])).toEqual([
            ['Lokasi palsu', 'bahaya'], ['Di luar rute', 'bahaya'], ['Jarak dekat', 'waspada'], ['Tanpa koordinat', 'info'],
        ]);
        expect(tingkatKunjungan(k, [runtun])).toBe('bahaya');
        expect(tingkatKunjungan({ ...dasar, id: 1, di_luar_rute: true })).toBe('bahaya');
        expect(tingkatKunjungan({ ...dasar, id: 1 }, [{ ...runtun, kunjungan_id: [1] }])).toBe('waspada');
        expect(tingkatKunjungan({ ...dasar, id: 1, lat: null })).toBeNull();
        expect(tingkatKunjungan(dasar)).toBeNull();
    });
});
