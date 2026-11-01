import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it } from 'vitest';
import { KUNCI_DETEKSI } from '@/Support/deteksiKunjungan.js';
import PengaturanDeteksiMotoris from './PengaturanDeteksiMotoris.vue';

const pasang = () => mount(PengaturanDeteksiMotoris, { attachTo: document.body });
const buka = async (wrapper) => { await wrapper.find('.tanda-chip').trigger('click'); return wrapper; };

describe('PengaturanDeteksiMotoris', () => {
    afterEach(() => {
        localStorage.clear();
        document.body.innerHTML = '';
    });

    it('menampilkan ambang yang berlaku di tombol: bawaan 11 m dan 3 kunjungan', () => {
        const wrapper = pasang();

        expect(wrapper.find('.tanda-chip').text()).toContain('Ambang: 11 m · 3 kunjungan');
        expect(wrapper.find('form').exists()).toBe(false);
    });

    it('menampilkan ambang tersimpan di tombol', () => {
        localStorage.setItem(KUNCI_DETEKSI, JSON.stringify({ jarak_dekat_meter: 25, min_kunjungan_berturutan: 6 }));

        expect(pasang().find('.tanda-chip').text()).toContain('Ambang: 25 m · 6 kunjungan');
    });

    it('membuka form berisi nilai berlaku, menyimpan ke localStorage, memberi tahu induk, dan menutup form', async () => {
        const wrapper = await buka(pasang());
        expect(wrapper.find('#deteksi_jarak').element.value).toBe('11');
        expect(wrapper.find('#deteksi_min').element.value).toBe('3');

        await wrapper.find('#deteksi_jarak').setValue('20');
        await wrapper.find('#deteksi_min').setValue('5');
        await wrapper.find('form').trigger('submit');

        expect(JSON.parse(localStorage.getItem(KUNCI_DETEKSI))).toEqual({ jarak_dekat_meter: 20, min_kunjungan_berturutan: 5 });
        expect(wrapper.emitted('berubah')).toHaveLength(1);
        expect(wrapper.find('form').exists()).toBe(false);
        expect(wrapper.find('.tanda-chip').text()).toContain('Ambang: 20 m · 5 kunjungan');
    });

    it('menolak nilai tidak sah: pesan galat, Simpan nonaktif, tidak menyimpan dan tidak memberi tahu induk', async () => {
        const wrapper = await buka(pasang());
        await wrapper.find('#deteksi_jarak').setValue('0');
        await wrapper.find('#deteksi_min').setValue('1');
        await wrapper.find('form').trigger('submit');

        expect(wrapper.text()).toContain('Isi bilangan bulat 1–10000 meter.');
        expect(wrapper.text()).toContain('Isi bilangan bulat 2–100 kunjungan.');
        expect(wrapper.find('button[type="submit"]').attributes('disabled')).toBeDefined();
        expect(localStorage.getItem(KUNCI_DETEKSI)).toBeNull();
        expect(wrapper.emitted('berubah')).toBeUndefined();
    });

    it('kembalikan ke bawaan menghapus nilai tersimpan dan memberi tahu induk', async () => {
        localStorage.setItem(KUNCI_DETEKSI, JSON.stringify({ jarak_dekat_meter: 25, min_kunjungan_berturutan: 6 }));
        const wrapper = await buka(pasang());
        await wrapper.findAll('button').find((b) => b.text() === 'Kembalikan ke bawaan').trigger('click');

        expect(localStorage.getItem(KUNCI_DETEKSI)).toBeNull();
        expect(wrapper.emitted('berubah')).toHaveLength(1);
        expect(wrapper.find('.tanda-chip').text()).toContain('Ambang: 11 m · 3 kunjungan');
    });

    it('klik di luar menutup form walau elemen yang diklik menghentikan propagasi (mis. peta)', async () => {
        const wrapper = await buka(pasang());
        const peta = document.createElement('div');
        peta.addEventListener('click', (e) => e.stopPropagation());
        document.body.append(peta);

        peta.dispatchEvent(new MouseEvent('click', { bubbles: true }));
        await wrapper.vm.$nextTick();

        expect(wrapper.find('form').exists()).toBe(false);
    });
});
