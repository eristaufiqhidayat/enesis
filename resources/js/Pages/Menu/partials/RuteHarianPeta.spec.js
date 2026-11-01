import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { useLeafletMap } from '@/Composables/useLeafletMap.js';
import { gambarKunjungan, gambarLompatan, gambarRute } from '@/Support/rute.js';
import RuteHarianPeta from './RuteHarianPeta.vue';

const marker = { getLatLng: vi.fn(() => ({ lat: -7.45, lng: 112.71 })), openPopup: vi.fn() };
const map = {
    invalidateSize: vi.fn(),
    fitBounds: vi.fn(),
    setView: vi.fn(),
    getZoom: vi.fn(() => 12),
};
const lapisan = { clearLayers: vi.fn(), addTo: vi.fn(() => lapisan) };
const L = { layerGroup: () => lapisan, latLngBounds: vi.fn((t) => t) };

vi.mock('@/Composables/useLeafletMap.js', () => ({ useLeafletMap: vi.fn(() => ({ peta: () => map })) }));
vi.mock('@/Support/rute.js', () => ({ gambarRute: vi.fn(), gambarKunjungan: vi.fn(), gambarLompatan: vi.fn() }));

const props = {
    orang: { nama: 'Rudi' },
    locationsUrl: '/tracking_sales_history_location/locations',
    idParam: 'sales_id',
    id: 11,
    tanggal: '2026-10-21',
};

const kunjungan = [
    { id: 1, outlet_id: 70, outlet: 'Toko Makmur', jam: '09:00', keluar: '09:15', durasi_menit: 15, lat: -7.45, lng: 112.71, beli: true, penjualan: { transaksi: 1, total: 35500, sku: 3, qty: 5 }, di_luar_rute: true, lokasi_palsu: false, outlet_ditandai: true, keterangan: null },
    { id: 2, outlet_id: null, outlet: 'Warung Bu Sri', jam: '10:30', keluar: null, durasi_menit: null, lat: null, lng: null, beli: false, di_luar_rute: false, lokasi_palsu: true, outlet_ditandai: false, keterangan: 'toko_tutup', perjalanan: { menit: 75, jarak_m: null } },
    { id: 3, outlet_id: 71, outlet: 'Kios Baru', jam: '12:00', keluar: null, durasi_menit: null, lat: -7.4, lng: 112.7, beli: true, di_luar_rute: false, lokasi_palsu: false, outlet_ditandai: false, keterangan: null, penjualan: { transaksi: 2, total: 3500, sku: 3, qty: 5.5 }, perjalanan: { menit: 12, jarak_m: 3400 } },
];

function jawab(data) {
    global.fetch = vi.fn(() => Promise.resolve({ json: () => Promise.resolve(data) }));
}

async function siapkan() {
    const wrapper = mount(RuteHarianPeta, { props });
    // Leaflet siap: komponen membuat layer group lalu memuat rute.
    useLeafletMap.mock.calls.at(-1)[1].onSiap(map, L);
    await flushPromises();

    return wrapper;
}

describe('RuteHarianPeta', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        window.L = L;
        gambarKunjungan.mockReturnValue(new Map([[1, marker]]));
    });

    it('memuat titik orang dan tanggal itu lalu menggambar rute', async () => {
        jawab({ total: 2, locations: [{ lat: '-7.1', lng: '112.1' }, { lat: '-7.2', lng: '112.2' }], depo: null });
        const wrapper = await siapkan();

        expect(fetch).toHaveBeenCalledWith('/tracking_sales_history_location/locations?sales_id=11&tanggal=2026-10-21');
        expect(gambarRute).toHaveBeenCalledWith(L, map, lapisan, expect.objectContaining({ total: 2 }), 'Rudi');
        expect(wrapper.text()).toContain('2 titik lokasi');
    });

    it('menampilkan spinner selama titik lokasi diambil lalu menggantinya dengan jumlah titik', async () => {
        jawab({ total: 2, locations: [{ lat: '-7.1', lng: '112.1' }, { lat: '-7.2', lng: '112.2' }], depo: null });
        const wrapper = mount(RuteHarianPeta, { props });

        expect(wrapper.find('.spinner').exists()).toBe(true);
        expect(wrapper.text()).toContain('Memuat titik lokasi...');

        useLeafletMap.mock.calls.at(-1)[1].onSiap(map, L);
        await flushPromises();
        expect(wrapper.find('.spinner').exists()).toBe(false);
        expect(wrapper.text()).toContain('2 titik lokasi');
    });

    it('menghentikan spinner dan menampilkan pesan bila pengambilan gagal', async () => {
        global.fetch = vi.fn(() => Promise.reject(new Error('jaringan')));
        const wrapper = await siapkan();

        expect(wrapper.find('.spinner').exists()).toBe(false);
        expect(wrapper.text()).toContain('Gagal memuat data');
    });

    it('menampilkan pesan dan mengosongkan rute bila tidak ada titik', async () => {
        jawab({ total: 0, locations: [], depo: null });
        const wrapper = await siapkan();

        expect(gambarRute).not.toHaveBeenCalled();
        expect(lapisan.clearLayers).toHaveBeenCalled();
        expect(wrapper.text()).toContain('Tidak ada data lokasi pada tanggal ini');
    });

    it('menampilkan daftar kunjungan di kiri peta dengan jam, durasi, dan lencana', async () => {
        jawab({ total: 0, locations: [], depo: null, kunjungan });
        const wrapper = await siapkan();

        expect(wrapper.find('.rute-harian').classes()).toContain('rute-harian-daftar');
        const baris = wrapper.findAll('.rute-kunjungan');
        expect(baris).toHaveLength(3);
        expect(baris[0].text()).toContain('Toko Makmur');
        expect(baris[0].text()).toContain('09:00 – 09:15 · 15 menit');
        expect(baris[0].find('.rute-kunjungan-nomor').attributes('title')).toBe('Beli');
        expect(baris[0].find('.rute-kunjungan-beli').exists()).toBe(true);
        expect(baris[0].text()).toContain('Di luar rute');
        expect(baris[1].find('.rute-kunjungan-nomor').attributes('title')).toBe('Tidak beli');
        expect(baris[1].find('.rute-kunjungan-tidak').exists()).toBe(true);
        expect(baris[1].text()).not.toContain('Tidak beli');
        expect(baris[1].text()).toContain('Lokasi palsu');
        expect(baris[1].text()).toContain('Tanpa koordinat');
        expect(baris[1].text()).toContain('toko_tutup');
        expect(gambarKunjungan).toHaveBeenCalledWith(L, lapisan, kunjungan);
        expect(map.fitBounds).toHaveBeenCalled();
    });

    it('menampilkan durasi dan jarak perjalanan di antara dua kunjungan', async () => {
        jawab({ total: 0, locations: [], depo: null, kunjungan });
        const wrapper = await siapkan();
        const penghubung = wrapper.findAll('.rute-perjalanan').map((p) => p.text());

        // Kunjungan pertama tidak punya penghubung; yang kedua tanpa jarak (tanpa koordinat); yang ketiga lengkap.
        expect(penghubung).toEqual(['↓ 1 j 15 mnt di jalan', '↓ 12 menit di jalan · 3,4 km']);
    });

    it('menampilkan nilai penjualan pada kunjungan yang beli', async () => {
        jawab({ total: 0, locations: [], depo: null, kunjungan });
        const baris = (await siapkan()).findAll('.rute-kunjungan');
        const teks = (i) => baris[i].text().replace(/ /g, ' ');

        expect(teks(0)).toContain('Rp 35.500 · 3 jenis · 5 unit');
        expect(teks(1)).not.toContain('Rp');
        expect(teks(2)).toContain('2 transaksi · Rp 3.500 · 3 jenis · 5,5 unit');
    });

    it('menampilkan strip KPI hari itu di atas daftar kunjungan', async () => {
        jawab({ total: 0, locations: [], depo: null, kunjungan });
        const wrapper = await siapkan();
        const teks = wrapper.find('.kpi-hari').text().replace(/ /g, ' ');

        // Kunjungan 1 dan 3 beli (Rp 35.500 + Rp 3.500), kunjungan 2 tidak; jam kerja dari 09:00 sampai 12:00.
        expect(wrapper.text()).toContain('Kunjungan outlet (3)');
        expect(teks).toContain('Kunjungan3');
        expect(teks).toContain('Beli2 67%');
        expect(teks).toContain('Tidak beli1');
        expect(teks).toContain('Rp 39.000');
        expect(teks).toContain('09:00–12:00');
        expect(teks).toContain('3 j');
    });

    it('tidak menampilkan strip KPI bila tidak ada kunjungan', async () => {
        jawab({ total: 0, locations: [], depo: null, kunjungan: [] });

        expect((await siapkan()).find('.kpi-hari').exists()).toBe(false);
    });

    it('menyaring daftar kunjungan tanpa mengubah nomor yang sama dengan penanda peta', async () => {
        jawab({ total: 0, locations: [], depo: null, kunjungan });
        const wrapper = await siapkan();
        const tab = (nama) => wrapper.findAll('[role="tab"]').find((t) => t.text().startsWith(nama));

        expect(tab('Semua').text()).toBe('Semua (3)');
        expect(tab('Bermasalah').text()).toBe('Bermasalah (2)');

        await tab('Tidak beli').trigger('click');
        expect(wrapper.findAll('.rute-kunjungan')).toHaveLength(1);
        expect(wrapper.find('.rute-kunjungan').text()).toContain('Warung Bu Sri');
        expect(wrapper.find('.rute-kunjungan-nomor').text()).toBe('2');
        expect(wrapper.findAll('.rute-perjalanan')).toHaveLength(0);

        await tab('Beli (').trigger('click');
        expect(wrapper.findAll('.rute-kunjungan-nomor').map((n) => n.text())).toEqual(['1', '3']);
    });

    it('menampilkan legenda peta', async () => {
        jawab({ total: 0, locations: [], depo: null, kunjungan });
        const legenda = (await siapkan()).find('.peta-legenda').text();

        expect(legenda).toContain('Beli');
        expect(legenda).toContain('Rute GPS');
        expect(legenda).toContain('Lompatan GPS');
    });

    describe('navigasi tanggal', () => {
        it('memuat hari sebelumnya dan berikutnya', async () => {
            jawab({ total: 0, locations: [], depo: null });
            const wrapper = await siapkan();

            await wrapper.find('[aria-label="Hari sebelumnya"]').trigger('click');
            await flushPromises();
            expect(fetch).toHaveBeenLastCalledWith('/tracking_sales_history_location/locations?sales_id=11&tanggal=2026-10-20');

            await wrapper.find('[aria-label="Hari berikutnya"]').trigger('click');
            await flushPromises();
            expect(fetch).toHaveBeenLastCalledWith('/tracking_sales_history_location/locations?sales_id=11&tanggal=2026-10-21');
        });

        it('menonaktifkan hari berikutnya bila tanggalnya hari ini', async () => {
            jawab({ total: 0, locations: [], depo: null });
            const hariIni = new Date();
            const pola = `${hariIni.getFullYear()}-${String(hariIni.getMonth() + 1).padStart(2, '0')}-${String(hariIni.getDate()).padStart(2, '0')}`;
            const wrapper = mount(RuteHarianPeta, { props: { ...props, tanggal: pola } });
            useLeafletMap.mock.calls.at(-1)[1].onSiap(map, L);
            await flushPromises();

            expect(wrapper.find('[aria-label="Hari berikutnya"]').attributes('disabled')).toBeDefined();
            expect(wrapper.find('[aria-label="Hari sebelumnya"]').attributes('disabled')).toBeUndefined();
        });
    });

    it('menampilkan pesan tidak ada kunjungan bila daftarnya kosong', async () => {
        jawab({ total: 0, locations: [], depo: null, kunjungan: [] });

        expect((await siapkan()).text()).toContain('Kunjungan outlet (0) · Tidak ada kunjungan outlet pada tanggal ini.');
    });

    describe('tanda motoris', () => {
        const stub = { TandaMotoris: { props: ['url', 'salesId', 'tanggal'], template: '<div class="stub-tanda">{{ url }}|{{ salesId }}|{{ tanggal }}</div>' } };

        async function dengan(lainnya) {
            const wrapper = mount(RuteHarianPeta, { props: { ...props, ...lainnya }, global: { stubs: stub } });
            useLeafletMap.mock.calls.at(-1)[1].onSiap(map, L);
            await flushPromises();

            return wrapper;
        }

        it('menampilkan tanda motoris di header drawer bila tandaUrl diisi (Sales)', async () => {
            jawab({ total: 0, locations: [], depo: null, kunjungan });
            const wrapper = await dengan({ tandaUrl: '/tracking_sales/tanda' });

            expect(wrapper.find('.stub-tanda').text()).toBe('/tracking_sales/tanda|11|2026-10-21');
        });

        it('mengikuti tanggal yang dipilih di drawer', async () => {
            jawab({ total: 0, locations: [], depo: null, kunjungan });
            const wrapper = await dengan({ tandaUrl: '/tracking_sales/tanda' });
            wrapper.findComponent({ name: 'DatePicker' }).vm.$emit('update:modelValue', '2026-10-22');
            await flushPromises();

            expect(wrapper.find('.stub-tanda').text()).toBe('/tracking_sales/tanda|11|2026-10-22');
        });

        it('berpindah ke tanggal yang dipilih dari daftar tanda di tanggal lain lalu memuat ulang rute', async () => {
            jawab({ total: 0, locations: [], depo: null, kunjungan });
            const pindah = { TandaMotoris: { props: ['url', 'salesId', 'tanggal'], emits: ['pilih-tanggal'], template: '<button class="pindah" @click="$emit(\'pilih-tanggal\', \'2026-10-14\')">{{ tanggal }}</button>' } };
            const wrapper = mount(RuteHarianPeta, { props: { ...props, tandaUrl: '/tracking_sales/tanda' }, global: { stubs: pindah } });
            useLeafletMap.mock.calls.at(-1)[1].onSiap(map, L);
            await flushPromises();

            await wrapper.find('.pindah').trigger('click');
            await flushPromises();

            expect(fetch.mock.calls.at(-1)[0]).toContain('tanggal=2026-10-14');
            expect(wrapper.find('.pindah').text()).toBe('2026-10-14');
        });

        it('tidak menampilkan tanda motoris tanpa tandaUrl', async () => {
            jawab({ total: 0, locations: [], depo: null, kunjungan });

            expect((await dengan({})).find('.stub-tanda').exists()).toBe(false);
        });
    });

    describe('tanda outlet', () => {
        const stubTandaOutlet = { TandaOutlet: { props: ['url', 'outletId'], template: '<div class="stub-tanda-outlet">{{ url }}|{{ outletId }}</div>' } };

        async function dengan() {
            jawab({ total: 0, locations: [], depo: null, kunjungan });
            const wrapper = mount(RuteHarianPeta, { props, global: { stubs: stubTandaOutlet } });
            useLeafletMap.mock.calls.at(-1)[1].onSiap(map, L);
            await flushPromises();

            return wrapper;
        }

        it('tombol Tandai outlet hanya tampil pada kunjungan yang punya outlet_id', async () => {
            const wrapper = await dengan();
            const tombol = wrapper.findAll('.tombol-tanda-outlet');

            // Kunjungan 1 dan 3 punya outlet_id; kunjungan 2 (outlet baru belum disetujui) tidak.
            expect(tombol).toHaveLength(2);
        });

        it('membedakan tombol bendera outlet yang sudah ditandai dari yang belum', async () => {
            jawab({ total: 0, locations: [], depo: null, kunjungan });
            const tombol = (await siapkan()).findAll('.tombol-tanda-outlet');

            // Kunjungan 1 ditandai, kunjungan 3 belum.
            expect(tombol[0].classes()).toContain('tombol-tanda-ditandai');
            expect(tombol[0].attributes('aria-label')).toBe('Lihat tanda outlet');
            expect(tombol[1].classes()).not.toContain('tombol-tanda-ditandai');
            expect(tombol[1].classes()).toContain('tombol-tanda-belum');
            expect(tombol[1].attributes('aria-label')).toBe('Tandai outlet');
        });

        it('mengklik tombol Tandai outlet membuka drawer dengan outlet yang tepat', async () => {
            const wrapper = await dengan();
            expect(wrapper.find('.stub-tanda-outlet').exists()).toBe(false);

            await wrapper.findAll('.tombol-tanda-outlet')[0].trigger('click');

            expect(wrapper.find('.stub-tanda-outlet').text()).toBe('/outlet_all/tanda|70');
            expect(wrapper.text()).toContain('Tanda Outlet · Toko Makmur');
        });
    });

    describe('indikasi jarak dekat antar kunjungan', () => {
        const runtun1 = {
            jumlah: 2, kunjungan_id: [1, 3], outlet_id: [70, 71], dari_jam: '09:00', sampai_jam: '12:00',
            saran: { kategori: 'pola_kunjungan_tidak_wajar', catatan: '2 kunjungan outlet berturutan berjarak kurang dari 5 m satu sama lain, dari jam 09:00 sampai 12:00.' },
        };
        const runtun2 = {
            jumlah: 6, kunjungan_id: [10, 11, 12, 13, 14, 15], outlet_id: [80, 81], dari_jam: '13:04', sampai_jam: '13:17',
            saran: { kategori: 'pola_kunjungan_tidak_wajar', catatan: '6 kunjungan outlet berturutan berjarak kurang dari 5 m satu sama lain, dari jam 13:04 sampai 13:17.' },
        };
        const stub = { TandaOutlet: { props: ['url', 'outletId', 'saran'], template: '<div class="stub-tanda-outlet">{{ saran ? saran.kategori + "|" + saran.catatan : "tanpa-saran" }}</div>' } };

        async function dengan(indikasi) {
            jawab({ total: 0, locations: [], depo: null, kunjungan, indikasi_jarak_kunjungan: indikasi });
            const wrapper = mount(RuteHarianPeta, { props, global: { stubs: stub } });
            useLeafletMap.mock.calls.at(-1)[1].onSiap(map, L);
            await flushPromises();

            return wrapper;
        }

        const buka = async (wrapper) => { await wrapper.find('.temuan-tombol').trigger('click'); return wrapper; };

        it('merangkum semua indikasi di tombol Temuan, panelnya tertutup secara bawaan', async () => {
            const wrapper = await dengan([runtun1]);

            // Jarak dekat + kunjungan ke outlet ditandai + di luar rute + tanpa koordinat dari fixture.
            expect(wrapper.find('.temuan-tombol').text()).toContain('4 temuan');
            expect(wrapper.find('.temuan-tombol').classes()).toContain('temuan-tombol-bahaya');
            expect(wrapper.find('.temuan-panel').exists()).toBe(false);
        });

        it('membuka panel berisi catatan runtun jarak dekat dan penegasan bukan bukti kecurangan', async () => {
            const panel = (await buka(await dengan([runtun1]))).find('.temuan-panel');

            expect(panel.text()).toContain('Kunjungan berturutan berjarak dekat');
            expect(panel.text()).toContain('2 kunjungan outlet berturutan berjarak kurang dari 5 m');
            expect(panel.text()).toContain('Ini indikasi dari pola data, bukan bukti kecurangan.');
        });

        it('menampilkan "Tidak ada temuan" setelah data dimuat bila bersih', async () => {
            jawab({ total: 0, locations: [], depo: null, kunjungan: [], indikasi_jarak_kunjungan: [] });
            const wrapper = await siapkan();

            expect(wrapper.find('.temuan-tombol').text()).toContain('Tidak ada temuan');
            expect(wrapper.find('.temuan-tombol-bersih').exists()).toBe(true);
        });

        it('menyorot kunjungan terkait dan memperbesar peta saat temuan diklik', async () => {
            const wrapper = await buka(await dengan([runtun1]));
            map.fitBounds.mockClear();

            const item = wrapper.findAll('.temuan-item').find((i) => i.text().includes('berjarak dekat'));
            await item.trigger('click');

            const sorot = wrapper.findAll('.rute-kunjungan-sorot');
            expect(sorot).toHaveLength(2);
            expect(sorot[0].text()).toContain('Toko Makmur');
            expect(sorot[1].text()).toContain('Kios Baru');
            expect(map.fitBounds).toHaveBeenCalledWith(expect.anything(), expect.objectContaining({ maxZoom: 18 }));
        });

        it('menggulir daftar ke kunjungan pertama yang ditunjuk temuan', async () => {
            const gulir = vi.fn();
            Element.prototype.scrollIntoView = gulir;
            const wrapper = await buka(await dengan([runtun1]));

            await wrapper.findAll('.temuan-item').find((i) => i.text().includes('berjarak dekat')).trigger('click');
            await flushPromises();

            expect(gulir).toHaveBeenCalledTimes(1);
            expect(gulir.mock.contexts[0].dataset.kunjunganId).toBe('1');
            expect(gulir).toHaveBeenCalledWith(expect.objectContaining({ block: 'center' }));
        });

        it('memperlihatkan indikasi GPS tidak wajar sebagai temuan', async () => {
            const anomali = { tingkat: 'indikasi', titik_mock: 0, kunjungan_mock: 0, lompatan: 20, kembali_ke_asal: 11, terjauh: { km: 10.5, menit: 1, kmh: 627, jam: '10:17' }, segmen: [], menyimpang: 0 };
            jawab({ total: 0, locations: [], depo: null, kunjungan: [], anomali_gps: anomali });
            const wrapper = await buka(await siapkan());

            expect(wrapper.find('.temuan-panel').text()).toContain('20 lompatan posisi mustahil');
        });

        it('memberi lencana "Jarak dekat" pada baris kunjungan yang termasuk runtun', async () => {
            const baris = (await dengan([runtun1])).findAll('.rute-kunjungan');

            expect(baris[0].text()).toContain('Jarak dekat');
            expect(baris[1].text()).not.toContain('Jarak dekat');
            expect(baris[2].text()).toContain('Jarak dekat');
        });

        it('mengisi saran Tandai outlet untuk outlet dalam runtun, kosong untuk outlet di luar runtun', async () => {
            // Kunjungan 1 -> outlet 70 (dalam runtun); tambahkan outlet di luar runtun untuk dibandingkan.
            const diluarRuntun = kunjungan.map((k) => (k.id === 3 ? { ...k, outlet_id: 99 } : k));
            jawab({ total: 0, locations: [], depo: null, kunjungan: diluarRuntun, indikasi_jarak_kunjungan: [runtun1] });
            const wrapper = mount(RuteHarianPeta, { props, global: { stubs: stub } });
            useLeafletMap.mock.calls.at(-1)[1].onSiap(map, L);
            await flushPromises();

            await wrapper.findAll('.tombol-tanda-outlet')[0].trigger('click');
            expect(wrapper.find('.stub-tanda-outlet').text()).toContain('pola_kunjungan_tidak_wajar');

            await wrapper.findAll('.tombol-tanda-outlet')[1].trigger('click');
            expect(wrapper.find('.stub-tanda-outlet').text()).toBe('tanpa-saran');
        });
    });

    describe('saran dari indikasi GPS tidak wajar', () => {
        const segmen = [
            { dari: { lat: -6.6, lng: 106.8, waktu: '2026-10-22 10:17:28' }, ke: { lat: -6.5, lng: 106.85, waktu: '2026-10-22 10:18:28' }, km: 10.5, menit: 1, kmh: 627, kembali: false },
        ];
        const indikasi = { tingkat: 'indikasi', lompatan: 20, kembali_ke_asal: 11, titik_mock: 0, kunjungan_mock: 0, terjauh: { km: 10.5, menit: 1, kmh: 627, jam: '10:17' }, segmen };
        const stub = { TandaMotoris: { props: ['url', 'salesId', 'tanggal', 'saran'], template: '<div class="stub-tanda">{{ saran ? saran.kategori + "|" + saran.catatan : "tanpa-saran" }}</div>' } };

        async function dengan(anomali, lainnya = { tandaUrl: '/tracking_sales/tanda' }) {
            jawab({ total: 0, locations: [], depo: null, kunjungan, anomali_gps: anomali });
            const wrapper = mount(RuteHarianPeta, { props: { ...props, ...lainnya }, global: { stubs: stub } });
            useLeafletMap.mock.calls.at(-1)[1].onSiap(map, L);
            await flushPromises();

            return wrapper;
        }

        it('mengisi form Tandai motoris dengan kategori lokasi palsu dan ringkasan peringatan', async () => {
            const teks = (await dengan(indikasi)).find('.stub-tanda').text();

            expect(teks).toContain('lokasi_palsu|Indikasi GPS tidak wajar. 20 lompatan posisi mustahil, 11 di antaranya langsung kembali');
        });

        it('menambahkan kalimat titik GPS menyimpang ke catatan tanda dan memakai titiknya untuk digambar', async () => {
            const titik = [{ lat: -6.55, lng: 106.8, waktu: '2026-10-22 14:09:06', km: 5.7 }];
            const wrapper = await dengan({
                ...indikasi, lompatan: 0, kembali_ke_asal: 0, terjauh: null, segmen: [],
                menyimpang: 3, menyimpang_terjauh: { km: 8.4, jam: '15:00' }, titik_menyimpang: titik,
            });

            expect(wrapper.find('.stub-tanda').text()).toContain('3 titik GPS menyimpang jauh');
            expect(gambarLompatan).toHaveBeenLastCalledWith(L, lapisan, [], titik);
        });

        it('judul "lokasi palsu terdeteksi" dipakai sebagai kategori catatan bila HP menandai mock location', async () => {
            const teks = (await dengan({ ...indikasi, tingkat: 'terdeteksi', titik_mock: 3, kunjungan_mock: 1 })).find('.stub-tanda').text();

            expect(teks).toContain('Lokasi palsu terdeteksi oleh aplikasi HP. Aplikasi HP menandai lokasi palsu (mock location) pada 3 titik GPS dan 1 kunjungan.');
        });

        it('tanpa peringatan form tidak diberi isian otomatis', async () => {
            expect((await dengan(null)).find('.stub-tanda').text()).toBe('tanpa-saran');
        });

        it('menggambar lompatan di peta dan mengosongkannya bila tidak ada anomali', async () => {
            await dengan(indikasi);
            expect(gambarLompatan).toHaveBeenLastCalledWith(L, lapisan, segmen, []);

            await dengan(null);
            expect(gambarLompatan).toHaveBeenLastCalledWith(L, lapisan, [], []);
        });

        it('saran ikut berubah ketika tanggal diganti ke hari tanpa anomali', async () => {
            const wrapper = await dengan(indikasi);
            expect(wrapper.find('.stub-tanda').text()).not.toBe('tanpa-saran');

            jawab({ total: 0, locations: [], depo: null, kunjungan, anomali_gps: null });
            wrapper.findComponent({ name: 'DatePicker' }).vm.$emit('update:modelValue', '2026-10-22');
            await flushPromises();

            expect(wrapper.find('.stub-tanda').text()).toBe('tanpa-saran');
        });
    });

    it('menyembunyikan kolom daftar bila respons tanpa data kunjungan (koordinator)', async () => {
        jawab({ total: 1, locations: [{ lat: '-7.1', lng: '112.1' }], depo: null });
        const wrapper = await siapkan();

        expect(wrapper.find('.rute-harian-kolom').exists()).toBe(false);
        expect(wrapper.find('.rute-harian').classes()).not.toContain('rute-harian-daftar');
    });

    it('memusatkan peta ke penanda dan membuka popup saat baris diklik', async () => {
        jawab({ total: 0, locations: [], depo: null, kunjungan });
        const wrapper = await siapkan();
        await wrapper.findAll('.rute-kunjungan')[0].trigger('click');

        expect(map.setView).toHaveBeenCalledWith({ lat: -7.45, lng: 112.71 }, 16);
        expect(marker.openPopup).toHaveBeenCalled();
        expect(wrapper.findAll('.rute-kunjungan')[0].classes()).toContain('rute-kunjungan-aktif');
    });

    it('baris tanpa koordinat ditandai aktif tanpa menggeser peta', async () => {
        jawab({ total: 0, locations: [], depo: null, kunjungan });
        const wrapper = await siapkan();
        await wrapper.findAll('.rute-kunjungan')[1].trigger('click');

        expect(map.setView).not.toHaveBeenCalled();
        expect(wrapper.findAll('.rute-kunjungan')[1].classes()).toContain('rute-kunjungan-aktif');
    });
});
