import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import DetailDepo from './DetailDepo.vue';

vi.mock('@/Components/ui/atoms/Icon.vue', () => ({ default: { render: () => null } }));
vi.mock('@/Composables/useLeafletMap.js', () => ({ useLeafletMap: () => ({ peta: () => null }) }));

const depo = {
    kode: 'DP01',
    nama: 'Depo Jakarta Selatan',
    projek: 'Enesis',
    kota_besar: 'Jakarta',
    minimum_sku: 20,
    multi_polygon_sales: 'Ya',
    provinsi: 'DKI Jakarta',
    kota_kab: 'Jakarta Selatan',
    kecamatan: 'Kebayoran Baru',
    kelurahan_desa: 'Senayan',
    alamat: 'Jl. Contoh No. 1',
    longitude: 106.8,
    latitude: -6.2,
    total_koordinator: 3,
    jumlah_sales_aktif: 30,
    jumlah_wilayah_depo: 5,
    kuota_koordinator: 4,
    kuota_sales_motoris: 40,
    target_call: 100,
    target_ec: 50,
    durasi_tracking_gps: '15 menit',
    insentif_per_poin: 15000,
    outlet_1_sku: 12,
    minimal_value_transaksi: null,
    diupdate_oleh: 'admin',
    diupdate_pada: '2026-10-01',
    status: 'aktif',
};

describe('DetailDepo', () => {
    it('shows every section at once so the drawer can simply be scrolled', () => {
        const wrapper = mount(DetailDepo, { props: { depo } });
        const judul = wrapper.findAll('h3').map((h) => h.text());

        expect(judul).toEqual(['Ringkasan', 'Lokasi', 'Tim & Kuota', 'Target & Insentif']);
        const teksTombol = wrapper.findAll('button').map((b) => b.text());

        expect(teksTombol.some((t) => t.includes('Sebelumnya') || t.includes('Berikutnya'))).toBe(false);
    });

    it('shows the location details and a map instead of raw coordinates', () => {
        const wrapper = mount(DetailDepo, { props: { depo } });

        expect(wrapper.text()).toContain('Kelurahan/Desa');
        expect(wrapper.text()).toContain('Senayan');
        expect(wrapper.text()).not.toContain('-6.2, 106.8');
        expect(wrapper.find('.detail-depo-peta').exists()).toBe(true);
    });

    it('computes progress as value divided by quota, capped at 100', () => {
        const wrapper = mount(DetailDepo, { props: { depo } });
        const bars = wrapper.findAll('[role="progressbar"]');

        expect(bars).toHaveLength(2);
        expect(bars[0].attributes('aria-valuenow')).toBe('75');
        expect(bars[1].attributes('aria-valuenow')).toBe('75');
    });

    it('caps progress at 100 when the value exceeds the quota', () => {
        const wrapper = mount(DetailDepo, { props: { depo: { ...depo, jumlah_sales_aktif: 90 } } });

        expect(wrapper.findAll('[role="progressbar"]')[0].attributes('aria-valuenow')).toBe('100');
    });

    it('shows a note instead of a bar when the quota is empty', () => {
        const wrapper = mount(DetailDepo, { props: { depo: { ...depo, kuota_sales_motoris: null } } });

        expect(wrapper.text()).toContain('Kuota belum diisi.');
        expect(wrapper.findAll('[role="progressbar"]')).toHaveLength(1);
    });

    it('shows multi polygon sales as a badge colored by Ya or Tidak', () => {
        const ya = mount(DetailDepo, { props: { depo: { ...depo, multi_polygon_sales: 'Ya' } } });
        const tidak = mount(DetailDepo, { props: { depo: { ...depo, multi_polygon_sales: 'Tidak' } } });

        const badgeYa = ya.findAll('.badge').find((b) => b.text() === 'Ya');
        const badgeTidak = tidak.findAll('.badge').find((b) => b.text() === 'Tidak');

        expect(badgeYa.classes()).toContain('badge-success');
        expect(badgeTidak.classes()).toContain('badge-secondary');
    });

    it('formats rupiah values and shows a dash for empty ones', () => {
        const wrapper = mount(DetailDepo, { props: { depo } });

        expect(wrapper.text()).toMatch(/Rp\s15\.000/);
        expect(wrapper.text()).toContain('Minimal value transaksi');
    });
});
