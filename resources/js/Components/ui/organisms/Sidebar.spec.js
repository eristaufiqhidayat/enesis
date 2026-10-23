import { mount } from '@vue/test-utils';
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

        expect(wrapper.find('ul.hidden').exists()).toBe(false);
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
