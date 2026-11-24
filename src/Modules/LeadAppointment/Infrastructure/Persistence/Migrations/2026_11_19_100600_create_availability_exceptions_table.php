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
        Schema::create('availability_exceptions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('technician_id')->nullable()->constrained('technicians')->cascadeOnDelete();
            $table->date('date');
            $table->boolean('is_available')->default(false);
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('reason')->nullable();
            $table->string('source', 20)->default('manual');
            $table->softDeletes();
            $table->timestamps();

            $table->index(['technician_id', 'date'], 'availability_exceptions_scope_date_idx');
            $table->index(['deleted_at', 'date'], 'availability_exceptions_deleted_date_idx');
        });

        // One active override per (technician, date); NULL is the company scope.
        // The COALESCE expression index is valid on PostgreSQL and SQLite.
        DB::statement(
            'CREATE UNIQUE INDEX availability_exceptions_active_unique
             ON availability_exceptions (COALESCE(technician_id, 0), date)
             WHERE deleted_at IS NULL',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('availability_exceptions');
    }
};
