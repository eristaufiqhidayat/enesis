import { describe, expect, it, vi } from 'vitest';
import { gambarKunjungan, gambarLompatan, gambarRute } from './rute.js';

function buatL() {
    const dibuat = [];
    const L = {
        divIcon: vi.fn((opsi) => opsi),
        marker: vi.fn((ll, opsi) => {
            const marker = { ll, opsi, popup: null, addTo: vi.fn(), bindPopup: vi.fn((html) => { marker.popup = html; return marker; }) };
            dibuat.push(marker);

            return marker;
        }),
    };

    return { L, dibuat };
}

const lapisan = () => ({ clearLayers: vi.fn() });

describe('gambarKunjungan', () => {
    const kunjungan = [
        { id: 7, outlet: 'Toko <b>Makmur</b>', jam: '09:00', keluar: '09:15', durasi_menit: 15, lat: -7.45, lng: 112.71, beli: true, keterangan: null },
        { id: 8, outlet: 'Warung Bu Sri', jam: '10:30', keluar: null, durasi_menit: null, lat: null, lng: null, beli: false, keterangan: 'toko_tutup' },
        { id: 9, outlet: 'Kios Baru', jam: '11:00', keluar: null, durasi_menit: null, lat: -7.4, lng: 112.7, beli: false, keterangan: null },
    ];

    it('menggambar penanda bernomor sesuai urutan daftar, hanya yang punya koordinat', () => {
        const { L, dibuat } = buatL();
        const hasil = gambarKunjungan(L, lapisan(), kunjungan);

        expect(dibuat).toHaveLength(2);
        expect([...hasil.keys()]).toEqual([7, 9]);
        // Nomor mengikuti posisi di daftar (kunjungan tanpa koordinat tetap memakai nomornya).
        expect(dibuat.map((m) => m.opsi.icon.html.replace(/<[^>]+>/g, ''))).toEqual(['1', '3']);
    });

    it('mewarnai penanda menurut hasil kunjungan', () => {
        const { L, dibuat } = buatL();
        gambarKunjungan(L, lapisan(), kunjungan);

        expect(dibuat[0].opsi.icon.html).toContain('penanda-kunjungan-beli');
        expect(dibuat[1].opsi.icon.html).toContain('penanda-kunjungan-tidak');
    });

    it('memusatkan angka lewat elemen dalam; elemen luar milik Leaflet tidak diberi kelas tampilan', () => {
        const { L, dibuat } = buatL();
        gambarKunjungan(L, lapisan(), kunjungan);
        const { icon } = dibuat[0].opsi;

        expect(icon.className).toBe('');
        expect(icon.iconSize).toEqual([26, 26]);
        expect(icon.html).toMatch(/^<span class="penanda-kunjungan-isi [^"]+">1<\/span>$/);
    });

    describe('popover', () => {
        const lengkap = {
            id: 3, outlet: 'Kios Baru', jam: '11:00', keluar: '11:20', durasi_menit: 20, lat: -7.4, lng: 112.7, beli: false,
            keterangan: 'Toko tutup', di_luar_rute: true, lokasi_palsu: true, jarak_m: 850, perjalanan: { menit: 65, jarak_m: 3400 },
        };

        function popup(data, indeks = 0) {
            const { L, dibuat } = buatL();
            gambarKunjungan(L, lapisan(), [data]);

            return dibuat[indeks].popup;
        }

        it('memuat semua info kunjungan yang tersedia (tanpa koordinat)', () => {
            const html = popup(lengkap);

            expect(html).toContain('Kios Baru');
            expect(html).toContain('11:00 – 11:20 (20 menit)');
            expect(html).toContain('Tidak beli: Toko tutup');
            expect(html).toContain('Di luar rute');
            expect(html).toContain('Lokasi palsu');
            expect(html).toContain('Dari kunjungan sebelumnya: 1 j 5 mnt · 3,4 km');
            expect(html).toContain('Jarak ke toko saat checkin: 850 m');
            expect(html).not.toContain('Lat:');
        });

        it('menampilkan nomor sebagai lingkaran berwarna sama dengan penanda di peta', () => {
            const { L, dibuat } = buatL();
            gambarKunjungan(L, lapisan(), [{ ...lengkap, id: 1, beli: true }, { ...lengkap, id: 2, beli: false }]);

            expect(dibuat[0].popup).toContain('<span class="rute-kunjungan-nomor rute-kunjungan-beli">1</span>');
            expect(dibuat[1].popup).toContain('<span class="rute-kunjungan-nomor rute-kunjungan-tidak">2</span>');
            // Warna lingkaran popover = warna penanda: kelas warna yang sama dengan html ikon di peta.
            expect(dibuat[0].opsi.icon.html).toContain('penanda-kunjungan-beli');
            expect(dibuat[1].opsi.icon.html).toContain('penanda-kunjungan-tidak');
        });

        it('tidak menampilkan baris yang datanya kosong', () => {
            const html = popup({ ...lengkap, beli: true, keterangan: null, di_luar_rute: false, lokasi_palsu: false, jarak_m: null, perjalanan: null, keluar: null, durasi_menit: null });

            expect(html).not.toContain('badge');
            expect(html).not.toContain('Dari kunjungan sebelumnya');
            expect(html).not.toContain('Jarak ke toko');
            expect(html).toContain('<small>11:00</small>');
            expect(html).toContain('Beli');
        });

        it('memuat kode outlet di bawah judul, di-escape, dan tanpa baris kode bila kosong', () => {
            const dengan = popup({ ...lengkap, kode_outlet: 'OUT-70986' });
            expect(dengan).toContain('<small class="kode-mono text-gray-500">OUT-70986</small>');
            // Kode berada setelah judul dan sebelum jam.
            expect(dengan.indexOf('Kios Baru')).toBeLessThan(dengan.indexOf('OUT-70986'));
            expect(dengan.indexOf('OUT-70986')).toBeLessThan(dengan.indexOf('11:00'));

            expect(popup({ ...lengkap, kode_outlet: '<b>X</b>' })).not.toContain('<b>X</b>');
            expect(popup({ ...lengkap, kode_outlet: null })).not.toContain('kode-mono');
        });

        it('memuat tombol Lihat di Google Map ke koordinat check-in, dibuka di tab baru', () => {
            const html = popup(lengkap);

            expect(html).toContain('href="https://maps.google.com/maps?q=loc:-7.4,112.7&t=k"');
            expect(html).toContain('Lihat di Google Map');
            expect(html).toContain('target="_blank"');
            expect(html).toContain('rel="noopener"');
        });

        it('hasil "Beli" disertai ringkasan penjualan bila ada', () => {
            const html = popup({ ...lengkap, beli: true, penjualan: { transaksi: 2, total: 3500, sku: 3, qty: 5.5 } }).replace(/\u00a0/g, ' ');

            expect(html).toContain('Beli: 2 transaksi · Rp 3.500 · 3 jenis · 5,5 unit');
            expect(html).not.toContain('Tidak beli');
        });

        it('hasil "Beli" tanpa data penjualan tetap hanya bertuliskan Beli', () => {
            expect(popup({ ...lengkap, beli: true, penjualan: null })).toContain('>Beli</small>');
        });

        it('perjalanan tanpa jarak hanya menampilkan durasi', () => {
            expect(popup({ ...lengkap, perjalanan: { menit: 12, jarak_m: null } })).toContain('Dari kunjungan sebelumnya: 12 menit</small>');
        });

        it('meng-escape alasan dan nama outlet', () => {
            const html = popup({ ...lengkap, outlet: '<i>x</i>', keterangan: '<script>' });

            expect(html).not.toContain('<i>x</i>');
            expect(html).not.toContain('<script>');
            expect(html).toContain('&lt;script&gt;');
        });
    });

    it('mengosongkan layer lebih dulu dan meng-escape nama outlet di popup', () => {
        const { L, dibuat } = buatL();
        const layer = lapisan();
        gambarKunjungan(L, layer, kunjungan);

        expect(layer.clearLayers).toHaveBeenCalled();
        expect(dibuat[0].popup).toContain('Toko &lt;b&gt;Makmur&lt;/b&gt;');
        expect(dibuat[0].popup).not.toContain('<b>Makmur</b>');
        expect(dibuat[0].popup).toContain('09:00 – 09:15 (15 menit)');
    });
});

describe('gambarRute penanda awal dan akhir', () => {
    // L palsu: ikon dibuat lewat window.L.divIcon (ikonLingkaran), penanda lain hanya dicatat.
    function buatLRute() {
        const marker = [];
        const rantai = () => { const o = { addTo: vi.fn(() => o), bindTooltip: vi.fn(() => o), bindPopup: vi.fn(() => o), on: vi.fn(() => o) }; return o; };
        const L = {
            divIcon: vi.fn((opsi) => opsi),
            polyline: vi.fn(rantai),
            circleMarker: vi.fn(rantai),
            marker: vi.fn((ll, opsi) => { const m = rantai(); m.opsi = opsi; marker.push(m); return m; }),
            latLngBounds: vi.fn((t) => t),
        };
        window.L = L;

        return { L, marker };
    }

    const data = {
        locations: [
            { lat: '-7.10', lng: '112.10', waktu: '2026-10-21 08:00:00' },
            { lat: '-7.15', lng: '112.15', waktu: '2026-10-21 08:30:00' },
            { lat: '-7.20', lng: '112.20', waktu: '2026-10-21 09:00:00' },
        ],
        depo: null,
    };

    it('titik awal memakai ikon Lucide bike hijau dan titik akhir ikon Lucide flag merah', () => {
        const { L, marker } = buatLRute();
        gambarRute(L, { fitBounds: vi.fn() }, { clearLayers: vi.fn() }, data, 'Rudi');

        const [awal, akhir] = marker.map((m) => m.opsi.icon.html);
        expect(marker).toHaveLength(2);
        expect(awal).toContain('fill="#198754"');
        expect(awal).toContain('cx="18.5" cy="17.5" r="3.5"'); // Lucide bike
        expect(akhir).toContain('fill="#dc3545"');
        expect(akhir).toContain('M4 22V4'); // Lucide flag
        // Ikon Lucide berbentuk garis (outline putih), bukan isian.
        expect(awal).toContain('stroke="#fff"');
        expect(akhir).toContain('stroke="#fff"');
        delete window.L;
    });

    it('penanda depo memakai ikon Lucide building ungu', () => {
        const { L, marker } = buatLRute();
        gambarRute(L, { fitBounds: vi.fn() }, { clearLayers: vi.fn() }, { ...data, depo: { nama: 'JAKSEL', latitude: '-6.26', longitude: '106.81' } }, 'Rudi');

        const depo = marker.at(-1).opsi.icon.html;
        expect(marker).toHaveLength(3);
        expect(depo).toContain('fill="#6f42c1"');
        expect(depo).toContain('<rect x="4" y="2" width="16" height="20" rx="2"/>'); // Lucide building
        expect(depo).toContain('stroke="#fff"');
        delete window.L;
    });

    it('titik di antara awal dan akhir tetap lingkaran kecil biasa', () => {
        const { L } = buatLRute();
        gambarRute(L, { fitBounds: vi.fn() }, { clearLayers: vi.fn() }, data, 'Rudi');

        expect(L.circleMarker).toHaveBeenCalledTimes(1);
        delete window.L;
    });
});

describe('gambarLompatan', () => {
    const segmen = [
        { dari: { lat: -6.6, lng: 106.8, waktu: '2026-10-22 10:17:28' }, ke: { lat: -6.5, lng: 106.85, waktu: '2026-10-22 10:18:28' }, km: 10.5, menit: 1, kmh: 627, kembali: true },
        { dari: { lat: -6.5, lng: 106.85, waktu: '2026-10-22 10:21:29' }, ke: { lat: -6.6, lng: 106.8, waktu: '2026-10-22 10:22:14' }, km: 9.7, menit: 0.8, kmh: 772, kembali: false },
    ];

    function buatLGaris() {
        const garis = [];
        const L = {
            polyline: vi.fn((titik, gaya) => {
                const g = { titik, gaya, popup: null, addTo: vi.fn(), bindPopup: vi.fn((html) => { g.popup = html; return g; }) };
                g.addTo.mockReturnValue(g);
                garis.push(g);

                return g;
            }),
        };

        return { L, garis };
    }

    it('menggambar garis merah putus-putus dari titik asal ke titik tujuan tiap lompatan', () => {
        const { L, garis } = buatLGaris();
        const lapisan = { clearLayers: vi.fn() };
        gambarLompatan(L, lapisan, segmen);

        expect(lapisan.clearLayers).toHaveBeenCalled();
        expect(garis).toHaveLength(2);
        expect(garis[0].titik).toEqual([[-6.6, 106.8], [-6.5, 106.85]]);
        expect(garis[0].gaya).toMatchObject({ color: '#dc2626', dashArray: '6 6' });
        expect(garis[0].addTo).toHaveBeenCalledWith(lapisan);
    });

    it('popover memuat jarak, selisih waktu, kecepatan, jam, dan keterangan kembali ke lokasi semula', () => {
        const { L, garis } = buatLGaris();
        gambarLompatan(L, { clearLayers: vi.fn() }, segmen);

        expect(garis[0].popup).toContain('10,5 km dalam 1 menit (627 km/jam)');
        expect(garis[0].popup).toContain('10:17:28 → 10:18:28');
        expect(garis[0].popup).toContain('lalu kembali ke lokasi semula');
        expect(garis[1].popup).toContain('9,7 km dalam 0,8 menit (772 km/jam)');
        expect(garis[1].popup).not.toContain('kembali ke lokasi semula');
    });

    it('hanya mengosongkan layer bila tidak ada lompatan', () => {
        const { L, garis } = buatLGaris();
        const lapisan = { clearLayers: vi.fn() };
        gambarLompatan(L, lapisan, []);

        expect(lapisan.clearLayers).toHaveBeenCalled();
        expect(garis).toHaveLength(0);
    });
});

describe('gambarLompatan: titik GPS menyimpang tunggal', () => {
    const titik = [
        { lat: -6.55, lng: 106.8, waktu: '2026-10-22 14:09:06', km: 5.7 },
        { lat: -6.5, lng: 106.85, waktu: '2026-10-22 15:00:08', km: 8.4 },
    ];

    function buatLTitik() {
        const lingkaran = [];
        const L = {
            polyline: vi.fn(() => { const o = { bindPopup: vi.fn(() => o), addTo: vi.fn(() => o) }; return o; }),
            circleMarker: vi.fn((ll, gaya) => {
                const c = { ll, gaya, popup: null, addTo: vi.fn(), bindPopup: vi.fn((html) => { c.popup = html; return c; }) };
                c.addTo.mockReturnValue(c);
                lingkaran.push(c);

                return c;
            }),
        };

        return { L, lingkaran };
    }

    it('menggambar lingkaran merah berpusat putih di tiap titik menyimpang, ke layer yang sama', () => {
        const { L, lingkaran } = buatLTitik();
        const lapisan = { clearLayers: vi.fn() };
        gambarLompatan(L, lapisan, [], titik);

        expect(lapisan.clearLayers).toHaveBeenCalledTimes(1);
        expect(lingkaran).toHaveLength(2);
        expect(lingkaran[0].ll).toEqual([-6.55, 106.8]);
        expect(lingkaran[0].gaya).toMatchObject({ color: '#dc2626', fillColor: '#ffffff', fillOpacity: 1 });
        expect(lingkaran[0].addTo).toHaveBeenCalledWith(lapisan);
    });

    it('popover memuat jarak, jam, dan keterangan langsung kembali', () => {
        const { L, lingkaran } = buatLTitik();
        gambarLompatan(L, { clearLayers: vi.fn() }, [], titik);

        expect(lingkaran[0].popup).toContain('Titik GPS menyimpang');
        expect(lingkaran[0].popup).toContain('5,7 km dari posisi sekitarnya');
        expect(lingkaran[0].popup).toContain('14:09:06 · lalu langsung kembali ke lokasi semula');
        expect(lingkaran[1].popup).toContain('8,4 km');
    });

    it('titik menyimpang bersifat opsional: tanpa argumen keempat hanya garis lompatan', () => {
        const { L, lingkaran } = buatLTitik();
        gambarLompatan(L, { clearLayers: vi.fn() }, []);

        expect(lingkaran).toHaveLength(0);
    });
});

