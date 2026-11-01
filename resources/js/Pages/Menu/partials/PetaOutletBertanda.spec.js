import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import PetaOutletBertanda from './PetaOutletBertanda.vue';

vi.mock('@/Composables/useLeafletMap.js', () => ({ useLeafletMap: vi.fn(() => ({ peta: () => null })) }));

const outlet = { id: 70, nama: 'Toko A', latitude: -7.4, longitude: 112.7 };
const sekitar = [
    { outlet_id: 71, outlet: 'Toko Dekat', latitude: -7.402, longitude: 112.7, jarak_m: 222, jumlah_tanda: 2, kategori_label: 'Outlet fiktif', ditandai_pada: '2026-10-26' },
    { outlet_id: 72, outlet: 'Toko Jauh', latitude: -7.41, longitude: 112.7, jarak_m: 1450, jumlah_tanda: 1, kategori_label: 'Alamat tidak sesuai', ditandai_pada: '2026-10-20' },
];

const pasang = (balasan) => { global.fetch = vi.fn(() => Promise.resolve(balasan)); };
const siapkan = async () => {
    const wrapper = mount(PetaOutletBertanda, { props: { url: '/tracking_sales/tanda/outlet-terdekat', outlet } });
    await flushPromises();

    return wrapper;
};

describe('PetaOutletBertanda', () => {
    beforeEach(() => { window.L = undefined; localStorage.clear(); });
    afterEach(() => { document.body.innerHTML = ''; });

    it('memuat outlet bertanda di sekitar dan menampilkannya terurut dengan jarak', async () => {
        pasang({ ok: true, json: () => Promise.resolve({ sekitar, radius_m: 2000 }) });
        const wrapper = await siapkan();

        expect(fetch.mock.calls[0][0]).toBe('/tracking_sales/tanda/outlet-terdekat?outlet_id=70&radius_m=11');
        expect(wrapper.find('.detail-depo-peta').exists()).toBe(true);
        const item = wrapper.findAll('.tanda-motoris-item');
        expect(item).toHaveLength(2);
        expect(item[0].text()).toContain('Toko Dekat');
        expect(item[0].text()).toContain('222 m dari outlet ini');
        expect(item[0].text()).toContain('2 tanda');
        expect(item[0].text()).toContain('Ditandai 26 Okt 2026');
        expect(item[1].text()).toContain('1,5 km dari outlet ini');
        expect(wrapper.text()).toContain('radius 11 m');
    });

    it('memberi keterangan bila tidak ada outlet bertanda lain di sekitar', async () => {
        pasang({ ok: true, json: () => Promise.resolve({ sekitar: [], radius_m: 2000 }) });
        const wrapper = await siapkan();

        expect(wrapper.text()).toContain('Tidak ada outlet bertanda lain di sekitar.');
    });

    it('menampilkan pesan bila gagal memuat', async () => {
        pasang({ ok: false, status: 500, json: () => Promise.resolve({}) });
        const wrapper = await siapkan();

        expect(wrapper.text()).toContain('Gagal memuat outlet bertanda di sekitar.');
    });

    it('memakai radius dari localStorage dan menampilkannya di form', async () => {
        localStorage.setItem('enesis.deteksiKunjungan', JSON.stringify({ jarak_dekat_meter: 250, min_kunjungan_berturutan: 4 }));
        pasang({ ok: true, json: () => Promise.resolve({ sekitar: [], radius_m: 250 }) });
        const wrapper = await siapkan();

        expect(fetch.mock.calls[0][0]).toContain('radius_m=250');
        expect(wrapper.find('#radius_sekitar').element.value).toBe('250');
        expect(wrapper.text()).toContain('radius 250 m');
    });

    it('menerapkan radius baru: menyimpan ke localStorage (tanpa mengubah minimum kunjungan) lalu memuat ulang', async () => {
        localStorage.setItem('enesis.deteksiKunjungan', JSON.stringify({ jarak_dekat_meter: 250, min_kunjungan_berturutan: 4 }));
        pasang({ ok: true, json: () => Promise.resolve({ sekitar: [], radius_m: 500 }) });
        const wrapper = await siapkan();

        await wrapper.find('#radius_sekitar').setValue('500');
        await wrapper.find('form').trigger('submit');
        await flushPromises();

        expect(JSON.parse(localStorage.getItem('enesis.deteksiKunjungan'))).toEqual({ jarak_dekat_meter: 500, min_kunjungan_berturutan: 4 });
        expect(fetch.mock.calls.at(-1)[0]).toContain('radius_m=500');
        expect(wrapper.text()).toContain('radius 500 m');
    });

    it('tombol Terapkan nonaktif untuk nilai tidak sah atau tidak berubah', async () => {
        pasang({ ok: true, json: () => Promise.resolve({ sekitar: [], radius_m: 11 }) });
        const wrapper = await siapkan();
        const tombol = () => wrapper.find('form button[type="submit"]');

        expect(tombol().attributes('disabled')).toBeDefined();
        await wrapper.find('#radius_sekitar').setValue('0');
        expect(tombol().attributes('disabled')).toBeDefined();
        expect(wrapper.find('form').text()).toContain('Isi bilangan bulat 1–10000 meter.');
        await wrapper.find('#radius_sekitar').setValue('300');
        expect(tombol().attributes('disabled')).toBeUndefined();
    });

    it('menampilkan spinner di tombol Terapkan selama pengambilan ulang, bukan saat muat awal', async () => {
        pasang({ ok: true, json: () => Promise.resolve({ sekitar: [], radius_m: 11 }) });
        const wrapper = mount(PetaOutletBertanda, { props: { url: '/tracking_sales/tanda/outlet-terdekat', outlet } });
        expect(wrapper.find('.btn-spinner').exists()).toBe(false);
        await flushPromises();

        let selesai;
        global.fetch = vi.fn(() => new Promise((resolve) => { selesai = () => resolve({ ok: true, json: () => Promise.resolve({ sekitar: [], radius_m: 300 }) }); }));
        await wrapper.find('#radius_sekitar').setValue('300');
        await wrapper.find('form').trigger('submit');
        await flushPromises();

        const tombol = wrapper.find('form button[type="submit"]');
        expect(tombol.find('.btn-spinner').exists()).toBe(true);
        expect(tombol.attributes('disabled')).toBeDefined();

        selesai();
        await flushPromises();
        expect(wrapper.find('form .btn-spinner').exists()).toBe(false);
    });
});
