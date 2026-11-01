import { afterEach, describe, expect, it, vi } from 'vitest';
import { BAWAAN_DETEKSI, KUNCI_DETEKSI, bacaDeteksi, hapusDeteksi, rapikanDeteksi, simpanDeteksi } from './deteksiKunjungan.js';

describe('deteksiKunjungan', () => {
    afterEach(() => {
        localStorage.clear();
        vi.restoreAllMocks();
    });

    it('bawaannya 11 meter dan minimum 3 kunjungan berturutan', () => {
        expect(BAWAAN_DETEKSI).toEqual({ jarak_dekat_meter: 11, min_kunjungan_berturutan: 3 });
        expect(bacaDeteksi()).toEqual(BAWAAN_DETEKSI);
    });

    it('menyimpan dan membaca kembali dari localStorage', () => {
        expect(simpanDeteksi({ jarak_dekat_meter: 20, min_kunjungan_berturutan: 5 })).toEqual({ nilai: { jarak_dekat_meter: 20, min_kunjungan_berturutan: 5 }, tersimpan: true });

        expect(JSON.parse(localStorage.getItem(KUNCI_DETEKSI))).toEqual({ jarak_dekat_meter: 20, min_kunjungan_berturutan: 5 });
        expect(bacaDeteksi()).toEqual({ jarak_dekat_meter: 20, min_kunjungan_berturutan: 5 });
    });

    it('nilai tidak sah (kosong, nol, di bawah minimum, di atas batas, bukan angka) diganti bawaannya per kolom', () => {
        expect(rapikanDeteksi({ jarak_dekat_meter: 0, min_kunjungan_berturutan: 1 })).toEqual(BAWAAN_DETEKSI);
        expect(rapikanDeteksi({ jarak_dekat_meter: 'abc', min_kunjungan_berturutan: null })).toEqual(BAWAAN_DETEKSI);
        expect(rapikanDeteksi({ jarak_dekat_meter: 99999, min_kunjungan_berturutan: 101 })).toEqual(BAWAAN_DETEKSI);
        expect(rapikanDeteksi({ jarak_dekat_meter: '8', min_kunjungan_berturutan: 1 })).toEqual({ jarak_dekat_meter: 8, min_kunjungan_berturutan: 3 });
        expect(rapikanDeteksi(undefined)).toEqual(BAWAAN_DETEKSI);
    });

    it('data localStorage yang rusak jatuh ke bawaan', () => {
        localStorage.setItem(KUNCI_DETEKSI, '{bukan json');

        expect(bacaDeteksi()).toEqual(BAWAAN_DETEKSI);
    });

    it('localStorage yang melempar galat tidak merusak: baca kembali bawaan, simpan melapor gagal', () => {
        vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => { throw new Error('diblokir'); });
        vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => { throw new Error('penuh'); });

        expect(bacaDeteksi()).toEqual(BAWAAN_DETEKSI);
        expect(simpanDeteksi({ jarak_dekat_meter: 20, min_kunjungan_berturutan: 5 }).tersimpan).toBe(false);
    });

    it('hapus mengembalikan bawaan dan membuang nilai tersimpan', () => {
        simpanDeteksi({ jarak_dekat_meter: 20, min_kunjungan_berturutan: 5 });

        expect(hapusDeteksi()).toEqual(BAWAAN_DETEKSI);
        expect(localStorage.getItem(KUNCI_DETEKSI)).toBeNull();
        expect(bacaDeteksi()).toEqual(BAWAAN_DETEKSI);
    });
});
