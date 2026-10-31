import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import TandaMotoris from './TandaMotoris.vue';

const URL = '/tracking_sales/tanda';
const props = { url: URL, salesId: 11, tanggal: '2026-10-24' };
const kategori = { penjualan_fiktif: 'Penjualan fiktif', lokasi_palsu: 'Lokasi / GPS palsu' };
const tanda = [
    { id: 5, kategori: 'penjualan_fiktif', kategori_label: 'Penjualan fiktif', catatan: 'Nota tidak wajar', oleh: 'koor', waktu: '2026-10-25 09:00', bisa_cabut: true },
    { id: 6, kategori: 'lokasi_palsu', kategori_label: 'Lokasi / GPS palsu', catatan: null, oleh: 'admin', waktu: '2026-10-25 10:00', bisa_cabut: false },
];

let daftar;
let lainnya;
let bisaTambah;
let balasan;

// fetch palsu: GET daftar tanda; POST/DELETE memakai `balasan` (default sukses).
function pasangFetch() {
    global.fetch = vi.fn((url, opsi = {}) => {
        const metode = opsi.method ?? 'GET';
        if (metode === 'GET') {
            return Promise.resolve({ ok: true, status: 200, json: () => Promise.resolve({ tanda: daftar, lainnya, bisa_tambah: bisaTambah, kategori }) });
        }

        return Promise.resolve(balasan[metode] ?? { ok: true, status: metode === 'POST' ? 201 : 200, json: () => Promise.resolve({}) });
    });
}

const panggilan = (metode) => fetch.mock.calls.filter(([, opsi]) => (opsi?.method ?? 'GET') === metode);

async function siapkan(lain = {}) {
    const wrapper = mount(TandaMotoris, { props: { ...props, ...lain } });
    await flushPromises();

    return wrapper;
}

describe('TandaMotoris', () => {
    beforeEach(() => {
        daftar = tanda;
        lainnya = [];
        bisaTambah = true;
        balasan = {};
        document.head.innerHTML = '<meta name="csrf-token" content="token-rahasia">';
        window.confirm = vi.fn(() => true);
        pasangFetch();
    });

    afterEach(() => {
        document.head.innerHTML = '';
    });

    it('memuat tanda motoris dan tanggal itu lalu menampilkannya', async () => {
        const wrapper = await siapkan();

        expect(fetch.mock.calls[0][0]).toBe(`${URL}?sales_id=11&tanggal=2026-10-24`);
        const item = wrapper.findAll('.tanda-motoris-item');
        expect(item).toHaveLength(2);
        expect(item[0].text()).toContain('Penjualan fiktif');
        expect(item[0].text()).toContain('koor · 2026-10-25 09:00');
        expect(item[0].text()).toContain('Nota tidak wajar');
        expect(item[1].text()).toContain('Lokasi / GPS palsu');
    });

    it('menampilkan pesan bila belum ada tanda', async () => {
        daftar = [];

        expect((await siapkan()).text()).toContain('Belum ada tanda pada tanggal ini.');
    });

    it('tombol Tandai motoris hanya tampil bila akun punya hak Tambah', async () => {
        expect((await siapkan()).text()).toContain('Tandai motoris');

        bisaTambah = false;
        expect((await siapkan()).text()).not.toContain('Tandai motoris');
    });

    it('tombol Cabut hanya pada tanda yang boleh dicabut', async () => {
        const item = (await siapkan()).findAll('.tanda-motoris-item');

        expect(item[0].text()).toContain('Cabut');
        expect(item[1].text()).not.toContain('Cabut');
    });

    it('mengirim tanda dengan header CSRF, lalu menutup form dan memuat ulang daftar', async () => {
        const wrapper = await siapkan();
        await wrapper.find('button.btn-outline-danger').trigger('click');
        await wrapper.find('select').setValue('lokasi_palsu');
        await wrapper.find('textarea').setValue('GPS meloncat');
        await wrapper.find('form').trigger('submit');
        await flushPromises();

        const [url, opsi] = panggilan('POST')[0];
        expect(url).toBe(URL);
        expect(opsi.headers['X-CSRF-TOKEN']).toBe('token-rahasia');
        expect(opsi.headers['Content-Type']).toBe('application/json');
        expect(JSON.parse(opsi.body)).toEqual({ sales_id: 11, tanggal: '2026-10-24', kategori: 'lokasi_palsu', catatan: 'GPS meloncat' });
        expect(panggilan('GET')).toHaveLength(2); // muat awal + muat ulang
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

    it('menampilkan pesan umum bila penyimpanan gagal', async () => {
        balasan.POST = { ok: false, status: 500, json: () => Promise.resolve({}) };
        const wrapper = await siapkan();
        await wrapper.find('button.btn-outline-danger').trigger('click');
        await wrapper.find('form').trigger('submit');
        await flushPromises();

        expect(wrapper.text()).toContain('Gagal menyimpan tanda.');
    });

    it('mengisi kategori dan catatan dari saran peringatan GPS saat form dibuka, lalu mengirimnya', async () => {
        const wrapper = await siapkan({ saran: { kategori: 'lokasi_palsu', catatan: 'Indikasi GPS tidak wajar. 20 lompatan.' } });
        await wrapper.find('button.btn-outline-danger').trigger('click');

        expect(wrapper.find('select').element.value).toBe('lokasi_palsu');
        expect(wrapper.find('textarea').element.value).toBe('Indikasi GPS tidak wajar. 20 lompatan.');
        expect(wrapper.find('form').text()).toContain('Terisi dari peringatan GPS tanggal ini');

        await wrapper.find('form').trigger('submit');
        await flushPromises();
        expect(JSON.parse(panggilan('POST')[0][1].body)).toMatchObject({ kategori: 'lokasi_palsu', catatan: 'Indikasi GPS tidak wajar. 20 lompatan.' });
    });

    it('tanpa saran form kosong dan tanpa petunjuk isian otomatis', async () => {
        const wrapper = await siapkan();
        await wrapper.find('button.btn-outline-danger').trigger('click');

        expect(wrapper.find('select').element.value).toBe('');
        expect(wrapper.find('textarea').element.value).toBe('');
        expect(wrapper.find('form').text()).not.toContain('Terisi dari peringatan');
    });

    it('Batal menutup dan mengosongkan form tanpa mengirim apa pun', async () => {
        const wrapper = await siapkan();
        await wrapper.find('button.btn-outline-danger').trigger('click');
        await wrapper.find('textarea').setValue('draf');
        await wrapper.findAll('form button').find((b) => b.text() === 'Batal').trigger('click');

        expect(wrapper.find('form').exists()).toBe(false);
        expect(panggilan('POST')).toHaveLength(0);
        await wrapper.find('button.btn-outline-danger').trigger('click');
        expect(wrapper.find('textarea').element.value).toBe('');
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

    it('tidak mencabut bila konfirmasi dibatalkan', async () => {
        window.confirm = vi.fn(() => false);
        const wrapper = await siapkan();
        await wrapper.find('.tanda-motoris-item button').trigger('click');
        await flushPromises();

        expect(panggilan('DELETE')).toHaveLength(0);
    });

    it('memuat ulang tanda dan menutup form saat tanggal drawer diganti', async () => {
        const wrapper = await siapkan();
        await wrapper.find('button.btn-outline-danger').trigger('click');
        await wrapper.setProps({ tanggal: '2026-10-25' });
        await flushPromises();

        expect(fetch.mock.calls.at(-1)[0]).toBe(`${URL}?sales_id=11&tanggal=2026-10-25`);
        expect(wrapper.find('form').exists()).toBe(false);
    });

    describe('tanda di tanggal lain', () => {
        const lain = [
            { id: 9, tanggal: '2026-10-22', kategori_label: 'Lokasi / GPS palsu', oleh: 'koor' },
            { id: 8, tanggal: '2026-10-14', kategori_label: 'Penjualan fiktif', oleh: 'admin' },
        ];

        it('tidak menampilkan apa pun bila tidak ada tanda di tanggal lain', async () => {
            expect((await siapkan()).text()).not.toContain('tanda di tanggal lain');
        });

        it('menampilkan jumlah dan menyembunyikan daftarnya sampai dibuka', async () => {
            lainnya = lain;
            const wrapper = await siapkan();

            expect(wrapper.text()).toContain('Lihat tanda di tanggal lain (2)');
            expect(wrapper.text()).not.toContain('14 Okt 2026');

            await wrapper.findAll('button').find((b) => b.text().includes('tanda di tanggal lain')).trigger('click');

            expect(wrapper.text()).toContain('Sembunyikan tanda di tanggal lain (2)');
            const baris = wrapper.findAll('.tanda-motoris-lain').map((b) => b.text());
            expect(baris).toEqual(['22 Okt 2026 · Lokasi / GPS palsu · koor', '14 Okt 2026 · Penjualan fiktif · admin']);
        });

        it('mengklik satu baris mengirim tanggalnya agar drawer berpindah', async () => {
            lainnya = lain;
            const wrapper = await siapkan();
            await wrapper.findAll('button').find((b) => b.text().includes('tanda di tanggal lain')).trigger('click');
            await wrapper.findAll('.tanda-motoris-lain')[1].trigger('click');

            expect(wrapper.emitted('pilih-tanggal')).toEqual([['2026-10-14']]);
        });

        it('daftar ikut diperbarui saat tanggal drawer diganti', async () => {
            lainnya = lain;
            const wrapper = await siapkan();
            expect(wrapper.text()).toContain('(2)');

            lainnya = [lain[0]];
            await wrapper.setProps({ tanggal: '2026-10-25' });
            await flushPromises();

            expect(wrapper.text()).toContain('(1)');
        });
    });

    it('menampilkan pesan bila daftar gagal dimuat', async () => {
        global.fetch = vi.fn(() => Promise.resolve({ ok: false, status: 403, json: () => Promise.resolve({}) }));

        expect((await siapkan()).text()).toContain('Gagal memuat tanda motoris.');
    });
});
