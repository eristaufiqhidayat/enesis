<?php

namespace App\Support\Karyawan;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;

// Baca NIK, nama, tempat/tanggal lahir, dan alamat dari foto KTP lewat OCR (Google Cloud Vision API,
// "services.google_vision.key" / env GOOGLE_VISION_API_KEY), dipakai KaryawanController::bacaKtp() saat
// admin menambah karyawan sales (motoris) baru. Dikirim lewat HTTP ke Google, bukan binary lokal, supaya
// tetap jalan di shared hosting yang tidak bisa pasang paket sistem seperti Tesseract. Foto cuma dipakai
// sebentar (pra-proses lalu OCR) dan tidak pernah disimpan. Hasil OCR tidak 100% akurat (tergantung kualitas
// foto): field yang gagal terbaca dikembalikan null supaya admin mengisinya manual, bukan ditebak.
class BacaKtp
{
    /** @return array{nik: ?string, nama: ?string, tempat_lahir: ?string, tanggal_lahir: ?string, alamat: ?string, teks_mentah: string} */
    public static function baca(UploadedFile $file): array
    {
        $gambarBase64 = self::praProses($file->getRealPath());
        $teks = self::jalankanOcr($gambarBase64);

        return self::parseTeks($teks) + ['teks_mentah' => $teks];
    }

    /**
     * Perbesar gambar kecil, ubah ke abu-abu, pertajam kontras -- supaya karakter lebih mudah dibaca OCR.
     * Dikembalikan sebagai base64 PNG (format yang diminta Google Vision), bukan file di disk.
     */
    private static function praProses(string $sumber): string
    {
        $info = @getimagesize($sumber);
        $gambar = match ($info[2] ?? null) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($sumber),
            IMAGETYPE_PNG => imagecreatefrompng($sumber),
            IMAGETYPE_WEBP => imagecreatefromwebp($sumber),
            default => imagecreatefromstring((string) file_get_contents($sumber)),
        };

        if (! $gambar) {
            throw new RuntimeException('Format gambar tidak didukung.');
        }

        $lebar = imagesx($gambar);
        $tinggi = imagesy($gambar);

        if ($lebar > 0 && $lebar < 1500) {
            $skala = 1500 / $lebar;
            $diperbesar = imagescale($gambar, 1500, (int) round($tinggi * $skala));

            if ($diperbesar !== false) {
                imagedestroy($gambar);
                $gambar = $diperbesar;
            }
        }

        imagefilter($gambar, IMG_FILTER_GRAYSCALE);
        imagefilter($gambar, IMG_FILTER_CONTRAST, -15);

        ob_start();
        imagepng($gambar);
        $isi = (string) ob_get_clean();
        imagedestroy($gambar);

        return base64_encode($isi);
    }

    private static function jalankanOcr(string $gambarBase64): string
    {
        $kunci = config('services.google_vision.key');

        if (! $kunci) {
            throw new RuntimeException('GOOGLE_VISION_API_KEY belum diatur di .env.');
        }

        $respons = Http::timeout(20)->post('https://vision.googleapis.com/v1/images:annotate?key='.$kunci, [
            'requests' => [[
                'image' => ['content' => $gambarBase64],
                'features' => [['type' => 'TEXT_DETECTION', 'maxResults' => 1]],
                'imageContext' => ['languageHints' => ['id']],
            ]],
        ]);

        if ($respons->failed()) {
            throw new RuntimeException('Google Vision API gagal dipanggil: '.$respons->status());
        }

        $kesalahan = $respons->json('responses.0.error');

        if ($kesalahan) {
            throw new RuntimeException('Google Vision API error: '.($kesalahan['message'] ?? 'tidak diketahui'));
        }

        return (string) $respons->json('responses.0.fullTextAnnotation.text', '');
    }

    /**
     * Ambil NIK, nama, tempat/tanggal lahir, dan alamat dari teks mentah hasil OCR. Berdasarkan label baku
     * KTP ("NIK", "Nama", "Tempat/Tgl Lahir", "Alamat") -- field yang tidak ketemu dikembalikan null, bukan ditebak.
     *
     * @return array{nik: ?string, nama: ?string, tempat_lahir: ?string, tanggal_lahir: ?string, alamat: ?string}
     */
    public static function parseTeks(string $teks): array
    {
        $teks = str_replace("\r", '', $teks);
        $hasil = ['nik' => null, 'nama' => null, 'tempat_lahir' => null, 'tanggal_lahir' => null, 'alamat' => null];

        if (preg_match('/\b(\d{16})\b/', $teks, $m) || preg_match('/NIK[:\s]*([0-9]{14,16})/i', $teks, $m)) {
            $hasil['nik'] = substr($m[1], 0, 16);
        }

        if (preg_match('/Nama\s*[:\-]?\s*([A-Z][A-Z .\'\-]{2,})/u', $teks, $m)) {
            $hasil['nama'] = trim((string) preg_replace('/\s{2,}/', ' ', $m[1]));
        }

        if (preg_match('/Tempat[\/\s]*Tgl\s*Lahir\s*[:\-]?\s*([A-Za-z .\'\-]+)[,.]?\s*(\d{2}[-\/]\d{2}[-\/]\d{4})/i', $teks, $m)) {
            $hasil['tempat_lahir'] = trim($m[1]);
            $hasil['tanggal_lahir'] = self::keFormatTanggal($m[2]);
        } elseif (preg_match('/(\d{2}[-\/]\d{2}[-\/]\d{4})/', $teks, $m)) {
            $hasil['tanggal_lahir'] = self::keFormatTanggal($m[1]);
        }

        if (preg_match('/Alamat\s*[:\-]?\s*([^\n]+(?:\n(?!RT\s*\/?\s*RW|Kel|Desa|Kecamatan|Agama|Status|Pekerjaan|Kewarganegaraan|Berlaku)[^\n]+)*)/i', $teks, $m)) {
            $hasil['alamat'] = trim((string) preg_replace('/\s{2,}/', ' ', str_replace("\n", ' ', $m[1])));
        }

        return $hasil;
    }

    /** "DD-MM-YYYY" atau "DD/MM/YYYY" dari KTP -> "YYYY-MM-DD" dipakai form. */
    private static function keFormatTanggal(string $tanggal): string
    {
        [$d, $b, $t] = explode('-', str_replace('/', '-', $tanggal));

        return sprintf('%04d-%02d-%02d', (int) $t, (int) $b, (int) $d);
    }
}
