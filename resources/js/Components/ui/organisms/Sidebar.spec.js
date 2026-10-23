import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Sidebar from './Sidebar.vue';

const STORAGE_KEY = 'sidebar-collapsed-groups';

const menu = [
    {
        label: null,
        items: [{ id: 1, slug: 'dashboard', title: 'Dashboard', icon: 'fas fa-home' }],
    },
    {
        label: 'Master Data',
        items: [
            { id: 2, slug: 'outlet', title: 'Outlet', icon: 'fas fa-store' },
            { id: 3, slug: 'area', title: 'Area', icon: 'fas fa-map' },
            {
                id: 4,
                title: 'Laporan',
                icon: 'fas fa-file',
                children: [{ slug: 'laporan-harian', title: 'Laporan Harian', icon: null }],
            },
        ],
    },
];

let currentSlug;

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({ props: { menu, get currentSlug() { return currentSlug; }, appName: 'Enesis Canvassing' } }),
    Link: { template: '<a><slot /></a>' },
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

    it('keeps a group open when it contains the current slug, even if collapsed in localStorage', async () => {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(['Master Data']));
        currentSlug = 'outlet';

        const wrapper = mount(Sidebar);
        await wrapper.vm.$nextTick();
        const groupList = wrapper.findAll('ul:not(.nav)').find((ul) => ul.text().includes('Outlet'));

        expect(groupList.classes()).not.toContain('hidden');
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
