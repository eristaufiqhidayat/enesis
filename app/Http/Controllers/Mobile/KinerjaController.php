<?php

namespace App\Http\Controllers\Mobile;

use App\Support\Mobile\HariKerja;
use App\Support\Mobile\Lokasi;
use App\Support\Mobile\Pengaturan;
use App\Support\Motoris\Insentif;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// Ringkasan hari ini (layar Beranda: 5 langkah, KPI vs target, outlet berikutnya), rekap
// bulanan + perkiraan insentif, dan status sinkron untuk mencocokkan antrian di HP.
class KinerjaController extends MobileController
{
    public function ringkasan(Request $request): JsonResponse
    {
        $request->validate(['date' => ['nullable', 'date_format:Y-m-d']] + $this->aturanLokasi(false));

        $karyawan = $this->karyawan($request);
        $hari = $this->hari($request, $request->input('date'));
        $depo = Pengaturan::depo($karyawan->depo_id ? (int) $karyawan->depo_id : null);
        $target = $depo['target'] ?? config('mobile.target_default');
        $kpi = $this->kpi($hari->karyawanId, $hari->tanggal, $hari->tanggal);

        $absen = $hari->absensi();
        $depoMasuk = $hari->checkinDepo();
        $canvassing = $hari->canvassing();
        $langkah = [
            ['key' => 'attendance', 'label' => 'Absen masuk', 'done' => (bool) $absen, 'at' => $this->waktu($absen?->masuk_at)],
            ['key' => 'depot_check_in', 'label' => 'Checkin depo', 'done' => (bool) $depoMasuk, 'at' => $this->waktu($depoMasuk?->checkin_at)],
            ['key' => 'accept_goods', 'label' => 'Terima barang', 'done' => (bool) $canvassing?->diterima_at, 'at' => $this->waktu($canvassing?->diterima_at)],
            ['key' => 'visits', 'label' => 'Kunjungan outlet', 'done' => (bool) $canvassing?->dikembalikan_at, 'at' => null, 'count' => $kpi['call']],
            ['key' => 'close_day', 'label' => 'Tutup hari', 'done' => (bool) $depoMasuk?->checkout_at, 'at' => $this->waktu($depoMasuk?->checkout_at)],
        ];
        $aktif = collect($langkah)->search(fn ($l) => ! $l['done']);
        $langkah = array_map(fn ($l, $i) => $l + ['status' => $l['done'] ? 'done' : ($i === $aktif ? 'current' : 'pending')], $langkah, array_keys($langkah));

        return $this->ok([
            'date' => $hari->tanggal,
            'steps' => $langkah,
            'kpi' => [
                'call' => ['value' => $kpi['call'], 'target' => $target['call']],
                'ec' => ['value' => $kpi['ec'], 'target' => $target['ec']],
                'ipt' => ['value' => $kpi['ipt'], 'target' => $target['ipt']],
                'sales_amount' => ['value' => $kpi['omset'], 'target' => $target['omset']],
                'points' => $kpi['poin'],
            ],
            'next_outlet' => $this->outletBerikutnya($hari, $request),
            'open_visit_id' => ($k = $hari->kunjunganTerbuka()) ? (int) $k->id : null,
        ]);
    }

    public function bulanan(Request $request): JsonResponse
    {
        $request->validate(['month' => ['nullable', 'date_format:Y-m']]);
        $bulan = $request->input('month', $this->sekarang()->format('Y-m'));
        $awal = CarbonImmutable::parse("$bulan-01");
        $akhir = $awal->endOfMonth();
        $karyawan = $this->karyawan($request);
        $id = (int) $karyawan->id;

        $call = DB::table('trx_kunjungan')->where('karyawan_id', $id)->whereBetween('tanggal', [$awal->toDateString(), $akhir->toDateString()])
            ->get(['tanggal'])->countBy(fn ($k) => substr((string) $k->tanggal, 0, 10));
        $jual = DB::table('trx_penjualan')->where('karyawan_id', $id)->whereNull('dibatalkan_at')
            ->whereBetween('tanggal', [$awal->toDateString(), $akhir->toDateString()])
            ->get(['tanggal', 'total', 'poin', 'is_effective_call'])->groupBy(fn ($p) => substr((string) $p->tanggal, 0, 10));

        $hariHari = collect($call->keys())->merge($jual->keys())->unique()->sort()->values()->map(fn ($tgl) => [
            'date' => $tgl,
            'call' => (int) ($call[$tgl] ?? 0),
            'ec' => isset($jual[$tgl]) ? $jual[$tgl]->filter(fn ($p) => (bool) $p->is_effective_call)->count() : 0,
            'sales_amount' => isset($jual[$tgl]) ? (int) $jual[$tgl]->sum('total') : 0,
            'points' => isset($jual[$tgl]) ? (int) $jual[$tgl]->sum('poin') : 0,
        ]);

        $totalEc = $hariHari->sum('ec');
        $totalPoin = $hariHari->sum('points');

        return $this->ok([
            'month' => $bulan,
            'days' => $hariHari,
            'totals' => [
                'call' => $hariHari->sum('call'),
                'ec' => $totalEc,
                'sales_amount' => $hariHari->sum('sales_amount'),
                'points' => $totalPoin,
            ],
            'incentive_estimate' => [
                'ec_incentive' => $this->insentifEc($totalEc),
                'points_value' => $this->nilaiPoin($karyawan->depo_id ? (int) $karyawan->depo_id : null, $totalPoin),
                'note' => 'Perkiraan; nilai resmi dihitung admin.',
            ],
        ]);
    }

    public function sinkron(Request $request): JsonResponse
    {
        $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);
        $hari = $this->hari($request, $request->input('date'));
        $id = $hari->karyawanId;
        $absen = $hari->absensi();
        $depo = $hari->checkinDepo();
        $c = $hari->canvassing();
        $mulai = CarbonImmutable::parse($hari->tanggal, Pengaturan::zona())->utc();
        $ref = fn ($q) => $q->where('karyawan_id', $id)->whereNotNull('client_ref')->pluck('client_ref')->values();
        $tracking = DB::table('trx_tracking')->where('karyawan_id', $id)->whereBetween('recorded_at', [$mulai, $mulai->addDay()]);

        return $this->ok([
            'date' => $hari->tanggal,
            'attendance' => ['check_in' => $absen?->client_ref, 'check_out' => $absen?->pulang_client_ref],
            'depot_visit' => ['check_in' => $depo?->client_ref, 'check_out' => $depo?->checkout_client_ref],
            'canvassing' => $c ? ['number' => $c->nomor, 'accept' => $c->client_ref, 'return' => $c->return_client_ref] : null,
            'visits' => $ref(DB::table('trx_kunjungan')->where('tanggal', $hari->tanggal)),
            'sales' => $ref(DB::table('trx_penjualan')->where('tanggal', $hari->tanggal)),
            'new_outlets' => $ref(DB::table('trx_outlet_baru')->whereBetween('received_at', [$mulai, $mulai->addDay()])),
            'tracking' => ['points' => (clone $tracking)->count(), 'last_recorded_at' => $this->waktu((clone $tracking)->max('recorded_at'))],
        ], ['server_time' => $this->waktu(now())]);
    }

    /** Call, EC, IPT (rata-rata SKU per transaksi), omset, poin untuk rentang tanggal. */
    private function kpi(int $karyawanId, string $dari, string $sampai): array
    {
        $call = DB::table('trx_kunjungan')->where('karyawan_id', $karyawanId)->whereBetween('tanggal', [$dari, $sampai])->count();
        $jual = DB::table('trx_penjualan')->where('karyawan_id', $karyawanId)->whereNull('dibatalkan_at')
            ->whereBetween('tanggal', [$dari, $sampai])->get(['jumlah_sku', 'total', 'poin', 'is_effective_call']);

        return [
            'call' => $call,
            'ec' => $jual->filter(fn ($p) => (bool) $p->is_effective_call)->count(),
            'ipt' => $jual->isEmpty() ? 0 : round($jual->avg('jumlah_sku'), 1),
            'omset' => (int) $jual->sum('total'),
            'poin' => (int) $jual->sum('poin'),
        ];
    }

    private function outletBerikutnya(HariKerja $hari, Request $request): ?array
    {
        $status = $hari->statusOutlet();
        $titik = $request->filled('lat') ? [(float) $request->lat, (float) $request->lng] : null;

        if (! $titik && ($k = DB::table('trx_kunjungan')->where('karyawan_id', $hari->karyawanId)->where('tanggal', $hari->tanggal)->orderByDesc('id')->first())) {
            $titik = $k->lat !== null ? [(float) $k->lat, (float) $k->lng] : null;
        }

        $calon = DB::table('mk_outlet')->whereIn('area_id', $hari->areaRute() ?: [0])
            ->whereNotIn('id', array_keys($status) ?: [0])
            ->orderBy('id')->limit(500)->get(['id', 'kode', 'nama', 'alamat', 'latitude', 'longitude'])
            ->map(fn ($o) => [
                'id' => (int) $o->id,
                'code' => $o->kode,
                'name' => $o->nama,
                'address' => $o->alamat,
                'distance_m' => $titik && Lokasi::sah($o->latitude, $o->longitude)
                    ? Lokasi::jarak($titik[0], $titik[1], (float) $o->latitude, (float) $o->longitude) : null,
            ]);

        return $titik ? $calon->sortBy(fn ($o) => $o['distance_m'] ?? PHP_INT_MAX)->first() : $calon->first();
    }

    private function insentifEc(int $ec): ?int
    {
        return Insentif::ec($ec);
    }

    private function nilaiPoin(?int $depoId, int $poin): ?int
    {
        return Insentif::nilaiPoin($depoId ? DB::table('mk_depo')->where('id', $depoId)->value('nama') : null, $poin);
    }
}
