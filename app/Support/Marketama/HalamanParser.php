<?php

namespace App\Support\Marketama;

use DOMDocument;
use DOMElement;
use DOMXPath;

// Pembaca HTML halaman aplikasi lama Marketama (Laravel + DataTables server-side).
class HalamanParser
{
    public static function dom(string $html): DOMXPath
    {
        $doc = new DOMDocument;
        $prev = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        return new DOMXPath($doc);
    }

    /** Token CSRF yang dipakai DataTables (header X-CSRF-TOKEN di script halaman). */
    public static function csrfToken(string $html): ?string
    {
        if (preg_match('/X-CSRF-TOKEN[\'"]?\s*:\s*[\'"]([^\'"]+)[\'"]/', $html, $m)) {
            return $m[1];
        }

        if (preg_match('/<meta[^>]+name=["\']csrf-token["\'][^>]+content=["\']([^"\']+)/i', $html, $m)) {
            return $m[1];
        }

        $x = self::dom($html);
        $input = $x->query('//input[@name="_token"]')->item(0);

        return $input instanceof DOMElement ? $input->getAttribute('value') : null;
    }

    /** Path endpoint DataTables di halaman menu, mis. "/outlet_all/datatable_outlet_all". */
    public static function datatableUrl(string $html): ?string
    {
        if (! preg_match_all('/[\'"]([^\'"\s]*\/datatable[a-z_]*)[\'"]/i', $html, $m)) {
            return null;
        }

        $url = $m[1][0];

        return '/'.ltrim((string) preg_replace('#^https?://[^/]+#', '', $url), '/');
    }

    /**
     * Judul kolom tabel DataTables (dari <thead>) dinormalkan jadi kunci snake_case.
     * Kolom tanpa judul (checkbox/aksi) diberi nama "_0", "_1", dst.
     *
     * @return list<string>
     */
    public static function kolom(string $html, ?string $tableId = null): array
    {
        $x = self::dom($html);
        $path = $tableId ? "//table[@id='{$tableId}']//thead//th" : '(//table[.//thead])[1]//thead//th';
        $keys = [];

        foreach ($x->query($path) as $i => $th) {
            $key = self::kunci($th->textContent);
            $keys[] = $key === '' ? "_{$i}" : $key;
        }

        return $keys;
    }

    public static function kunci(string $judul): string
    {
        $judul = strtolower(trim(html_entity_decode($judul, ENT_QUOTES | ENT_HTML5, 'UTF-8')));

        return trim((string) preg_replace('/[^a-z0-9]+/', '_', $judul), '_');
    }

    /** Teks polos sebuah sel DataTables (HTML dibuang, spasi dirapikan). */
    public static function teks(mixed $sel): ?string
    {
        if ($sel === null) {
            return null;
        }

        $teks = html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />'], ' ', (string) $sel)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $teks = trim((string) preg_replace('/\s+/u', ' ', $teks));

        return $teks === '' ? null : $teks;
    }

    /** ID baris: data-id pertama di baris, atau sel pertama bila berupa angka. */
    public static function idBaris(array $sel): ?int
    {
        foreach ($sel as $isi) {
            if (preg_match('/data-id=\\\\?["\']?(\d+)/', (string) $isi, $m)) {
                return (int) $m[1];
            }
        }

        $pertama = self::teks($sel[0] ?? null);

        return $pertama !== null && ctype_digit($pertama) ? (int) $pertama : null;
    }

    /**
     * Gabungkan judul kolom dengan isi sel => [kunci => teks]. Kolom "_n" (checkbox/aksi) dibuang.
     *
     * @param  list<string>  $kolom
     * @return array<string, ?string>
     */
    public static function baris(array $kolom, array $sel): array
    {
        $hasil = [];

        foreach (array_values($sel) as $i => $isi) {
            $kunci = $kolom[$i] ?? "kolom_{$i}";

            if (str_starts_with($kunci, '_')) {
                continue;
            }

            $hasil[$kunci] = self::teks($isi);
        }

        return $hasil;
    }

    /**
     * Nilai isian form edit: input (value), textarea (isi), select (option terpilih).
     *
     * @return array<string, string|null>
     */
    public static function form(string $html): array
    {
        $x = self::dom($html);
        $nilai = [];

        foreach ($x->query('//input[@name] | //textarea[@name] | //select[@name]') as $el) {
            /** @var DOMElement $el */
            $nama = $el->getAttribute('name');
            $tipe = strtolower($el->getAttribute('type'));

            if ($nama === '_token' || in_array($tipe, ['password', 'file', 'submit', 'button'], true) || array_key_exists($nama, $nilai)) {
                continue;
            }

            if ($tipe === 'checkbox' || $tipe === 'radio') {
                if ($el->hasAttribute('checked')) {
                    $nilai[$nama] = $el->getAttribute('value') ?: '1';
                }

                continue;
            }

            $nilai[$nama] = match (strtolower($el->tagName)) {
                'textarea' => trim($el->textContent),
                'select' => self::opsiTerpilih($x, $el),
                default => $el->getAttribute('value'),
            };
        }

        return array_map(fn ($v) => $v === '' ? null : $v, $nilai);
    }

    private static function opsiTerpilih(DOMXPath $x, DOMElement $select): ?string
    {
        $opsi = $x->query('.//option[@selected]', $select)->item(0);

        // Select2 berbasis AJAX hanya berisi satu option (yang terpilih) tanpa atribut selected.
        if (! $opsi) {
            $semua = $x->query('.//option[normalize-space(@value) != ""]', $select);
            $opsi = $semua->length === 1 ? $semua->item(0) : null;
        }

        return $opsi instanceof DOMElement ? $opsi->getAttribute('value') : null;
    }

    /**
     * Daftar area milik sales di form edit karyawan_sales (atribut data-areas berisi JSON).
     *
     * @return list<array{area_id:int, aktif:bool}>
     */
    public static function areaKaryawan(string $html): array
    {
        $x = self::dom($html);
        $el = $x->query('//*[@data-areas]')->item(0);

        if (! $el instanceof DOMElement) {
            return [];
        }

        $data = json_decode($el->getAttribute('data-areas'), true);

        if (! is_array($data)) {
            return [];
        }

        return array_values(array_filter(array_map(fn ($a) => isset($a['area_id']) ? [
            'area_id' => (int) $a['area_id'],
            'aktif' => (bool) ($a['active'] ?? false),
        ] : null, $data)));
    }

    /**
     * Isian form login: action dan nama field username/password.
     *
     * @return array{action:?string, fields:array<string,string>, user:?string, pass:?string}
     */
    public static function formLogin(string $html): array
    {
        $x = self::dom($html);
        $pass = $x->query('//input[@type="password"]')->item(0);
        $form = $pass;

        while ($form && strtolower($form->nodeName) !== 'form') {
            $form = $form->parentNode;
        }

        $fields = [];
        $user = null;

        if ($form instanceof DOMElement) {
            foreach ($x->query('.//input[@name]', $form) as $input) {
                /** @var DOMElement $input */
                $tipe = strtolower($input->getAttribute('type') ?: 'text');
                $nama = $input->getAttribute('name');

                if ($tipe === 'hidden') {
                    $fields[$nama] = $input->getAttribute('value');
                } elseif ($user === null && in_array($tipe, ['text', 'email'], true)) {
                    $user = $nama;
                }
            }
        }

        return [
            'action' => $form instanceof DOMElement ? ($form->getAttribute('action') ?: null) : null,
            'fields' => $fields,
            'user' => $user,
            'pass' => $pass instanceof DOMElement ? $pass->getAttribute('name') : null,
        ];
    }

    /** Kunci pencocokan nama: huruf kecil, spasi di tepi dibuang, spasi ganda di tengah jadi satu. */
    public static function kunciNama(?string $nama): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', (string) $nama)));
    }

    public static function aktif(?string $status): ?bool
    {
        if ($status === null) {
            return null;
        }

        $s = strtolower(trim($status));

        if (in_array($s, ['1', 'aktif', 'active', 'ya'], true)) {
            return true;
        }

        if (in_array($s, ['0', 'nonaktif', 'non aktif', 'tidak aktif', 'inactive', 'tidak'], true)) {
            return false;
        }

        return null;
    }

    /** Angka dari teks tampilan: "Rp 16.656.000" => 16656000, "106.8227" (koordinat) tetap desimal. */
    public static function angka(?string $teks, bool $desimalTitik = false): ?float
    {
        if ($teks === null || trim($teks) === '' || trim($teks) === '-') {
            return null;
        }

        $t = preg_replace('/[^0-9,.\-]/', '', $teks);

        // Angka mentah dari value input ("15000" / "15000.50") tidak memakai pemisah ribuan.
        if ($desimalTitik || preg_match('/^-?\d+(\.\d{1,2})?$/', (string) $t) && ! str_contains($teks, 'Rp')) {
            $t = str_replace(',', '.', (string) $t);

            return is_numeric($t) ? (float) $t : null;
        }

        // Format Indonesia: titik = ribuan, koma = desimal.
        $t = str_replace(['.', ','], ['', '.'], (string) $t);

        return is_numeric($t) ? (float) $t : null;
    }
}
