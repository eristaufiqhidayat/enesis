import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import PasswordInput from './PasswordInput.vue';

describe('PasswordInput', () => {
    it('renders a hidden password input and passes attributes to it', () => {
        const wrapper = mount(PasswordInput, {
            attrs: { id: 'password', autocomplete: 'current-password' },
            props: { modelValue: 'rahasia' },
        });

        const input = wrapper.find('input');
        expect(input.attributes('type')).toBe('password');
        expect(input.attributes('id')).toBe('password');
        expect(input.attributes('autocomplete')).toBe('current-password');
        expect(input.element.value).toBe('rahasia');
    });

    it('toggles visibility with the eye button', async () => {
        const wrapper = mount(PasswordInput, { attrs: { id: 'password' } });
        const tombol = wrapper.find('button.password-toggle');

        expect(tombol.attributes('aria-label')).toBe('Tampilkan password');

        await tombol.trigger('click');

        expect(wrapper.find('input').attributes('type')).toBe('text');
        expect(tombol.attributes('aria-label')).toBe('Sembunyikan password');
    });

    it('emits update:modelValue when the user types', async () => {
        const wrapper = mount(PasswordInput, { attrs: { id: 'password' }, props: { modelValue: '' } });

        await wrapper.find('input').setValue('baru-12345');

        expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['baru-12345']);
    });
});
