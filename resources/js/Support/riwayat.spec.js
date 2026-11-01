import { describe, expect, it } from 'vitest';
import { infoRiwayat, riwayatTabel, tabelLastSeen } from './riwayat.js';

const tigaTab = ['tracking_sales', 'tracking_sales_history_location', 'motoris_ditandai'];
const tabKoordinator = ['tracking_koordinator', 'tracking_koordinator_history_location'];
const sales = { id: 11, nama: 'Rudi', terakhir_terlihat: '2026-10-22 11:00:00' };

describe('infoRiwayat', () => {
    it('Last Seen Sales: membuka riwayat sales pada tanggal terakhir terlihat', () => {
        expect(infoRiwayat('tracking_last_seen_sales', sales, tigaTab)).toEqual({
            orang: sales, slug: 'tracking_sales_history_location', idParam: 'sales_id', tanggal: '2026-10-22',
        });
    });

    it('tidak salah memilih tab ketiga sebagai tujuan riwayat (tujuan eksplisit, bukan tab lain pertama)', () => {
        const urutTerbalik = [tigaTab[2], tigaTab[1], tigaTab[0]];

        expect(infoRiwayat('tracking_last_seen_sales', sales, urutTerbalik).slug).toBe('tracking_sales_history_location');
    });

    it('Last Seen Koordinator: memakai hak riwayat koordinator dan tidak butuh hak Sales', () => {
        const koordinator = { id: 5, nama: 'Koor', terakhir_terlihat: '2026-10-21 09:00:00' };

        expect(infoRiwayat('tracking_last_seen_koordinator', koordinator, tabKoordinator)).toEqual({
            orang: koordinator, slug: 'tracking_koordinator_history_location', idParam: 'koordinator_id', tanggal: '2026-10-21',
        });
    });

    it('tanggal pilihan (dari lencana Ditandai) menggantikan tanggal terakhir terlihat', () => {
        expect(infoRiwayat('tracking_last_seen_sales', sales, tigaTab, '2026-10-18').tanggal).toBe('2026-10-18');
    });

    it('rekap: orang dibentuk dari karyawan_id dan nama motoris, tanggal dari tanggal tanda', () => {
        const baris = { karyawan_id: '718', motoris: 'Ade Suherman', tanggal: '2026-10-22', status: 'Aktif' };

        expect(infoRiwayat('tanda_motoris_rekap', baris, tigaTab)).toEqual({
            orang: { id: 718, nama: 'Ade Suherman' }, slug: 'tracking_sales_history_location', idParam: 'sales_id', tanggal: '2026-10-22',
        });
    });

    it('null bila akun tidak punya hak riwayat', () => {
        expect(infoRiwayat('tracking_last_seen_sales', sales, [tigaTab[0]])).toBeNull();
        expect(infoRiwayat('tracking_last_seen_sales', sales, [])).toBeNull();
        expect(infoRiwayat('tracking_last_seen_sales', sales, undefined)).toBeNull();
    });

    it('drawer Sales juga butuh akses Tracking Last Seen Sales (tanda motoris): rekap tanpa itu tidak bisa dibuka', () => {
        const tanpaLastSeen = [tigaTab[1], tigaTab[2]];
        const baris = { karyawan_id: 718, motoris: 'Ade', tanggal: '2026-10-22' };

        expect(infoRiwayat('tanda_motoris_rekap', baris, tanpaLastSeen)).toBeNull();
    });

    it('null bila tanggal tidak valid, mis. Belum ada data', () => {
        expect(infoRiwayat('tracking_last_seen_sales', { ...sales, terakhir_terlihat: 'Belum ada data' }, tigaTab)).toBeNull();
        expect(infoRiwayat('tracking_last_seen_sales', { ...sales, terakhir_terlihat: null }, tigaTab)).toBeNull();
        expect(infoRiwayat('tanda_motoris_rekap', { karyawan_id: 1, motoris: 'x', tanggal: null }, tigaTab)).toBeNull();
    });

    it('null untuk tabel yang tidak punya tautan riwayat', () => {
        expect(infoRiwayat('master_depo', { id: 1 }, tigaTab)).toBeNull();
    });

    it('riwayatTabel dan tabelLastSeen konsisten', () => {
        expect(Object.keys(riwayatTabel)).toEqual(expect.arrayContaining(tabelLastSeen));
        expect(riwayatTabel.tanda_motoris_rekap.idParam).toBe('sales_id');
    });
});
