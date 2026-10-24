<?php

namespace Tests\Concerns;

use App\Models\User;

// Semua halaman aplikasi wajib login: test yang membuka halaman memakai trait ini supaya
// setiap test berjalan sebagai user aktif (dipanggil otomatis oleh setUpTraits Laravel).
trait LoginOtomatis
{
    protected function setUpLoginOtomatis(): void
    {
        $this->actingAs(User::factory()->make(['id' => 1, 'aktif' => true, 'wajib_ganti_password' => false]));
    }
}
