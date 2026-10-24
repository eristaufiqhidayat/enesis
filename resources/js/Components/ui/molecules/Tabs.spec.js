import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import Tabs from './Tabs.vue';

const tabs = [
    { key: 'checkin_pagi', label: 'Sales Checkin Outlet Pagi' },
    { key: 'sales_tidak_absen', label: 'Sales Tidak Absen Pagi' },
];

describe('Tabs', () => {
    it('renders one tab button per item with its label', () => {
        const wrapper = mount(Tabs, { props: { tabs, modelValue: 'checkin_pagi' } });
        const buttons = wrapper.findAll('[role="tab"]');

        expect(buttons).toHaveLength(2);
        expect(buttons[1].text()).toBe('Sales Tidak Absen Pagi');
    });

    it('marks only the tab matching modelValue as selected', () => {
        const wrapper = mount(Tabs, { props: { tabs, modelValue: 'sales_tidak_absen' } });
        const buttons = wrapper.findAll('[role="tab"]');

        expect(buttons[0].attributes('aria-selected')).toBe('false');
        expect(buttons[1].attributes('aria-selected')).toBe('true');
    });

    it('emits update:modelValue with the tab key when a tab is clicked', async () => {
        const wrapper = mount(Tabs, { props: { tabs, modelValue: 'checkin_pagi' } });

        await wrapper.findAll('[role="tab"]')[1].trigger('click');

        expect(wrapper.emitted('update:modelValue')).toEqual([['sales_tidak_absen']]);
    });
});
