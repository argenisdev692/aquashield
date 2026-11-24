<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_ingestion_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('provider', 20);
            $table->string('external_id', 100);
            $table->json('payload');
            $table->string('status', 20)->default('received');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'external_id'], 'lead_ingestion_events_unique');
            $table->index(['status', 'created_at'], 'lead_ingestion_events_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_ingestion_events');
    }
};
