// Ambang deteksi "kunjungan berturutan berjarak dekat" (spec 054, spec 058), disimpan di localStorage browser pengguna
// (bukan database): tiap pengguna bisa menyesuaikannya sendiri dan modal Riwayat meneruskannya ke endpoint locations.

export const KUNCI_DETEKSI = 'enesis.deteksiKunjungan';

// Harus sama dengan config('marketama.deteksi_jarak_kunjungan') yang dipakai server bila permintaan tanpa ambang.
export const BAWAAN_DETEKSI = { jarak_dekat_meter: 11, min_kunjungan_berturutan: 3 };

// Batas sama dengan validasi endpoint locations.
export const BATAS_DETEKSI = { jarak_dekat_meter: { min: 1, maks: 10000 }, min_kunjungan_berturutan: { min: 2, maks: 100 } };

const bulat = (nilai, { min, maks }, bawaan) => {
    const n = Number.parseInt(nilai, 10);

    return Number.isFinite(n) && n >= min && n <= maks ? n : bawaan;
};

// Nilai di luar batas atau bukan angka diganti bawaannya, supaya data localStorage yang rusak tidak merusak permintaan.
export function rapikanDeteksi(mentah) {
    return {
        jarak_dekat_meter: bulat(mentah?.jarak_dekat_meter, BATAS_DETEKSI.jarak_dekat_meter, BAWAAN_DETEKSI.jarak_dekat_meter),
        min_kunjungan_berturutan: bulat(mentah?.min_kunjungan_berturutan, BATAS_DETEKSI.min_kunjungan_berturutan, BAWAAN_DETEKSI.min_kunjungan_berturutan),
    };
}

// localStorage bisa kosong atau melempar galat (jendela privat, data situs diblokir): selalu jatuh ke bawaan.
export function bacaDeteksi() {
    try {
        return rapikanDeteksi(JSON.parse(localStorage.getItem(KUNCI_DETEKSI)));
    } catch {
        return { ...BAWAAN_DETEKSI };
    }
}

/** @returns {{nilai: object, tersimpan: boolean}} nilai yang berlaku dan apakah benar masuk ke localStorage */
export function simpanDeteksi(nilai) {
    const rapi = rapikanDeteksi(nilai);

    try {
        localStorage.setItem(KUNCI_DETEKSI, JSON.stringify(rapi));

        return { nilai: rapi, tersimpan: true };
    } catch {
        return { nilai: rapi, tersimpan: false };
    }
}

export function hapusDeteksi() {
    try {
        localStorage.removeItem(KUNCI_DETEKSI);
    } catch {
        // Tidak ada yang perlu dihapus bila penyimpanan tidak tersedia.
    }

    return { ...BAWAAN_DETEKSI };
}
