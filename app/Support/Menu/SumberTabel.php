<?php

namespace App\Support\Menu;

use Illuminate\Database\Query\Builder;

/**
 * Sumber data menu yang tidak bisa dibaca langsung dengan DB::table(), mis. butuh join ke tabel master.
 * Dipasang lewat config marketama.tables.<nama>.sumber; dipakai MenuController@show.
 */
interface SumberTabel
{
    /** Tabel utama; halaman dilewati jika tabel ini belum ada (sama seperti tabel biasa). */
    public function tabelUtama(): string;

    /** Query yang sudah memilih kolom dengan alias = key di config columns, dan sudah diurutkan. */
    public function query(): Builder;

    /** Kolom berkualifikasi yang dicari dengan ?q=, mis. ['o.nama', 'd.nama']. */
    public function kolomCari(): array;
}
