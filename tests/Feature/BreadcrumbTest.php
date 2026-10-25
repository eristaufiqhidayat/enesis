<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\LoginOtomatis;
use Tests\TestCase;

// Trail breadcrumb dihitung di Vue (Breadcrumb.vue, diuji di Breadcrumb.spec.js) dari menu & currentSlug;
// di sini yang diuji adalah slug yang dikirim tiap halaman.
class BreadcrumbTest extends TestCase
{
    use LoginOtomatis, RefreshDatabase;

    public function test_tracking_koordinator_menampilkan_breadcrumb_yang_benar(): void
    {
        // Breadcrumb dirender Topbar Vue dari prop slug, jadi yang diuji adalah slug halaman.
        $this->get('/tracking_koordinator_history_location')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('slug', 'tracking_koordinator_history_location'));
    }

    public function test_tracking_sales_menampilkan_breadcrumb_yang_benar(): void
    {
        $this->get('/tracking_sales_history_location')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('slug', 'tracking_sales_history_location'));
    }

    public function test_design_area_menampilkan_breadcrumb_master_area(): void
    {
        // Breadcrumb dirender Topbar Vue dari prop slug, jadi yang diuji adalah slug halaman.
        $this->get('/master_area/design_area')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('slug', 'master_area'));
    }
}
