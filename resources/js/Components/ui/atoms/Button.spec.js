import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import Button from './Button.vue';

describe('Button', () => {
    it('renders as a button by default with btn classes', () => {
        const wrapper = mount(Button, {
            props: { variant: 'primary', size: 'md' },
            slots: { default: 'Simpan' },
        });

        expect(wrapper.element.tagName).toBe('BUTTON');
        expect(wrapper.classes()).toContain('btn-primary');
        expect(wrapper.classes()).toContain('btn-md');
        expect(wrapper.text()).toBe('Simpan');
    });

    it('shows a spinner in place of the label and disables itself while loading', () => {
        const wrapper = mount(Button, { props: { loading: true }, slots: { default: 'Tampilkan' } });

        expect(wrapper.attributes('disabled')).toBeDefined();
        expect(wrapper.attributes('aria-busy')).toBe('true');
        expect(wrapper.find('.btn-spinner').exists()).toBe(true);
        expect(wrapper.find('.btn-label').classes()).toContain('btn-label-hidden');
    });

    it('is disabled without a spinner when only disabled', () => {
        const wrapper = mount(Button, { props: { disabled: true }, slots: { default: 'Tampilkan' } });

        expect(wrapper.attributes('disabled')).toBeDefined();
        expect(wrapper.find('.btn-spinner').exists()).toBe(false);
    });

    it('renders as the given tag without a type attribute', () => {
        const wrapper = mount(Button, {
            props: { tag: 'a' },
        });

        expect(wrapper.element.tagName).toBe('A');
        expect(wrapper.attributes('type')).toBeUndefined();
    });
});
