<?php

namespace App\Support\Menu;

use Illuminate\Database\Query\Builder;

/**
 * Sumber data menu yang bisa disaring menurut "status" lewat dropdown di header tabel (?status=<kunci>),
 * mis. kebaruan posisi GPS di Tracking Last Seen. MenuController menerapkannya di SQL supaya benar lintas halaman.
 */
interface SumberBerstatus extends SumberTabel
{
    /**
     * Pilihan dropdown: kunci => label.
     *
     * @return array<string, string>
     */
    public function pilihanStatus(): array;

    /** Batasi $query ke baris berstatus $status (salah satu kunci pilihanStatus()). */
    public function batasiStatus(Builder $query, string $status): void;
}
