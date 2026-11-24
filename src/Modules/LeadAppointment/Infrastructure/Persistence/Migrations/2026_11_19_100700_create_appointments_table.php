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
        Schema::create('appointments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->foreignId('technician_id')->nullable()->constrained('technicians')->nullOnDelete();
            $table->string('kind', 20)->default('inspection');
            $table->string('status', 20)->default('requested');
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->string('timezone', 64);
            $table->timestampTz('previous_starts_at')->nullable();
            $table->timestampTz('previous_ends_at')->nullable();
            $table->string('region', 20);
            $table->string('google_event_id')->nullable();
            $table->string('cancel_reason', 500)->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['technician_id', 'starts_at', 'ends_at'], 'appointments_technician_time_idx');
            $table->index(['status', 'starts_at'], 'appointments_status_time_idx');
            $table->index(['lead_id', 'starts_at'], 'appointments_lead_time_idx');
            $table->index(['region', 'starts_at'], 'appointments_region_time_idx');
            $table->index(['deleted_at', 'created_at'], 'appointments_deleted_created_idx');
        });

        DB::statement(
            'CREATE UNIQUE INDEX appointments_google_event_unique
             ON appointments (google_event_id)
             WHERE google_event_id IS NOT NULL',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
