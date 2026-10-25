<?php

namespace Tests\Feature\Mobile;

use App\Models\User;
use App\Support\Outlet\VerifikasiOutletBaru;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

// Alur ujung ke ujung: sales menambah outlet dari HP → tampil di web (All Outlet, tab Outlet Baru dari HP)
// → admin menyetujui → outlet masuk Master Outlet di web dan rute HP (routes/today) sales.
class AlurOutletBaruWebTest extends MobileApiTestCase
{
    private function web(string $method, string $uri, array $data = [])
    {
        $this->app['auth']->forgetGuards();
        $admin = User::where('username', 'admin')->first()
            ?? User::factory()->create(['username' => 'admin', 'kategori' => null, 'role_id' => null, 'aktif' => true, 'wajib_ganti_password' => false]);

        return $this->actingAs($admin, 'web')->call($method, $uri, $data);
    }

    public function test_outlet_dari_hp_tampil_di_web_dan_setelah_disetujui_masuk_rute_hp(): void
    {
        $this->sales();
        $this->login();

        // Web dan HP sama-sama membaca mk_outlet: 3 outlet master, 2 di antaranya di area rute sales.
        $this->web('GET', '/outlet_all')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $page) => $page->where('tables.0.name', 'mk_outlet')->where('tables.0.rows.total', 3)));
        $this->assertSame([11, 12], array_column($this->api('GET', 'routes/today')->assertOk()->json('data'), 'id'));

        // 1. Sales menambah outlet dari HP.
        $foto = $this->foto('toko');
        $baru = $this->api('POST', 'outlets', [
            'name' => 'Warung Bu Sri', 'phone' => '081234567890', 'photo_store_id' => $foto, 'lat' => -6.25, 'lng' => 106.85, 'accuracy_m' => 10,
        ])->assertCreated()->json('data.new_outlet_id');

        // 2. Langsung tampil di web, tab Outlet Baru dari HP, status menunggu; belum masuk master & rute HP.
        $this->web('GET', '/outlet_all?tab=baru')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps(fn (Assert $page) => $page
                    ->where('tables.0.rows.total', 1)
                    ->where('tables.0.rows.data.0.id', $baru)
                    ->where('tables.0.rows.data.0.nama', 'Warung Bu Sri')
                    ->where('tables.0.rows.data.0.status', 'menunggu')
                    ->where('tables.0.rows.data.0.sales', 'Andi Motoris')
                    ->where('tables.0.rows.data.0.foto_toko_id', $foto)
                )
            );
        $this->assertSame(3, DB::table('mk_outlet')->count());

        // 3. Admin menyetujui di web.
        $this->web('POST', "/outlet_all/baru/{$baru}/setujui")->assertSessionHas('sukses');
        $outletId = VerifikasiOutletBaru::ID_AWAL;

        // 4. Masuk Master Outlet di web dan rute HP sales (area rute sama).
        $this->web('GET', '/outlet_all?q=Bu+Sri')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps(fn (Assert $page) => $page
                    ->where('tables.0.rows.total', 1)
                    ->where('tables.0.rows.data.0.id', $outletId)
                    ->where('tables.0.rows.data.0.area', 'Area Rute')
                )
            );
        $rute = $this->api('GET', 'routes/today')->assertOk()->json('data');
        $this->assertSame([11, 12, $outletId], array_column($rute, 'id'));
        $this->assertSame('NOO'.str_pad((string) $baru, 6, '0', STR_PAD_LEFT), $rute[2]['code']);
    }
}
