<?php

namespace App\Support\Menu;

use App\Models\User;
use Illuminate\Database\Query\Builder;

/**
 * Sumber data menu yang tidak bisa dibaca langsung dengan DB::table(), mis. butuh join ke tabel master.
 * Dipasang lewat config marketama.tables.<nama>.sumber; dipakai MenuController@show.
 */
interface SumberTabel
{
    /** Tabel utama; halaman dilewati jika tabel ini belum ada (sama seperti tabel biasa). */
    public function tabelUtama(): string;

    /**
     * Query yang sudah memilih kolom dengan alias = key di config columns, dan sudah diurutkan.
     * $user dipakai untuk membatasi baris sesuai cakupan role bila perlu.
     */
    public function query(?User $user): Builder;

    /** Kolom berkualifikasi yang dicari dengan ?q=, mis. ['o.nama', 'd.nama']. */
    public function kolomCari(): array;

    /** Rapikan satu baris sebelum tampil (mis. jam UTC → WIB). */
    public function ubahBaris(object $baris): object;
}
