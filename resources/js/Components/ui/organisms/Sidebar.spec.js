import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Sidebar from './Sidebar.vue';

const STORAGE_KEY = 'sidebar-collapsed-groups';

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
                children: [{ slug: 'laporan-harian', title: 'Laporan Harian', icon: null }],
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

describe('Sidebar group collapse', () => {
    beforeEach(() => {
        localStorage.clear();
        currentSlug = 'dashboard';
        Element.prototype.scrollIntoView = vi.fn();
    });

    it('keeps the first (unlabelled) group always open with no toggle', () => {
        const wrapper = mount(Sidebar);
        const sectionLabels = wrapper.findAll('.section-label');

        expect(sectionLabels).toHaveLength(1);
        expect(sectionLabels[0].text()).toContain('Master Data');
    });

    it('shows a labelled group open by default when localStorage is empty', async () => {
        const wrapper = mount(Sidebar);
        await wrapper.vm.$nextTick();

        const groupList = wrapper.findAll('ul:not(.nav)').find((ul) => ul.text().includes('Outlet'));

        expect(groupList.classes()).not.toContain('hidden');
    });

    it('collapses a labelled group when clicked and persists it to localStorage', async () => {
        const wrapper = mount(Sidebar);
        const toggle = wrapper.find('.section-label');

        await toggle.trigger('click');

        expect(JSON.parse(localStorage.getItem(STORAGE_KEY))).toEqual(['Master Data']);
    });

    it('reads the collapsed state back out of localStorage on mount', async () => {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(['Master Data']));

        const wrapper = mount(Sidebar);
        await wrapper.vm.$nextTick();
        const groupList = wrapper.findAll('ul:not(.nav)').find((ul) => ul.text().includes('Outlet'));

        expect(groupList.classes()).toContain('hidden');
    });

    it('keeps a group collapsed when the user collapsed it, even if it contains the current slug', async () => {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(['Master Data']));
        currentSlug = 'outlet';

        const wrapper = mount(Sidebar);
        await wrapper.vm.$nextTick();
        const groupList = wrapper.findAll('ul:not(.nav)').find((ul) => ul.text().includes('Outlet'));

        expect(groupList.classes()).toContain('hidden');
        expect(JSON.parse(localStorage.getItem(STORAGE_KEY))).toEqual(['Master Data']);
    });

    it('toggles the persisted state based on the stored flag, not the forced-open active-slug view', async () => {
        currentSlug = 'outlet';
        const wrapper = mount(Sidebar);
        const toggle = wrapper.find('.section-label');

        await toggle.trigger('click');
        expect(JSON.parse(localStorage.getItem(STORAGE_KEY))).toEqual(['Master Data']);

        await toggle.trigger('click');
        expect(JSON.parse(localStorage.getItem(STORAGE_KEY))).toEqual([]);
    });

    it('scrolls the clicked group header into view at the top', async () => {
        const wrapper = mount(Sidebar);
        const toggle = wrapper.find('.section-label');

        await toggle.trigger('click');

        expect(toggle.element.scrollIntoView).toHaveBeenCalledWith({ behavior: 'smooth', block: 'start' });
    });

    it('does not throw when localStorage has never been written', () => {
        expect(() => mount(Sidebar)).not.toThrow();
    });
});

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

    it('expands the sidebar before toggling a submenu when collapsed', async () => {
        localStorage.setItem(FULL_KEY, '1');
        const wrapper = mount(Sidebar);
        await nextTick();

        const submenuToggle = wrapper.findAll('a.nav-link').find((a) => a.text().includes('Laporan'));
        await submenuToggle.trigger('click');

        expect(document.body.classList.contains('sidebar-collapsed')).toBe(false);
        expect(localStorage.getItem(FULL_KEY)).toBeNull();
    });

    it('exposes the item title as a native tooltip on each nav link', () => {
        const wrapper = mount(Sidebar);

        const outlet = wrapper.findAll('a.nav-link').find((a) => a.text().includes('Outlet'));
        expect(outlet.attributes('title')).toBe('Outlet');
    });
});

describe('Sidebar toggle all groups', () => {
    const groupList = (wrapper) => wrapper.findAll('ul:not(.nav)').find((ul) => ul.text().includes('Outlet'));

    beforeEach(() => {
        localStorage.clear();
        document.body.classList.remove('sidebar-collapsed');
        currentSlug = 'dashboard';
        Element.prototype.scrollIntoView = vi.fn();
    });

    it('renders the toggle-all button in .brand next to the full-collapse toggle', () => {
        const wrapper = mount(Sidebar);
        const brand = wrapper.find('.brand');

        expect(brand.find('[data-toggle-all-groups]').exists()).toBe(true);
        expect(brand.find('[data-sidebar-toggle]').exists()).toBe(true);
    });

    it('collapses every labelled group when all are open', async () => {
        const wrapper = mount(Sidebar);

        await wrapper.find('[data-toggle-all-groups]').trigger('click');

        expect(JSON.parse(localStorage.getItem(STORAGE_KEY))).toEqual(['Master Data']);
        expect(groupList(wrapper).classes()).toContain('hidden');
    });

    it('expands every group when at least one is collapsed', async () => {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(['Master Data']));
        const wrapper = mount(Sidebar);
        await nextTick();

        await wrapper.find('[data-toggle-all-groups]').trigger('click');

        expect(JSON.parse(localStorage.getItem(STORAGE_KEY))).toEqual([]);
        expect(groupList(wrapper).classes()).not.toContain('hidden');
    });

    it('leaves the group that contains the current slug out of the collapsed set', async () => {
        currentSlug = 'outlet';
        const wrapper = mount(Sidebar);

        await wrapper.find('[data-toggle-all-groups]').trigger('click');

        expect(JSON.parse(localStorage.getItem(STORAGE_KEY))).toEqual([]);
        expect(groupList(wrapper).classes()).not.toContain('hidden');
    });

    it('lets the user collapse the group that contains the current slug by hand', async () => {
        currentSlug = 'outlet';
        const wrapper = mount(Sidebar);

        await wrapper.find('.section-label').trigger('click');

        expect(JSON.parse(localStorage.getItem(STORAGE_KEY))).toEqual(['Master Data']);
        expect(groupList(wrapper).classes()).toContain('hidden');
    });

    it('keeps the group that contains the current slug collapsed after a full page load', async () => {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(['Master Data']));
        currentSlug = 'outlet';

        const wrapper = mount(Sidebar);
        await nextTick();

        expect(JSON.parse(localStorage.getItem(STORAGE_KEY))).toEqual(['Master Data']);
        expect(groupList(wrapper).classes()).toContain('hidden');
    });

    it('flips the button direction after a single group is toggled by hand', async () => {
        const wrapper = mount(Sidebar);
        const button = wrapper.find('[data-toggle-all-groups]');

        await wrapper.find('.section-label').trigger('click');

        expect(button.attributes('aria-label')).toBe('Buka semua grup');
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
