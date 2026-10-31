import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import DatePicker from './DatePicker.vue';

const stubs = { Icon: true };

function pasang(props = {}, opsi = {}) {
    return mount(DatePicker, { props: { modelValue: '2026-10-07', ...props }, global: { stubs }, attachTo: document.body, ...opsi });
}

describe('DatePicker', () => {
    beforeEach(() => {
        vi.useFakeTimers({ toFake: ['Date'] });
        vi.setSystemTime(new Date(2026, 9, 15, 12, 0, 0)); // 15 Okt 2026
    });

    afterEach(() => {
        vi.useRealTimers();
        document.body.innerHTML = '';
    });

    it('menampilkan tanggal seperti "7 Okt 2026" dan mengosongkan kolom bila belum ada nilai', () => {
        expect(pasang().find('input').element.value).toBe('7 Okt 2026');
        expect(pasang({ modelValue: '2026-12-31' }).find('input').element.value).toBe('31 Des 2026');
        expect(pasang({ modelValue: '' }).find('input').element.value).toBe('');
    });

    it('tertutup di awal, lalu klik kolom membuka kalender bulan terpilih dengan awal minggu Senin', async () => {
        const wrapper = pasang();
        expect(wrapper.find('.datepicker-menu').exists()).toBe(false);

        await wrapper.find('input').trigger('click');

        expect(wrapper.find('.datepicker-judul').text()).toBe('Oktober 2026');
        expect(wrapper.findAll('.datepicker-hari-nama').map((n) => n.text())).toEqual(['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min']);
        // 1 Okt 2026 hari Kamis: dua sel pertama (Sen 28 Sep, Sel 29 Sep) dan Rab 30 Sep milik bulan sebelumnya.
        const sel = wrapper.findAll('.datepicker-hari');
        expect(sel).toHaveLength(42);
        expect(sel[0].attributes('data-pola')).toBe('2026-09-28');
        expect(sel[3].attributes('data-pola')).toBe('2026-10-01');
    });

    it('memilih tanggal mengirim update:modelValue berformat Y-m-d dan menutup kalender', async () => {
        const wrapper = pasang();
        await wrapper.find('input').trigger('click');

        await wrapper.find('[data-pola="2026-10-20"]').trigger('click');

        expect(wrapper.emitted('update:modelValue')[0]).toEqual(['2026-10-20']);
        expect(wrapper.find('.datepicker-menu').exists()).toBe(false);
    });

    it('menandai tanggal terpilih dan hari ini', async () => {
        const wrapper = pasang();
        await wrapper.find('input').trigger('click');

        expect(wrapper.find('[data-pola="2026-10-07"]').classes()).toContain('datepicker-hari-pilih');
        expect(wrapper.find('[data-pola="2026-10-15"]').classes()).toContain('datepicker-hari-hariini');
    });

    it('navigasi bulan, termasuk pergantian tahun', async () => {
        const wrapper = pasang({ modelValue: '2026-12-10' });
        await wrapper.find('input').trigger('click');

        await wrapper.find('[aria-label="Bulan berikutnya"]').trigger('click');
        expect(wrapper.find('.datepicker-judul').text()).toBe('Januari 2027');

        await wrapper.find('[aria-label="Bulan sebelumnya"]').trigger('click');
        await wrapper.find('[aria-label="Bulan sebelumnya"]').trigger('click');
        expect(wrapper.find('.datepicker-judul').text()).toBe('November 2026');
    });

    it('menonaktifkan tanggal di luar min/max dan tidak mengirim nilai saat diklik', async () => {
        const wrapper = pasang({ min: '2026-10-05', max: '2026-10-25' });
        await wrapper.find('input').trigger('click');

        expect(wrapper.find('[data-pola="2026-10-04"]').attributes('disabled')).toBeDefined();
        expect(wrapper.find('[data-pola="2026-10-26"]').attributes('disabled')).toBeDefined();
        expect(wrapper.find('[data-pola="2026-10-05"]').attributes('disabled')).toBeUndefined();

        await wrapper.find('[data-pola="2026-10-04"]').trigger('click');
        expect(wrapper.emitted('update:modelValue')).toBeUndefined();
    });

    it('tombol Hari ini memilih tanggal hari ini; nonaktif bila di luar rentang', async () => {
        const wrapper = pasang();
        await wrapper.find('input').trigger('click');
        await wrapper.find('.datepicker-aksi').trigger('click');
        expect(wrapper.emitted('update:modelValue')[0]).toEqual(['2026-10-15']);

        const terbatas = pasang({ max: '2026-10-10' });
        await terbatas.find('input').trigger('click');
        expect(terbatas.find('.datepicker-aksi').attributes('disabled')).toBeDefined();
    });

    it('tombol Hapus hanya ada bila clearable dan mengosongkan nilai', async () => {
        const biasa = pasang();
        await biasa.find('input').trigger('click');
        expect(biasa.findAll('.datepicker-aksi')).toHaveLength(1);

        const wrapper = pasang({ clearable: true });
        await wrapper.find('input').trigger('click');
        await wrapper.findAll('.datepicker-aksi')[1].trigger('click');
        expect(wrapper.emitted('update:modelValue')[0]).toEqual(['']);
    });

    it('Esc menutup kalender, Enter pada kolom membuka, dan klik di luar menutup', async () => {
        const wrapper = pasang();
        await wrapper.find('input').trigger('keydown', { key: 'Enter' });
        expect(wrapper.find('.datepicker-menu').exists()).toBe(true);

        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
        await wrapper.vm.$nextTick();
        expect(wrapper.find('.datepicker-menu').exists()).toBe(false);

        await wrapper.find('input').trigger('click');
        document.body.click();
        await wrapper.vm.$nextTick();
        expect(wrapper.find('.datepicker-menu').exists()).toBe(false);
    });

    it('panah menggeser fokus tanggal lintas bulan', async () => {
        const wrapper = pasang({ modelValue: '2026-10-31' });
        await wrapper.find('input').trigger('click');

        await wrapper.find('[data-pola="2026-10-31"]').trigger('keydown', { key: 'ArrowRight' });

        expect(wrapper.find('.datepicker-judul').text()).toBe('November 2026');
        expect(wrapper.find('[data-pola="2026-11-01"]').attributes('tabindex')).toBe('0');
    });

    it('tidak membuka kalender saat disabled, dan meneruskan id ke kolom serta class ke pembungkus', async () => {
        const nonaktif = pasang({ disabled: true });
        await nonaktif.find('input').trigger('click');
        expect(nonaktif.find('.datepicker-menu').exists()).toBe(false);

        const wrapper = mount(DatePicker, { props: { modelValue: '' }, attrs: { id: 'tanggal', class: 'w-40' }, global: { stubs } });
        expect(wrapper.find('input').attributes('id')).toBe('tanggal');
        expect(wrapper.classes()).toContain('w-40');
        expect(wrapper.find('input').classes()).not.toContain('w-40');
    });
});
