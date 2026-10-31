import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import DataTable from './DataTable.vue';

const columns = { kode: 'Kode', nama_provinsi: 'Nama Provinsi', dibuat: 'Dibuat' };
const rows = [{ kode: '11', nama_provinsi: 'Aceh', dibuat: '2026-01-01' }];

function header(wrapper, kolom) {
    return wrapper.findAll('th').find((th) => th.text().includes(columns[kolom]));
}

function filterButton(wrapper, kolom) {
    return header(wrapper, kolom).find('button.filter-button');
}

describe('DataTable filter', () => {
    afterEach(() => vi.useRealTimers());

    it('renders no filter button when filterable is empty', () => {
        const wrapper = mount(DataTable, { props: { columns, rows } });

        expect(wrapper.findAll('button.filter-button')).toHaveLength(0);
    });

    it('renders a filter button only for columns in filterable', () => {
        const wrapper = mount(DataTable, { props: { columns, rows, filterable: ['kode', 'nama_provinsi'] } });

        expect(wrapper.findAll('button.filter-button')).toHaveLength(2);
        expect(filterButton(wrapper, 'dibuat').exists()).toBe(false);
    });

    it('does not show the filter input until the flyout is opened', async () => {
        const wrapper = mount(DataTable, { props: { columns, rows, filterable: ['kode'] } });

        expect(wrapper.find('.filter-flyout').exists()).toBe(false);

        await filterButton(wrapper, 'kode').trigger('click');

        expect(wrapper.find('.filter-flyout input.col-filter').exists()).toBe(true);
    });

    it('toggles the flyout closed when the same button is clicked again', async () => {
        const wrapper = mount(DataTable, { props: { columns, rows, filterable: ['kode'] } });

        await filterButton(wrapper, 'kode').trigger('click');
        await filterButton(wrapper, 'kode').trigger('click');

        expect(wrapper.find('.filter-flyout').exists()).toBe(false);
    });

    it('shows the active filter value from filterValues', async () => {
        const wrapper = mount(DataTable, {
            props: { columns, rows, filterable: ['kode'], filterValues: { kode: '11' } },
        });

        await filterButton(wrapper, 'kode').trigger('click');

        expect(wrapper.find('input.col-filter').element.value).toBe('11');
    });

    it('marks the filter button as active when the column has a value', () => {
        const wrapper = mount(DataTable, {
            props: { columns, rows, filterable: ['kode'], filterValues: { kode: '11' } },
        });

        expect(filterButton(wrapper, 'kode').classes()).toContain('filter-aktif');
    });

    it('emits all filter values once after the debounce delay', async () => {
        vi.useFakeTimers();
        const wrapper = mount(DataTable, { props: { columns, rows, filterable: ['kode', 'nama_provinsi'] } });

        await filterButton(wrapper, 'kode').trigger('click');
        await wrapper.find('input.col-filter').setValue('11');
        await filterButton(wrapper, 'nama_provinsi').trigger('click');
        await wrapper.find('input.col-filter').setValue('  Aceh ');

        expect(wrapper.emitted('filter')).toBeUndefined();

        vi.advanceTimersByTime(700);

        expect(wrapper.emitted('filter')).toEqual([[{ nilai: { kode: '11', nama_provinsi: 'Aceh' }, operator: {} }]]);
    });

    it('does not emit while the user is still typing within the delay', async () => {
        vi.useFakeTimers();
        const wrapper = mount(DataTable, { props: { columns, rows, filterable: ['kode'] } });

        await filterButton(wrapper, 'kode').trigger('click');
        const input = wrapper.find('input.col-filter');

        await input.setValue('1');
        vi.advanceTimersByTime(500);
        await input.setValue('11');
        vi.advanceTimersByTime(500);

        expect(wrapper.emitted('filter')).toBeUndefined();

        vi.advanceTimersByTime(200);

        expect(wrapper.emitted('filter')).toEqual([[{ nilai: { kode: '11' }, operator: {} }]]);
    });

    it('keeps text typed during a request when the server echoes older values', async () => {
        vi.useFakeTimers();
        const wrapper = mount(DataTable, { props: { columns, rows, filterable: ['kode'] } });

        await filterButton(wrapper, 'kode').trigger('click');
        const input = wrapper.find('input.col-filter');

        await input.setValue('1');
        vi.advanceTimersByTime(700);
        // User mengetik lagi sebelum respons request pertama datang.
        await input.setValue('11');
        await wrapper.setProps({ filterValues: { kode: '1' } });

        expect(input.element.value).toBe('11');
    });

    it('renders an operator select and emits the chosen operator for numeric columns', async () => {
        vi.useFakeTimers();
        const wrapper = mount(DataTable, {
            props: { columns, rows, filterable: ['kode'], filterAngka: ['kode'] },
        });

        await filterButton(wrapper, 'kode').trigger('click');
        await wrapper.find('select.col-filter-op').setValue('<');
        vi.advanceTimersByTime(700);

        expect(wrapper.emitted('filter')).toEqual([[{ nilai: { kode: '' }, operator: { kode: '<' } }]]);
    });

    it('uses a date input with an operator select for date columns', async () => {
        vi.useFakeTimers();
        const wrapper = mount(DataTable, {
            props: { columns, rows, filterable: ['dibuat'], filterAngka: [], filterTanggal: ['dibuat'] },
        });

        await filterButton(wrapper, 'dibuat').trigger('click');

        expect(wrapper.find('.filter-flyout input.col-filter').attributes('type')).toBe('date');
        expect(wrapper.find('select.col-filter-op').exists()).toBe(true);

        await wrapper.find('.filter-flyout input.col-filter').setValue('2026-10-23');
        vi.advanceTimersByTime(700);

        expect(wrapper.emitted('filter')).toEqual([[{ nilai: { dibuat: '2026-10-23' }, operator: { dibuat: '=' } }]]);
    });

    it('clears the column value and emits when the hapus button is clicked', async () => {
        vi.useFakeTimers();
        const wrapper = mount(DataTable, {
            props: { columns, rows, filterable: ['kode'], filterValues: { kode: '11' } },
        });

        await filterButton(wrapper, 'kode').trigger('click');
        await wrapper.find('.filter-flyout-aksi button').trigger('click');
        vi.advanceTimersByTime(700);

        expect(wrapper.find('.filter-flyout').exists()).toBe(false);
        expect(wrapper.emitted('filter')).toEqual([[{ nilai: { kode: '' }, operator: {} }]]);
    });

    it('closes the flyout when Escape is pressed', async () => {
        const wrapper = mount(DataTable, { props: { columns, rows, filterable: ['kode'] }, attachTo: document.body });

        await filterButton(wrapper, 'kode').trigger('click');
        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.filter-flyout').exists()).toBe(false);
        wrapper.unmount();
    });
});

describe('DataTable sort', () => {
    it('renders no sort button when sortable is empty', () => {
        const wrapper = mount(DataTable, { props: { columns, rows } });

        expect(wrapper.findAll('button.sort-button')).toHaveLength(0);
    });

    it('renders sort buttons only for columns listed in sortable', () => {
        const wrapper = mount(DataTable, { props: { columns, rows, sortable: ['nama_provinsi'] } });

        expect(wrapper.findAll('button.sort-button')).toHaveLength(1);
        expect(header(wrapper, 'nama_provinsi').classes()).toContain('sortable');
        expect(header(wrapper, 'kode').classes()).not.toContain('sortable');
    });

    it('cycles naik → turun → netral when the same column is clicked', async () => {
        const wrapper = mount(DataTable, { props: { columns, rows, sortable: ['nama_provinsi'] } });
        const button = wrapper.find('button.sort-button');

        await button.trigger('click');
        await wrapper.setProps({ sort: { kolom: 'nama_provinsi', arah: 'asc' } });
        await button.trigger('click');
        await wrapper.setProps({ sort: { kolom: 'nama_provinsi', arah: 'desc' } });
        await button.trigger('click');

        expect(wrapper.emitted('sort')).toEqual([
            [{ kolom: 'nama_provinsi', arah: 'asc' }],
            [{ kolom: 'nama_provinsi', arah: 'desc' }],
            [{ kolom: 'nama_provinsi', arah: null }],
        ]);
    });

    it('marks the active sort column and its direction', () => {
        const wrapper = mount(DataTable, {
            props: { columns, rows, sortable: ['nama_provinsi'], sort: { kolom: 'nama_provinsi', arah: 'asc' } },
        });

        expect(header(wrapper, 'nama_provinsi').classes()).toEqual(expect.arrayContaining(['sort-aktif', 'sort-asc']));
        expect(header(wrapper, 'nama_provinsi').attributes('aria-sort')).toBe('ascending');
    });
});

describe('DataTable baris bisa diklik', () => {
    const dua = [{ kode: '11', nama_provinsi: 'Aceh', dibuat: '2026-01-01' }, { kode: '12', nama_provinsi: 'Sumut', dibuat: '2026-01-02' }];

    it('tidak mengirim klik-baris dan tidak menandai baris bila barisBisaDiklik mati', async () => {
        const wrapper = mount(DataTable, { props: { columns, rows: dua } });

        await wrapper.find('tbody tr').trigger('click');

        expect(wrapper.emitted('klik-baris')).toBeUndefined();
        expect(wrapper.find('tbody tr').classes()).not.toContain('baris-klik');
        expect(wrapper.find('tbody tr').attributes('tabindex')).toBeUndefined();
    });

    it('mengirim klik-baris berisi data baris saat baris diklik atau Enter ditekan', async () => {
        const wrapper = mount(DataTable, { props: { columns, rows: dua, barisBisaDiklik: true } });
        const baris = wrapper.findAll('tbody tr');

        expect(baris[1].classes()).toContain('baris-klik');
        expect(baris[1].attributes('tabindex')).toBe('0');

        await baris[1].find('td').trigger('click');
        await baris[0].trigger('keydown', { key: 'Enter' });

        expect(wrapper.emitted('klik-baris')).toEqual([[dua[1]], [dua[0]]]);
    });

    it('mengabaikan klik pada tombol atau tautan di dalam baris', async () => {
        const wrapper = mount(DataTable, {
            props: { columns, rows: dua, barisBisaDiklik: true },
            slots: { 'cell-kode': '<button type="button" class="aksi-dalam">Buka</button>' },
        });

        await wrapper.find('button.aksi-dalam').trigger('click');

        expect(wrapper.emitted('klik-baris')).toBeUndefined();
    });
});
