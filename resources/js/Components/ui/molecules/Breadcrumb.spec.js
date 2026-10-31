import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import Breadcrumb from './Breadcrumb.vue';

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({ props: {} }),
}));

const menu = [
    {
        label: 'Master Data',
        items: [
            { title: 'Outlet', slug: 'outlet' },
            {
                title: 'Area',
                children: [{ title: 'Desain Area', slug: 'area-design' }],
            },
        ],
    },
];

describe('Breadcrumb', () => {
    it('renders the trail for a top-level menu item', () => {
        const wrapper = mount(Breadcrumb, { props: { menu, currentSlug: 'outlet' } });

        expect(wrapper.text()).toBe('Master Data/Outlet');
    });

    it('renders the trail for a nested child item', () => {
        const wrapper = mount(Breadcrumb, { props: { menu, currentSlug: 'area-design' } });

        expect(wrapper.text()).toBe('Master Data/Area/Desain Area');
    });

    it('renders the trail of a merged item for its sibling page (serumpun)', () => {
        const gabungan = [{ label: 'Canvassing', items: [{ title: 'Lacak Motoris', slug: 'tracking_sales', serumpun: ['tracking_sales_history_location'] }] }];
        const wrapper = mount(Breadcrumb, { props: { menu: gabungan, currentSlug: 'tracking_sales_history_location' } });

        expect(wrapper.text()).toBe('Canvassing/Lacak Motoris');
    });

    it('renders nothing when the slug is not found', () => {
        const wrapper = mount(Breadcrumb, { props: { menu, currentSlug: 'unknown' } });

        expect(wrapper.find('nav').exists()).toBe(false);
    });
});
