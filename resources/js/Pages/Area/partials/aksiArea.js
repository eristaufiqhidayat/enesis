// Popup "area yang sudah ada" dengan tombol Ubah (ke Design Area ?area=<id>) dan Hapus, dipakai bersama
// Generate Polygon Kelurahan dan Auto Grid. Popup Leaflet berupa string HTML, jadi tombol Hapus
// disambungkan lewat event popupopen (pasangAksiArea).
import { escapeHtml, ikonSvg } from '@/Support/peta.js';

const ikonPensil = ikonSvg('<path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/>');
const ikonTempatSampah = ikonSvg('<path d="M10 11v6"/><path d="M14 11v6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>');

/**
 * @param {string} judulHtml  kepala popup (sudah di-escape)
 * @param {Array<{id: number, code: string, name: string, warehouse: ?string}>} daftar
 * @param {{bisaUbah: boolean, bisaHapus: boolean, designUrl: ?string, hapusUrl: ?string}} hak
 */
export function popupAksiArea(judulHtml, daftar, hak) {
    return '<div style="min-width:220px;font-size:12px">' + judulHtml + '<hr class="my-1">'
        + daftar.map((a) => '<div class="mb-1"><b>' + escapeHtml(a.code) + '</b> ' + escapeHtml(a.name)
            + (a.warehouse ? '<br><small>Depo ' + escapeHtml(a.warehouse) + '</small>' : '')
            + '<div class="flex flex-wrap gap-1 mt-1">'
            + (hak.bisaUbah && hak.designUrl ? '<a class="btn btn-sm btn-outline-primary" href="' + hak.designUrl + '?area=' + a.id + '">' + ikonPensil + 'Ubah</a>' : '')
            + (hak.bisaHapus && hak.hapusUrl ? '<button type="button" class="btn btn-sm btn-outline-danger btn-delete-area" data-id="' + a.id + '" data-code="' + escapeHtml(a.code) + '">' + ikonTempatSampah + 'Hapus</button>' : '')
            + '</div></div>').join('')
        + (!hak.bisaUbah && !hak.bisaHapus ? '<small class="text-gray-500">Anda tidak punya hak mengubah atau menghapus area.</small>' : '')
        + '</div>';
}

/** Sambungkan tombol Hapus di popup ke onHapus(id, kode). Panggil sekali di onSiap peta. */
export function pasangAksiArea(peta, onHapus) {
    peta.on('popupopen', (e) => {
        e.popup.getElement().querySelectorAll('.btn-delete-area').forEach((tombol) => {
            tombol.addEventListener('click', () => onHapus(Number(tombol.dataset.id), tombol.dataset.code));
        });
    });
}

/** Hapus satu area lewat API Design Area setelah konfirmasi. Mengembalikan true bila terhapus. */
export async function hapusArea(hapusUrl, id, kode) {
    if (!confirm(`Hapus area ${kode}? Wilayahnya bisa dibuat ulang sesudahnya.`)) return false;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const res = await fetch(hapusUrl + '/' + id, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' } });
    if (!res.ok) {
        alert('Gagal menghapus area.');
        return false;
    }
    return true;
}
