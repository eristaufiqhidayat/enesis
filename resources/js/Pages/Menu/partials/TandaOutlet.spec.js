import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import TandaOutlet from './TandaOutlet.vue';

const URL = '/outlet_all/tanda';
const props = { url: URL, outletId: 10 };
const kategori = { outlet_fiktif: 'Outlet fiktif', alamat_tidak_sesuai: 'Alamat/lokasi tidak sesuai' };
const tanda = [
    { id: 5, kategori: 'outlet_fiktif', kategori_label: 'Outlet fiktif', catatan: 'Alamat tidak ditemukan', oleh: 'koor', waktu: '2026-10-25 09:00', bisa_cabut: true },
    { id: 6, kategori: 'alamat_tidak_sesuai', kategori_label: 'Alamat/lokasi tidak sesuai', catatan: null, oleh: 'admin', waktu: '2026-10-25 10:00', bisa_cabut: false },
];

let daftar;
let bisaTambah;
let balasan;

function pasangFetch() {
    global.fetch = vi.fn((url, opsi = {}) => {
        const metode = opsi.method ?? 'GET';
        if (metode === 'GET') {
            return Promise.resolve({ ok: true, status: 200, json: () => Promise.resolve({ tanda: daftar, bisa_tambah: bisaTambah, kategori }) });
        }

        return Promise.resolve(balasan[metode] ?? { ok: true, status: metode === 'POST' ? 201 : 200, json: () => Promise.resolve({}) });
    });
}

const panggilan = (metode) => fetch.mock.calls.filter(([, opsi]) => (opsi?.method ?? 'GET') === metode);

async function siapkan(lain = {}) {
    const wrapper = mount(TandaOutlet, { props: { ...props, ...lain } });
    await flushPromises();

    return wrapper;
}

describe('TandaOutlet', () => {
    beforeEach(() => {
        daftar = tanda;
        bisaTambah = true;
        balasan = {};
        document.head.innerHTML = '<meta name="csrf-token" content="token-rahasia">';
        window.confirm = vi.fn(() => true);
        pasangFetch();
    });

    afterEach(() => {
        document.head.innerHTML = '';
    });

    it('memuat tanda outlet dan menampilkannya', async () => {
        const wrapper = await siapkan();

        expect(fetch.mock.calls[0][0]).toBe(`${URL}?outlet_id=10`);
        const item = wrapper.findAll('.tanda-motoris-item');
        expect(item).toHaveLength(2);
        expect(item[0].text()).toContain('Outlet fiktif');
        expect(item[0].text()).toContain('koor · 2026-10-25 09:00');
        expect(item[0].text()).toContain('Alamat tidak ditemukan');
        expect(item[1].text()).toContain('Alamat/lokasi tidak sesuai');
    });

    it('tidak mengirim event berubah saat memuat awal', async () => {
        const wrapper = await siapkan();

        expect(wrapper.emitted('berubah')).toBeUndefined();
    });

    it('mengirim jumlah tanda lewat event berubah setelah menandai', async () => {
        const wrapper = await siapkan();
        await wrapper.find('button.btn-outline-danger').trigger('click');
        await wrapper.find('select').setValue('alamat_tidak_sesuai');
        await wrapper.find('form').trigger('submit');
        await flushPromises();

        expect(wrapper.emitted('berubah')).toEqual([[2]]);
    });

    it('mengirim jumlah tanda lewat event berubah setelah mencabut', async () => {
        const wrapper = await siapkan();
        await wrapper.find('.tanda-motoris-item button').trigger('click');
        await flushPromises();

        expect(wrapper.emitted('berubah')).toEqual([[2]]);
    });

    it('menampilkan pesan bila belum ada tanda', async () => {
        daftar = [];

        expect((await siapkan()).text()).toContain('Belum ada tanda untuk outlet ini.');
    });

    it('tombol Tandai hanya tampil bila akun punya hak Tambah', async () => {
        expect((await siapkan()).text()).toContain('Tandai');

        bisaTambah = false;
        expect((await siapkan()).text()).not.toContain('Tandai');
    });

    it('tombol Cabut hanya pada tanda yang boleh dicabut', async () => {
        const item = (await siapkan()).findAll('.tanda-motoris-item');

        expect(item[0].text()).toContain('Cabut');
        expect(item[1].text()).not.toContain('Cabut');
    });

    it('mengirim tanda dengan header CSRF, lalu menutup form dan memuat ulang daftar', async () => {
        const wrapper = await siapkan();
        await wrapper.find('button.btn-outline-danger').trigger('click');
        await wrapper.find('select').setValue('alamat_tidak_sesuai');
        await wrapper.find('textarea').setValue('Alamat tidak cocok dengan peta');
        await wrapper.find('form').trigger('submit');
        await flushPromises();

        const [url, opsi] = panggilan('POST')[0];
        expect(url).toBe(URL);
        expect(opsi.headers['X-CSRF-TOKEN']).toBe('token-rahasia');
        expect(JSON.parse(opsi.body)).toEqual({ outlet_id: 10, kategori: 'alamat_tidak_sesuai', catatan: 'Alamat tidak cocok dengan peta' });
        expect(panggilan('GET')).toHaveLength(2);
        expect(wrapper.find('form').exists()).toBe(false);
    });

    it('menampilkan galat validasi di form dan membiarkan form terbuka', async () => {
        balasan.POST = { ok: false, status: 422, json: () => Promise.resolve({ errors: { kategori: ['Kategori wajib diisi.'] } }) };
        const wrapper = await siapkan();
        await wrapper.find('button.btn-outline-danger').trigger('click');
        await wrapper.find('form').trigger('submit');
        await flushPromises();

        expect(wrapper.find('form').text()).toContain('Kategori wajib diisi.');
        expect(panggilan('GET')).toHaveLength(1);
    });

    it('mengisi kategori dan catatan dari saran saat form dibuka, lalu mengirimnya', async () => {
        const wrapper = await siapkan({ saran: { kategori: 'alamat_tidak_sesuai', catatan: 'Indikasi jarak dekat antar kunjungan.' } });
        await wrapper.find('button.btn-outline-danger').trigger('click');

        expect(wrapper.find('select').element.value).toBe('alamat_tidak_sesuai');
        expect(wrapper.find('textarea').element.value).toBe('Indikasi jarak dekat antar kunjungan.');
        expect(wrapper.find('form').text()).toContain('Terisi dari indikasi jarak antar kunjungan');
    });

    it('mencabut tanda setelah konfirmasi lalu memuat ulang', async () => {
        const wrapper = await siapkan();
        await wrapper.find('.tanda-motoris-item button').trigger('click');
        await flushPromises();

        expect(window.confirm).toHaveBeenCalled();
        const [url, opsi] = panggilan('DELETE')[0];
        expect(url).toBe(`${URL}/5`);
        expect(opsi.headers['X-CSRF-TOKEN']).toBe('token-rahasia');
        expect(panggilan('GET')).toHaveLength(2);
    });

    it('memuat ulang tanda dan menutup form saat outlet diganti', async () => {
        const wrapper = await siapkan();
        await wrapper.find('button.btn-outline-danger').trigger('click');
        await wrapper.setProps({ outletId: 20 });
        await flushPromises();

        expect(fetch.mock.calls.at(-1)[0]).toBe(`${URL}?outlet_id=20`);
        expect(wrapper.find('form').exists()).toBe(false);
    });

    it('menampilkan pesan bila daftar gagal dimuat', async () => {
        global.fetch = vi.fn(() => Promise.resolve({ ok: false, status: 403, json: () => Promise.resolve({}) }));

        expect((await siapkan()).text()).toContain('Gagal memuat tanda outlet.');
    });
});
