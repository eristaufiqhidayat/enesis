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

    it('renders as the given tag without a type attribute', () => {
        const wrapper = mount(Button, {
            props: { tag: 'a' },
        });

        expect(wrapper.element.tagName).toBe('A');
        expect(wrapper.attributes('type')).toBeUndefined();
    });
});
