<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Index nama area di mk_outlet untuk langkah "marketama:tarik sambung" (isi area_id).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mk_outlet', function (Blueprint $table) {
            $table->index('area');
        });
    }

    public function down(): void
    {
        Schema::table('mk_outlet', function (Blueprint $table) {
            $table->dropIndex(['area']);
        });
    }
};
