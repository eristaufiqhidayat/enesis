import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Sidebar from './Sidebar.vue';


const menu = [
    {
        label: null,
        items: [{ id: 1, slug: 'dashboard', title: 'Dashboard', icon: 'home' }],
    },
    {
        label: 'Master Data',
        items: [
            { id: 2, slug: 'outlet', title: 'Outlet', icon: 'store' },
            { id: 3, slug: 'area', title: 'Area', icon: 'map' },
            {
                id: 4,
                title: 'Laporan',
                icon: 'file-text',
                children: [
                    { slug: 'laporan-harian', title: 'Laporan Harian', icon: null },
                    {
                        id: 5,
                        title: 'Laporan Bulanan',
                        icon: null,
                        children: [{ slug: 'laporan-bulanan', title: 'Laporan Bulanan Detail', icon: null }],
                    },
                ],
            },
            {
                id: 6,
                title: 'Pengaturan',
                icon: 'settings',
                children: [{ slug: 'pengaturan-umum', title: 'Umum', icon: null }],
            },
        ],
    },
];

let currentSlug;
let searchMenu;
const routerVisit = vi.fn();

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({
        props: {
            menu,
            get currentSlug() { return currentSlug; },
            get searchMenu() { return searchMenu; },
            appName: 'Enesis Canvassing',
        },
    }),
    Link: { template: '<a><slot /></a>' },
    router: { visit: (...args) => routerVisit(...args) },
}));

describe('Sidebar full collapse', () => {
    const FULL_KEY = 'sidebar-collapsed-full';

    beforeEach(() => {
        localStorage.clear();
        document.body.classList.remove('sidebar-collapsed');
        currentSlug = 'dashboard';
        Element.prototype.scrollIntoView = vi.fn();
    });

    it('does not throw and stays expanded when localStorage has never been written', () => {
        expect(() => mount(Sidebar)).not.toThrow();
        expect(document.body.classList.contains('sidebar-collapsed')).toBe(false);
    });

    it('reads the collapsed flag from localStorage on mount', async () => {
        localStorage.setItem(FULL_KEY, '1');

        mount(Sidebar);
        await nextTick();

        expect(document.body.classList.contains('sidebar-collapsed')).toBe(true);
    });

    it('toggles body class and persists flag when the brand toggle is clicked', async () => {
        const wrapper = mount(Sidebar);

        await wrapper.find('[data-sidebar-toggle]').trigger('click');
        expect(document.body.classList.contains('sidebar-collapsed')).toBe(true);
        expect(localStorage.getItem(FULL_KEY)).toBe('1');

        await wrapper.find('[data-sidebar-toggle]').trigger('click');
        expect(document.body.classList.contains('sidebar-collapsed')).toBe(false);
        expect(localStorage.getItem(FULL_KEY)).toBeNull();
    });

    it('keeps the sidebar collapsed when a submenu is clicked', async () => {
        localStorage.setItem(FULL_KEY, '1');
        const wrapper = mount(Sidebar);
        await nextTick();

        const submenuToggle = wrapper.findAll('a.nav-link').find((a) => a.text().includes('Laporan'));
        await submenuToggle.trigger('click');

        expect(document.body.classList.contains('sidebar-collapsed')).toBe(true);
        expect(localStorage.getItem(FULL_KEY)).toBe('1');
    });

    it('does not show a native tooltip on hover for a nav link', () => {
        const wrapper = mount(Sidebar);

        const outlet = wrapper.findAll('a.nav-link').find((a) => a.text().includes('Outlet'));
        expect(outlet.attributes('title')).toBeUndefined();
    });
});

describe('Sidebar search', () => {
    beforeEach(() => {
        localStorage.clear();
        currentSlug = 'dashboard';
        routerVisit.mockClear();
        // Entri tambahan untuk tab halaman gabungan (lihat Akses::menuPencarian), bukan menu nav biasa.
        searchMenu = [
            { title: 'Dashboard', slug: 'dashboard' },
            { title: 'Monitoring Harian Sales', slug: 'monitoring_harian_sales' },
            { title: 'Sales Tidak Penjualan', slug: 'monitoring_harian_sales?tab=sales_tidak_penjualan' },
        ];
    });

    it('finds a menu that only exists as a tab inside a merged page', async () => {
        const wrapper = mount(Sidebar);

        const input = wrapper.find('[data-sidebar-search]');
        await input.setValue('Sales Tidak Penjualan');
        await input.trigger('focus');

        const result = wrapper.findAll('.sidebar-search-results li').find((li) => li.text() === 'Sales Tidak Penjualan');
        expect(result).toBeTruthy();
        expect(result.find('a').attributes('href')).toBe('/monitoring_harian_sales?tab=sales_tidak_penjualan');
    });

    it('navigates to the merged page with the right tab when the result is opened via Enter', async () => {
        const wrapper = mount(Sidebar);

        const input = wrapper.find('[data-sidebar-search]');
        await input.setValue('Sales Tidak Penjualan');
        await input.trigger('focus');
        await input.trigger('keydown', { key: 'Enter' });

        expect(routerVisit).toHaveBeenCalledWith('/monitoring_harian_sales?tab=sales_tidak_penjualan');
    });
});

describe('Sidebar submenu flyout (desktop)', () => {
    const flyoutItem = (wrapper, title) => wrapper.findAll('li.has-flyout').find((li) => li.find('.nav-link-text').text() === title);
    const isOpen = (li) => li.classes().includes('is-flyout-open');
    const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

    beforeEach(() => {
        localStorage.clear();
        document.body.classList.remove('sidebar-collapsed');
        currentSlug = 'dashboard';
        Element.prototype.scrollIntoView = vi.fn();
        window.matchMedia = vi.fn(() => ({ matches: true, addEventListener() {}, removeEventListener() {} }));
    });

    it('opens the flyout on hover without needing a click', async () => {
        const wrapper = mount(Sidebar);
        const laporan = flyoutItem(wrapper, 'Laporan');

        await laporan.trigger('mouseenter');

        expect(isOpen(laporan)).toBe(true);
        expect(laporan.find('[data-flyout-panel]').exists()).toBe(true);
    });

    it('opens the flyout on click too', async () => {
        const wrapper = mount(Sidebar);
        const laporan = flyoutItem(wrapper, 'Laporan');

        await laporan.find('[data-flyout-toggle]').trigger('click');

        expect(isOpen(laporan)).toBe(true);
    });

    it('keeps only one flyout open per level', async () => {
        const wrapper = mount(Sidebar);
        const laporan = flyoutItem(wrapper, 'Laporan');
        const pengaturan = flyoutItem(wrapper, 'Pengaturan');

        await laporan.trigger('mouseenter');
        await pengaturan.trigger('mouseenter');

        expect(isOpen(laporan)).toBe(false);
        expect(isOpen(pengaturan)).toBe(true);
    });

    it('closes the flyout on Escape', async () => {
        const wrapper = mount(Sidebar);
        const laporan = flyoutItem(wrapper, 'Laporan');
        await laporan.trigger('mouseenter');

        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
        await nextTick();

        expect(isOpen(laporan)).toBe(false);
    });

    it('closes the flyout when clicking outside the item and its panel', async () => {
        const wrapper = mount(Sidebar);
        const laporan = flyoutItem(wrapper, 'Laporan');
        await laporan.find('[data-flyout-toggle]').trigger('click');

        document.body.click();
        await nextTick();

        expect(isOpen(laporan)).toBe(false);
    });

    it('closes the flyout after the pointer leaves the item and panel', async () => {
        const wrapper = mount(Sidebar);
        const laporan = flyoutItem(wrapper, 'Laporan');
        await laporan.trigger('mouseenter');

        await laporan.trigger('mouseleave');
        expect(isOpen(laporan)).toBe(true);

        await wait(200);
        expect(isOpen(laporan)).toBe(false);
    });

    it('keeps the flyout open when the pointer returns before the delay ends', async () => {
        const wrapper = mount(Sidebar);
        const laporan = flyoutItem(wrapper, 'Laporan');
        await laporan.trigger('mouseenter');

        await laporan.trigger('mouseleave');
        await laporan.trigger('mouseenter');
        await wait(200);

        expect(isOpen(laporan)).toBe(true);
    });

    it('marks a parent with an active descendant even while its flyout is closed', () => {
        currentSlug = 'laporan-harian';
        const wrapper = mount(Sidebar);
        const laporan = flyoutItem(wrapper, 'Laporan');

        expect(isOpen(laporan)).toBe(false);
        expect(laporan.find('[data-flyout-toggle]').classes()).toContain('has-active-child');
    });

    it('opens a nested flyout to the right of its parent flyout', async () => {
        const wrapper = mount(Sidebar);
        const laporan = flyoutItem(wrapper, 'Laporan');
        await laporan.trigger('mouseenter');

        const bulanan = laporan.findAll('li.has-flyout').find((li) => li.find('.nav-link-text').text() === 'Laporan Bulanan');
        await bulanan.trigger('mouseenter');

        expect(isOpen(bulanan)).toBe(true);
        expect(isOpen(laporan)).toBe(true);
    });

    it('opens the flyout while the sidebar stays collapsed', async () => {
        localStorage.setItem('sidebar-collapsed-full', '1');
        const wrapper = mount(Sidebar);
        await nextTick();

        const laporan = flyoutItem(wrapper, 'Laporan');
        await laporan.find('[data-flyout-toggle]').trigger('click');

        expect(isOpen(laporan)).toBe(true);
        expect(document.body.classList.contains('sidebar-collapsed')).toBe(true);
    });
});

describe('Sidebar submenu accordion (mobile)', () => {
    beforeEach(() => {
        localStorage.clear();
        currentSlug = 'dashboard';
        window.matchMedia = vi.fn(() => ({ matches: false, addEventListener() {}, removeEventListener() {} }));
    });

    it('toggles the submenu inline on click instead of opening a flyout', async () => {
        const wrapper = mount(Sidebar);
        const laporan = wrapper.findAll('li.has-flyout').find((li) => li.find('.nav-link-text').text() === 'Laporan');
        const panel = laporan.find('[data-flyout-panel]');

        await laporan.find('[data-flyout-toggle]').trigger('click');
        expect(panel.classes()).toContain('is-open');
        expect(laporan.classes()).not.toContain('is-flyout-open');

        await laporan.find('[data-flyout-toggle]').trigger('click');
        expect(panel.classes()).not.toContain('is-open');
    });

    it('does not open a flyout on hover', async () => {
        const wrapper = mount(Sidebar);
        const laporan = wrapper.findAll('li.has-flyout').find((li) => li.find('.nav-link-text').text() === 'Laporan');

        await laporan.trigger('mouseenter');

        expect(laporan.classes()).not.toContain('is-flyout-open');
    });
});

describe('Sidebar group flyout', () => {
    const groupItem = (wrapper) => wrapper.findAll('li.section-group').find((li) => li.text().includes('Master Data'));
    const isOpen = (li) => li.classes().includes('is-flyout-open');

    beforeEach(() => {
        localStorage.clear();
        currentSlug = 'dashboard';
        window.matchMedia = vi.fn(() => ({ matches: true, addEventListener() {}, removeEventListener() {} }));
    });

    it('renders the group header as a flyout toggle whose panel holds the group items', () => {
        const wrapper = mount(Sidebar);
        const group = groupItem(wrapper);

        expect(group.find('[data-flyout-toggle]').classes()).toContain('section-label');
        expect(group.find('[data-flyout-panel]').text()).toContain('Outlet');
    });

    it('opens the group flyout on hover and on click', async () => {
        const wrapper = mount(Sidebar);
        const group = groupItem(wrapper);

        await group.trigger('mouseenter');
        expect(isOpen(group)).toBe(true);

        await group.trigger('mouseleave');
        await group.find('[data-flyout-toggle]').trigger('click');
        expect(isOpen(group)).toBe(true);
    });

    it('no longer collapses a group in place or stores collapsed groups', async () => {
        const wrapper = mount(Sidebar);

        await wrapper.find('[data-flyout-toggle]').trigger('click');

        expect(localStorage.getItem('sidebar-collapsed-groups')).toBeNull();
        expect(wrapper.find('[data-toggle-all-groups]').exists()).toBe(false);
    });

    it('closes a nested flyout when its parent flyout closes', async () => {
        const wrapper = mount(Sidebar);
        const laporan = wrapper.findAll('li.has-flyout').find((li) => li.find('.nav-link-text').text() === 'Laporan');
        await laporan.trigger('mouseenter');
        const bulanan = laporan.findAll('li.has-flyout').find((li) => li.find('.nav-link-text').text() === 'Laporan Bulanan');
        await bulanan.trigger('mouseenter');

        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
        await nextTick();

        expect(isOpen(laporan)).toBe(false);
        expect(isOpen(bulanan)).toBe(false);
    });
});
