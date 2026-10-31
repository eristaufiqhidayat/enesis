<?php

namespace Tests\Unit;

use App\Support\Karyawan\BacaKtp;
use PHPUnit\Framework\TestCase;

// Parsing teks mentah OCR -> NIK, nama, tempat/tanggal lahir, alamat. Diuji terpisah dari pembacaan gambar
// (praProses + tesseract) supaya tidak butuh binary tesseract untuk jalan di CI mana pun.
class BacaKtpTest extends TestCase
{
    public function test_ktp_rapi_terbaca_lengkap(): void
    {
        $teks = <<<'TEKS'
        PROVINSI JAWA BARAT
        KOTA BANDUNG
        NIK : 3273010101990001
        Nama : BUDI SANTOSO
        Tempat/Tgl Lahir : BANDUNG, 01-01-1999
        Jenis Kelamin : LAKI-LAKI
        Alamat : JL MERDEKA NO 12
        RT/RW : 001/002
        Kel/Desa : SUKALUYU
        Kecamatan : COBLONG
        Agama : ISLAM
        Status Perkawinan : BELUM KAWIN
        Pekerjaan : KARYAWAN SWASTA
        Kewarganegaraan : WNI
        Berlaku Hingga : SEUMUR HIDUP
        TEKS;

        $hasil = BacaKtp::parseTeks($teks);

        $this->assertSame('3273010101990001', $hasil['nik']);
        $this->assertSame('BUDI SANTOSO', $hasil['nama']);
        $this->assertSame('BANDUNG', $hasil['tempat_lahir']);
        $this->assertSame('1999-01-01', $hasil['tanggal_lahir']);
        $this->assertSame('JL MERDEKA NO 12', $hasil['alamat']);
    }

    public function test_ktp_lusuh_field_kosong_tidak_ditebak(): void
    {
        // OCR gagal baca label "Nama" dan "Alamat" (foto buram) -- harus null, bukan ditebak dari teks lain.
        $teks = <<<'TEKS'
        NIK : 3273010101990001
        Nam.: BUDI SANT0S0
        Tempat/Tgl Lahir : BANDUNG, 01-01-1999
        TEKS;

        $hasil = BacaKtp::parseTeks($teks);

        $this->assertSame('3273010101990001', $hasil['nik']);
        $this->assertNull($hasil['nama']);
        $this->assertSame('BANDUNG', $hasil['tempat_lahir']);
        $this->assertSame('1999-01-01', $hasil['tanggal_lahir']);
        $this->assertNull($hasil['alamat']);
    }

    public function test_teks_kosong_semua_null(): void
    {
        $hasil = BacaKtp::parseTeks('');

        $this->assertSame(['nik' => null, 'nama' => null, 'tempat_lahir' => null, 'tanggal_lahir' => null, 'alamat' => null], $hasil);
    }

    public function test_alamat_berhenti_sebelum_label_berikutnya(): void
    {
        $teks = <<<'TEKS'
        Nama : SITI AMINAH
        Alamat : JL KENANGA RAYA BLOK C NO 5
        RT/RW : 003/004
        Kel/Desa : CIPADUNG
        TEKS;

        $hasil = BacaKtp::parseTeks($teks);

        $this->assertSame('JL KENANGA RAYA BLOK C NO 5', $hasil['alamat']);
    }
}
