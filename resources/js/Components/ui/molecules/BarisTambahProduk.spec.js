import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import BarisTambahProduk from './BarisTambahProduk.vue';

const options = [{ value: 3, label: 'SKU-3 · Adem Sari' }, { value: 4, label: 'SKU-4 · Plossa' }];
const stubs = { Icon: true };

function pasang(props = {}) {
    return mount(BarisTambahProduk, { props: { options, ...props }, global: { stubs }, attachTo: document.body });
}

async function pilihProduk(wrapper, label) {
    await wrapper.find('input[type="text"]').trigger('click');
    const pilihan = wrapper.findAll('.combobox-option').find((o) => o.text().includes(label));
    await pilihan.trigger('click');
}

describe('BarisTambahProduk', () => {
    it('menolak tambah tanpa produk atau dengan qty tidak positif, dan tidak mengirim apa pun', async () => {
        const wrapper = pasang();

        await wrapper.findAll('button').find((b) => b.text().includes('Tambah')).trigger('click');
        expect(wrapper.text()).toContain('Pilih produk dulu.');

        await pilihProduk(wrapper, 'Plossa');
        await wrapper.find('input[type="number"]').setValue('0');
        await wrapper.findAll('button').find((b) => b.text().includes('Tambah')).trigger('click');

        expect(wrapper.text()).toContain('Qty bawa harus lebih dari 0.');
        expect(wrapper.emitted('tambah')).toBeUndefined();
    });

    it('mengirim tambah berisi produk_id dan qty_bawa lalu mengosongkan isian (Enter di qty juga menambah)', async () => {
        const wrapper = pasang();

        await pilihProduk(wrapper, 'Adem Sari');
        await wrapper.find('input[type="number"]').setValue('2.5');
        await wrapper.find('input[type="number"]').trigger('keydown', { key: 'Enter' });

        expect(wrapper.emitted('tambah')).toEqual([[{ produk_id: 3, qty_bawa: '2.5' }]]);
        expect(wrapper.find('input[type="number"]').element.value).toBe('');
        expect(wrapper.text()).not.toContain('Pilih produk dulu.');
    });

    it('menonaktifkan isian dan tombol saat disabled', () => {
        const wrapper = pasang({ disabled: true });

        expect(wrapper.find('input[type="number"]').attributes('disabled')).toBeDefined();
        expect(wrapper.findAll('button').find((b) => b.text().includes('Tambah')).attributes('disabled')).toBeDefined();
    });
});
