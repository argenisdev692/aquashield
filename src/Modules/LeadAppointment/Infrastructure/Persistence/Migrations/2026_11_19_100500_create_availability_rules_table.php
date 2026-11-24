<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('availability_rules', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('technician_id')->nullable()->constrained('technicians')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_available')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['technician_id', 'day_of_week', 'is_available'], 'availability_rules_scope_day_idx');
            $table->index(['deleted_at', 'created_at'], 'availability_rules_deleted_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('availability_rules');
    }
};
