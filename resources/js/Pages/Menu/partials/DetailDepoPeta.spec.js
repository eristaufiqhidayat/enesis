import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import DetailDepoPeta from './DetailDepoPeta.vue';

vi.mock('@/Composables/useLeafletMap.js', () => ({ useLeafletMap: vi.fn(() => ({ peta: () => null })) }));

describe('DetailDepoPeta', () => {
    it('renders the map container when latitude and longitude are filled', () => {
        const wrapper = mount(DetailDepoPeta, { props: { depo: { nama: 'Depo A', latitude: -6.2, longitude: 106.8 } } });

        expect(wrapper.find('.detail-depo-peta').exists()).toBe(true);
        expect(wrapper.text()).not.toContain('Koordinat belum diisi.');
    });

    it('shows a note instead of a map when coordinates are missing', () => {
        const wrapper = mount(DetailDepoPeta, { props: { depo: { nama: 'Depo A', latitude: null, longitude: '' } } });

        expect(wrapper.find('.detail-depo-peta').exists()).toBe(false);
        expect(wrapper.text()).toContain('Koordinat belum diisi.');
    });
});
