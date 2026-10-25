<?php

namespace App\Support\Menu;

use App\Models\User;
use Illuminate\Database\Query\Builder;

/**
 * Sumber data menu yang barisnya dihitung untuk satu tanggal (bukan tabel yang disaring kolom tanggalnya),
 * mis. "sales aktif yang belum checkin pada tanggal X". MenuController memakai queryTanggal() alih-alih
 * query() + where(date_filter_column), dan menampilkan KPI, keterangan, serta filter depo/koordinator dari sini.
 */
interface SumberBertanggal extends SumberTabel
{
    /**
     * @param  array{depo: ?int, koordinator: ?int}  $filter
     */
    public function queryTanggal(?User $user, string $tanggal, array $filter): Builder;

    /**
     * Kartu KPI untuk tanggal & filter yang sama dengan tabel.
     *
     * @param  array{depo: ?int, koordinator: ?int}  $filter
     * @return array<int, array{label: string, value: int|string}>
     */
    public function kpi(?User $user, string $tanggal, array $filter): array;

    /** Keterangan singkat di atas tabel (aturan yang dipakai); null = tidak ada. */
    public function keterangan(): ?string;

    /**
     * Pilihan filter dropdown dalam cakupan akun: ['depo' => [id => nama], 'koordinator' => [id => nama]].
     *
     * @return array<string, array<int, string>>
     */
    public function pilihanFilter(?User $user): array;
}
