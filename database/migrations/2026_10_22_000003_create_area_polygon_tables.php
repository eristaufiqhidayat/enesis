<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Polygon area dan lokasi depo untuk halaman Design Polygon Area.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('area_polygon', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('source_id')->nullable()->index();
            $table->string('code')->unique();
            $table->string('name');
            $table->json('polygon');
            $table->string('warehouse')->nullable();
            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->string('branch_area')->nullable();
            $table->unsignedBigInteger('branch_area_id')->nullable();
            $table->string('province')->nullable()->index();
            $table->unsignedBigInteger('province_id')->nullable();
            $table->string('city')->nullable();
            $table->unsignedBigInteger('city_id')->nullable();
            $table->string('district')->nullable();
            $table->unsignedBigInteger('district_id')->nullable();
            $table->string('village')->nullable();
            $table->unsignedBigInteger('village_id')->nullable();
            $table->text('info')->nullable();
            $table->unsignedInteger('outlet_count')->nullable();
            $table->string('salesperson')->nullable();
        });

        Schema::create('area_depo', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('warehouse_id')->unique();
            $table->string('name');
            $table->string('province')->nullable()->index();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('area_depo');
        Schema::dropIfExists('area_polygon');
    }
};
