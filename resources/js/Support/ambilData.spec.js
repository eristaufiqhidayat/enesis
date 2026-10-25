import { describe, expect, it, vi } from 'vitest';
import { ref } from 'vue';

const get = vi.fn();
vi.mock('@inertiajs/vue3', () => ({ router: { get: (...args) => get(...args) } }));

const { useAmbilData } = await import('./ambilData.js');

describe('useAmbilData', () => {
    it('aktif hanya saat isian berbeda dari filter yang diterapkan', () => {
        const isian = ref({ bulan: '2026-10', depo: ['2', '1'] });
        const { berubah } = useAmbilData({ form: () => isian.value, diterapkan: () => ({ bulan: '2026-10', depo: [1, 2] }) });

        expect(berubah.value).toBe(false);

        isian.value = { bulan: '2026-09', depo: ['2', '1'] };
        expect(berubah.value).toBe(true);
    });

    it('menyalakan spinner selama request dan mematikannya saat selesai', () => {
        const { memuat, ambil } = useAmbilData({ form: () => ({}), diterapkan: () => ({}) });

        ambil('/x', {}, { only: ['a'] });
        const opsi = get.mock.calls[0][2];
        expect(opsi.only).toEqual(['a']);

        opsi.onStart();
        expect(memuat.value).toBe(true);
        opsi.onFinish();
        expect(memuat.value).toBe(false);
    });
});
