import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import SettingsSideNav from './SettingsSideNav.vue';

let currentSlug;

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({
        props: {
            get currentSlug() { return currentSlug; },
            menu,
        },
    }),
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
}));

const menu = [
    {
        label: 'Master Data & Pengaturan',
        posisi: 'footer',
        items: [
            { slug: 'panduan', title: 'Panduan' },
            { slug: 'pengumuman', title: 'Pengumuman' },
            {
                title: 'Manajemen Produk',
                children: [
                    { slug: 'produk', title: 'Produk' },
                    { slug: 'kategori_produk', title: 'Kategori Produk' },
                ],
            },
            {
                title: 'Konfigurasi',
                children: [
                    { slug: 'master_area', title: 'Master Area' },
                    { slug: 'master_depo', title: 'Master Warehouse / Depo' },
                ],
            },
            {
                title: 'Master Data',
                children: [{ slug: 'outlet', title: 'Outlet' }],
            },
        ],
    },
];

describe('SettingsSideNav', () => {
    it('shows the panel with the group title and its children for a page inside a sub-group', () => {
        currentSlug = 'master_area';
        const wrapper = mount(SettingsSideNav);

        expect(wrapper.find('.settings-side-nav-title').text()).toBe('Konfigurasi');
        expect(wrapper.findAll('.settings-side-nav-link').map((a) => a.text())).toEqual([
            'Master Area',
            'Master Warehouse / Depo',
        ]);
    });

    it('lists only the children of the sub-group, not sibling sub-groups', () => {
        currentSlug = 'produk';
        const wrapper = mount(SettingsSideNav);

        expect(wrapper.text()).not.toContain('Master Area');
        expect(wrapper.text()).toContain('Kategori Produk');
    });

    it('highlights the active child and links every child to its slug', () => {
        currentSlug = 'master_depo';
        const wrapper = mount(SettingsSideNav);

        const active = wrapper.find('.settings-side-nav-link.is-active');
        expect(active.text()).toBe('Master Warehouse / Depo');
        expect(active.attributes('href')).toBe('/master_depo');
        expect(wrapper.find('a[href="/master_area"]').classes()).not.toContain('is-active');
    });

    it('renders nothing for the Panduan and Pengumuman pages', () => {
        currentSlug = 'panduan';
        expect(mount(SettingsSideNav).find('nav').exists()).toBe(false);

        currentSlug = 'pengumuman';
        expect(mount(SettingsSideNav).find('nav').exists()).toBe(false);
    });

    it('renders nothing for child pages that are not menu items, such as add forms or the area designer', () => {
        currentSlug = 'master_area/design_area';
        expect(mount(SettingsSideNav).find('nav').exists()).toBe(false);

        currentSlug = 'master_depo/add';
        expect(mount(SettingsSideNav).find('nav').exists()).toBe(false);
    });

    it('renders nothing for pages outside the footer group', () => {
        currentSlug = 'dashboard';
        expect(mount(SettingsSideNav).find('nav').exists()).toBe(false);
    });
});
