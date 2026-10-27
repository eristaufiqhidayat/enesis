<?php

namespace App\Support\Wilayah;

// Nama wilayah Kemendagri (huruf besar, singkatan "KAB." dsb.) ke gaya master Enesis/Marketama
// ("Kabupaten Bogor", "Kota Jakarta Pusat", "Gambir"), dan pembanding nama yang mengabaikan gaya penulisan.
class NamaWilayah
{
    private const SINGKATAN = [
        '/\bKOTA\s+ADM\.?\s+/iu' => 'Kota Administrasi ',
        '/\bKAB\.\s*/iu' => 'Kabupaten ',
        '/\bKAB\s+/iu' => 'Kabupaten ',
        '/\bADM\.\s*/iu' => 'Administrasi ',
        '/\bKEP\.\s*/iu' => 'Kepulauan ',
    ];

    // Ditulis huruf besar semua (nama provinsi dan angka romawi dalam nama kecamatan/desa).
    private const KAPITAL = ['DKI', 'DI', 'NTB', 'NTT', 'II', 'III', 'IV', 'VI', 'VII', 'VIII', 'IX', 'XI', 'XII'];

    public static function rapikan(string $nama): string
    {
        $nama = preg_replace('/\s+/u', ' ', trim($nama));
        foreach (self::SINGKATAN as $pola => $ganti) {
            $nama = preg_replace($pola, $ganti, $nama);
        }
        $nama = mb_convert_case(mb_strtolower($nama), MB_CASE_TITLE, 'UTF-8');

        // mb TITLE juga membesarkan huruf setelah apostrof ("Je'Nebatu") — kembalikan.
        $nama = preg_replace_callback("/'(\p{Lu})/u", fn ($m) => "'".mb_strtolower($m[1]), $nama);

        return preg_replace_callback('/\b[\p{L}]+\b/u', function ($m) {
            $besar = mb_strtoupper($m[0]);

            return in_array($besar, self::KAPITAL, true) ? $besar : $m[0];
        }, trim(preg_replace('/\s+/u', ' ', $nama)));
    }

    /** Kunci pembanding: tanpa huruf besar/kecil, tanda baca, spasi, dan awalan jenis wilayah. */
    public static function kunci(string $nama): string
    {
        $nama = mb_strtolower(html_entity_decode($nama, ENT_QUOTES | ENT_HTML5));
        foreach (self::SINGKATAN as $pola => $ganti) {
            $nama = preg_replace($pola, mb_strtolower($ganti), $nama);
        }
        $nama = preg_replace('/^(kabupaten|kota)\s+(administrasi\s+)?|^(provinsi|kecamatan|kelurahan|desa)\s+/u', '', trim($nama));
        $nama = str_replace(['daerah istimewa', 'daerah khusus ibukota'], ['di', 'dki'], $nama);

        return preg_replace('/[^\p{L}\p{N}]/u', '', $nama);
    }

    public static function sama(?string $a, ?string $b): bool
    {
        return self::kunci((string) $a) === self::kunci((string) $b);
    }
}
