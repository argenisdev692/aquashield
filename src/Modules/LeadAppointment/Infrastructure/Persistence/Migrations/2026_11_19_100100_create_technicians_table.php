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
        Schema::create('technicians', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('region', 20);
            $table->string('phone', 32)->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('daily_capacity')->default(4);
            $table->unsignedSmallInteger('travel_buffer_minutes')->default(30);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['region', 'is_active'], 'technicians_region_active_idx');
            $table->index(['deleted_at', 'created_at'], 'technicians_deleted_created_idx');
        });

        DB::statement(
            'CREATE UNIQUE INDEX technicians_user_unique
             ON technicians (user_id)
             WHERE user_id IS NOT NULL AND deleted_at IS NULL',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('technicians');
    }
};
