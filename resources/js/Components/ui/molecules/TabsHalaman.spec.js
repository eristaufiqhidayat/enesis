import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import TabsHalaman from './TabsHalaman.vue';

let props;
const visit = vi.fn();

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({ get props() { return props; } }),
    router: { visit: (...args) => visit(...args) },
}));

describe('TabsHalaman', () => {
    beforeEach(() => {
        visit.mockClear();
        props = {
            currentSlug: 'tracking_sales',
            tabHalaman: [
                { slug: 'tracking_sales', label: 'Last Seen' },
                { slug: 'tracking_sales_history_location', label: 'Riwayat Lokasi' },
            ],
        };
    });

    it('merender tab dan menandai halaman aktif', () => {
        const tabs = mount(TabsHalaman).findAll('[role="tab"]');
        expect(tabs.map((t) => t.text())).toEqual(['Last Seen', 'Riwayat Lokasi']);
        expect(tabs[0].attributes('aria-selected')).toBe('true');
        expect(tabs[1].attributes('aria-selected')).toBe('false');
    });

    it('pindah ke halaman tab yang dipilih', async () => {
        await mount(TabsHalaman).findAll('[role="tab"]')[1].trigger('click');
        expect(visit).toHaveBeenCalledWith('/tracking_sales_history_location');
    });

    it('tidak berpindah saat tab aktif diklik', async () => {
        await mount(TabsHalaman).findAll('[role="tab"]')[0].trigger('click');
        expect(visit).not.toHaveBeenCalled();
    });

    it('tidak merender apa pun bila tidak ada tab', () => {
        props.tabHalaman = [];
        expect(mount(TabsHalaman).find('[role="tablist"]').exists()).toBe(false);
    });
});
