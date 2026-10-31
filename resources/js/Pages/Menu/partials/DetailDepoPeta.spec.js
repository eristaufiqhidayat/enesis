import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { useLeafletMap } from '@/Composables/useLeafletMap.js';
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

    it('puts the motoris marker with an open popup showing the rincian', () => {
        const penanda = { addTo: vi.fn(() => penanda), bindPopup: vi.fn(() => penanda), openPopup: vi.fn() };
        const L = { marker: vi.fn(() => penanda), divIcon: vi.fn(() => ({})) };
        window.L = L; // ikonLingkaran membuat ikon lewat window.L
        mount(DetailDepoPeta, {
            props: { depo: { nama: 'Rudi', latitude: -7.2, longitude: 112.2 }, ikon: 'motoris', rincian: 'Terakhir terlihat 2026-10-07 11:00:00' },
        });
        useLeafletMap.mock.calls.at(-1)[1].onSiap({ invalidateSize: vi.fn() }, L);

        expect(L.marker).toHaveBeenCalledWith([-7.2, 112.2], expect.any(Object));
        expect(penanda.bindPopup.mock.calls[0][0]).toContain('Rudi');
        expect(penanda.bindPopup.mock.calls[0][0]).toContain('Terakhir terlihat 2026-10-07 11:00:00');
        expect(penanda.openPopup).toHaveBeenCalled();
        delete window.L;
    });
});
