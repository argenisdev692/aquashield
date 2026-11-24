<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Region is optional until the Google Maps autocomplete contract is settled.
 * The lead is still accepted without a metro; scheduling falls back to the
 * technician's region.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->string('region', 20)->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('leads')->whereNull('region')->update(['region' => 'houston']);

        Schema::table('leads', function (Blueprint $table): void {
            $table->string('region', 20)->nullable(false)->change();
        });
    }
};
