import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import DetailDepoEdit from './DetailDepoEdit.vue';

const depo = { nama: 'Depo Jakarta Selatan', kota_besar: 'Jakarta', kuota_sales_motoris: 40, minimal_value_transaksi: null };

describe('DetailDepoEdit', () => {
    it('prefills the inputs with the depo values', () => {
        const wrapper = mount(DetailDepoEdit, { props: { depo } });

        expect(wrapper.find('#edit-nama').element.value).toBe('Depo Jakarta Selatan');
        expect(wrapper.find('#edit-kuota_sales_motoris').element.value).toBe('40');
        expect(wrapper.find('#edit-minimal_value_transaksi').element.value).toBe('');
    });

    it('keeps saving disabled because persistence is not connected yet', () => {
        const wrapper = mount(DetailDepoEdit, { props: { depo } });
        const simpan = wrapper.findAll('button').find((b) => b.text() === 'Simpan');

        expect(simpan.attributes('disabled')).toBeDefined();
        expect(wrapper.text()).toContain('Penyimpanan belum tersedia');
    });

    it('emits batal when Batal is clicked', async () => {
        const wrapper = mount(DetailDepoEdit, { props: { depo } });

        await wrapper.findAll('button').find((b) => b.text() === 'Batal').trigger('click');

        expect(wrapper.emitted('batal')).toHaveLength(1);
    });
});
