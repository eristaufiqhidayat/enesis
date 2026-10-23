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

    it('renders nothing when the slug is not found', () => {
        const wrapper = mount(Breadcrumb, { props: { menu, currentSlug: 'unknown' } });

        expect(wrapper.find('nav').exists()).toBe(false);
    });
});
