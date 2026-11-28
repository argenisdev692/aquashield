<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduling_locks', function (Blueprint $table): void {
            $table->string('key')->primary();
        });

        DB::table('scheduling_locks')->insert(['key' => 'company']);
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduling_locks');
    }
};
