import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import SidebarUserMenu from './SidebarUserMenu.vue';

let auth;
let currentSlug;
const routerPost = vi.fn();

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({
        props: {
            get auth() { return auth; },
            get currentSlug() { return currentSlug; },
        },
    }),
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
    router: { post: (...args) => routerPost(...args) },
}));

const items = [
    { slug: 'panduan', title: 'Panduan', icon: 'info' },
    { slug: 'pengumuman', title: 'Pengumuman', icon: 'megaphone' },
    {
        id: 'konfigurasi_group',
        title: 'Konfigurasi',
        icon: 'settings',
        children: [
            { slug: 'master_area', title: 'Master Area', icon: 'map-pin' },
            { slug: 'master_depo', title: 'Master Warehouse / Depo', icon: 'building' },
        ],
    },
];

let wrappers = [];

function mountMenu(props = { items }) {
    const wrapper = mount(SidebarUserMenu, { props });
    wrappers.push(wrapper);
    return wrapper;
}

const popup = (wrapper) => wrapper.find('.sidebar-user-popup');
const linkByText = (wrapper, text) => wrapper.findAll('.sidebar-user-popup-list a').find((a) => a.text() === text);

beforeEach(() => {
    auth = { user: { name: 'Admin Utama', username: 'admin', role: 'Super Admin' } };
    currentSlug = 'dashboard';
    routerPost.mockClear();
});

afterEach(() => {
    wrappers.forEach((wrapper) => wrapper.unmount());
    wrappers = [];
});

describe('SidebarUserMenu tombol', () => {
    it('shows the user name and role on the footer button', () => {
        const wrapper = mountMenu();

        const btn = wrapper.find('.sidebar-user-btn');
        expect(btn.find('.sidebar-user-name').text()).toBe('Admin Utama');
        expect(btn.find('.sidebar-user-role').text()).toBe('Super Admin');
    });

    it('renders nothing when there is no logged-in user', () => {
        auth = { user: null };

        const wrapper = mountMenu();

        expect(wrapper.find('.sidebar-user-menu').exists()).toBe(false);
    });

    it('highlights the button while the current page belongs to one of the group entries', () => {
        currentSlug = 'master_depo';

        const wrapper = mountMenu();

        expect(wrapper.find('.sidebar-user-btn').classes()).toContain('is-active');
        expect(linkByText(wrapper, 'Konfigurasi').classes()).toContain('is-active');
    });

    it('does not highlight the button when the current page is outside the footer menu', () => {
        const wrapper = mountMenu();

        expect(wrapper.find('.sidebar-user-btn').classes()).not.toContain('is-active');
    });

    it('keeps the button highlighted while the popup is open', async () => {
        currentSlug = 'master_depo';

        const wrapper = mountMenu();
        await wrapper.find('.sidebar-user-btn').trigger('click');

        expect(popup(wrapper).classes()).not.toContain('hidden');
        expect(wrapper.find('.sidebar-user-btn').classes()).toContain('is-active');
    });

    it('keeps name and role in the popup header, so the collapsed button can show only the avatar', () => {
        const wrapper = mountMenu();

        const header = wrapper.find('.sidebar-user-popup-header');
        expect(header.find('.sidebar-user-name').text()).toBe('Admin Utama');
        expect(header.find('.sidebar-user-role').text()).toBe('Super Admin');
    });
});

describe('SidebarUserMenu tooltip (sidebar dipersempit)', () => {
    const tooltip = () => document.body.querySelector('[data-tooltip]');

    beforeEach(() => {
        window.matchMedia = vi.fn(() => ({ matches: true, addEventListener() {}, removeEventListener() {} }));
        document.body.classList.add('sidebar-collapsed');
    });

    afterEach(() => {
        document.body.classList.remove('sidebar-collapsed');
    });

    it('shows the user name as a tooltip on hover when the sidebar is collapsed', async () => {
        const wrapper = mountMenu();

        await wrapper.find('.sidebar-user-btn').trigger('mouseenter');

        expect(tooltip().dataset.tooltip).toBe('Admin Utama');
    });

    it('hides the tooltip on mouse leave', async () => {
        const wrapper = mountMenu();
        await wrapper.find('.sidebar-user-btn').trigger('mouseenter');

        await wrapper.find('.sidebar-user-btn').trigger('mouseleave');

        expect(tooltip()).toBeNull();
    });

    it('does not show the tooltip when the sidebar is not collapsed', async () => {
        document.body.classList.remove('sidebar-collapsed');
        const wrapper = mountMenu();

        await wrapper.find('.sidebar-user-btn').trigger('mouseenter');

        expect(tooltip()).toBeNull();
    });

    it('does not show the tooltip while the popup is open', async () => {
        const wrapper = mountMenu();
        await wrapper.find('.sidebar-user-btn').trigger('click');

        await wrapper.find('.sidebar-user-btn').trigger('mouseenter');

        expect(tooltip()).toBeNull();
    });
});

describe('SidebarUserMenu popup', () => {
    it('starts closed and opens on button click', async () => {
        const wrapper = mountMenu();
        expect(popup(wrapper).classes()).toContain('hidden');

        await wrapper.find('.sidebar-user-btn').trigger('click');

        expect(popup(wrapper).classes()).not.toContain('hidden');
        expect(wrapper.find('.sidebar-user-btn').attributes('aria-expanded')).toBe('true');
    });

    it('closes when clicking outside the menu', async () => {
        const wrapper = mountMenu();
        await wrapper.find('.sidebar-user-btn').trigger('click');

        document.body.click();
        await wrapper.vm.$nextTick();

        expect(popup(wrapper).classes()).toContain('hidden');
    });

    it('lists the entries from items in order, followed by Ganti Password and Keluar', () => {
        const wrapper = mountMenu();

        const labels = wrapper.findAll('.sidebar-user-popup-list li').map((li) => li.text());
        expect(labels).toEqual(['Panduan', 'Pengumuman', 'Konfigurasi', 'Ganti Password', 'Keluar']);
    });

    it('points a group entry to the first child allowed for the role', () => {
        const wrapper = mountMenu();

        expect(linkByText(wrapper, 'Konfigurasi').attributes('href')).toBe('/master_area');
        expect(linkByText(wrapper, 'Panduan').attributes('href')).toBe('/panduan');
    });

    it('links Ganti Password to its page', () => {
        const wrapper = mountMenu();

        expect(linkByText(wrapper, 'Ganti Password').attributes('href')).toBe('/ganti-password');
    });

    it('logs out with a POST to /logout when Keluar is clicked', async () => {
        const wrapper = mountMenu();
        await wrapper.find('.sidebar-user-btn').trigger('click');

        const keluar = wrapper.findAll('.sidebar-user-popup-list button').find((b) => b.text() === 'Keluar');
        await keluar.trigger('click');

        expect(routerPost).toHaveBeenCalledWith('/logout');
    });
});
