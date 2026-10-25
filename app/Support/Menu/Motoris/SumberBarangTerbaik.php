<?php

namespace App\Support\Menu\Motoris;

use App\Models\User;
use App\Support\Akses\Akses;
use App\Support\Menu\SumberBertanggal;
use App\Support\Mobile\Pengaturan;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Lap. Barang Terbaik Harian / Mingguan / Bulanan: produk terlaris (qty) dari trx_penjualan_item pada hari, minggu
 * (Senin–Minggu) atau bulan yang memuat tanggal terpilih. Subkelas menentukan periode.
 */
abstract class SumberBarangTerbaik implements SumberBertanggal
{
    /** harian | mingguan | bulanan */
    abstract protected function periode(): string;

    public function tabelUtama(): string
    {
        return 'trx_penjualan_item';
    }

    public function query(?User $user): Builder
    {
        return $this->queryTanggal($user, Pengaturan::sekarang()->toDateString(), ['depo' => null, 'koordinator' => null]);
    }

    public function queryTanggal(?User $user, string $tanggal, array $filter): Builder
    {
        [$dari, $sampai] = $this->rentang($tanggal);
        // Agregat per produk dulu, baru join master produk (kolom JSON tidak bisa ikut GROUP BY di PostgreSQL).
        $agregat = $this->dasar($user, $dari, $sampai, $filter)->groupBy('i.produk_id')
            ->selectRaw('i.produk_id, SUM(i.qty) AS total_qty, SUM(i.total) AS total_amount, SUM(i.diskon) AS total_diskon, COUNT(DISTINCT p.id) AS total_transaksi');

        return DB::query()->fromSub($agregat, 'a')
            ->leftJoin('mk_produk as pr', 'pr.id', '=', 'a.produk_id')
            ->orderByDesc('a.total_qty')->orderBy('pr.nama')
            ->select(['a.produk_id as product_id', 'pr.nama as product_name', 'pr.kode as product_sku', 'pr.data as produk_data',
                'a.total_qty', 'a.total_amount', 'a.total_diskon', 'a.total_transaksi'])
            ->selectRaw('? AS tanggal_data', [$dari]);
    }

    public function kpi(?User $user, string $tanggal, array $filter): array
    {
        [$dari, $sampai] = $this->rentang($tanggal);
        $total = $this->dasar($user, $dari, $sampai, $filter)
            ->selectRaw('COUNT(DISTINCT i.produk_id) AS produk, COALESCE(SUM(i.qty), 0) AS qty, COALESCE(SUM(i.total), 0) AS nilai, COUNT(DISTINCT p.id) AS transaksi')
            ->first();

        return [
            ['label' => 'Periode', 'value' => $dari === $sampai ? $dari : "{$dari} s/d {$sampai}"],
            ['label' => 'Produk terjual', 'value' => (int) $total->produk],
            ['label' => 'Total qty', 'value' => SumberPenjualan::angka($total->qty)],
            ['label' => 'Total penjualan', 'value' => 'Rp '.number_format((float) $total->nilai, 0, ',', '.')],
            ['label' => 'Transaksi', 'value' => (int) $total->transaksi],
        ];
    }

    public function keterangan(): ?string
    {
        $periode = ['harian' => 'tanggal yang dipilih', 'mingguan' => 'minggu (Senin–Minggu) yang memuat tanggal terpilih',
            'bulanan' => 'bulan yang memuat tanggal terpilih'][$this->periode()];

        return "Produk terlaris berdasarkan qty pada {$periode}, dari penjualan aplikasi motoris dan riwayat aplikasi lama (tidak termasuk transaksi dibatalkan).";
    }

    public function pilihanFilter(?User $user): array
    {
        $depo = Akses::batasiKaryawan(DB::table('mk_karyawan as k')->where('k.kategori', 'sales')->whereNotNull('k.depo_id'), $user)
            ->distinct()->pluck('k.depo_id');

        return [
            'depo' => DB::table('mk_depo')->whereIn('id', $depo->all() ?: [0])->orderBy('nama')->pluck('nama', 'id')->all(),
            'koordinator' => Akses::batasiKaryawan(DB::table('mk_karyawan as k')->where('k.kategori', 'koordinator')->where('k.aktif', true), $user)
                ->orderBy('k.nama')->pluck('k.nama', 'k.id')->all(),
        ];
    }

    public function ubahBaris(object $baris): object
    {
        $produk = json_decode((string) $baris->produk_data, true) ?: [];
        $baris->category_name = $produk['kategori'] ?? null;
        $baris->brand_name = $produk['merk'] ?? null;
        $baris->unit_name = $produk['satuan'] ?? null;
        $baris->total_qty = SumberPenjualan::angka($baris->total_qty);
        $baris->total_amount = number_format((float) $baris->total_amount, 0, ',', '.');
        $baris->total_discount = number_format((float) $baris->total_diskon, 0, ',', '.');
        unset($baris->produk_data, $baris->total_diskon);

        return $baris;
    }

    public function kolomCari(): array
    {
        return ['pr.nama', 'pr.kode'];
    }

    /** @return array{0: string, 1: string} */
    public function rentang(string $tanggal): array
    {
        $t = CarbonImmutable::parse($tanggal);

        return match ($this->periode()) {
            'harian' => [$t->toDateString(), $t->toDateString()],
            'mingguan' => [$t->startOfWeek(CarbonImmutable::MONDAY)->toDateString(), $t->endOfWeek(CarbonImmutable::SUNDAY)->toDateString()],
            'bulanan' => [$t->startOfMonth()->toDateString(), $t->endOfMonth()->toDateString()],
        };
    }

    private function dasar(?User $user, string $dari, string $sampai, array $filter): Builder
    {
        return SumberPenjualan::dasar($user)
            ->join('trx_penjualan_item as i', 'i.penjualan_id', '=', 'p.id')
            ->whereBetween('p.tanggal', [$dari, $sampai])
            ->when($filter['depo'] ?? null, fn ($w, $depo) => $w->where('k.depo_id', $depo))
            ->when($filter['koordinator'] ?? null, fn ($w, $ko) => $w->where('k.koordinator_id', $ko));
    }
}
