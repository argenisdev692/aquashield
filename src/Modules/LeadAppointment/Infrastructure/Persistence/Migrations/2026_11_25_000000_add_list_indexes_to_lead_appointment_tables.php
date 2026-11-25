<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Default list and report sorts.
 *
 * `leads` sorts by `created_at` unless the operator picks a column, and the
 * source report groups by `source` inside a created_at window. `appointments`
 * defaults to `starts_at`; the existing composite indexes lead with status,
 * region or lead_id and cannot serve the unfiltered sort.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->index('created_at', 'leads_created_at_idx');
            $table->index(['source', 'created_at'], 'leads_source_created_idx');
        });

        Schema::table('appointments', function (Blueprint $table): void {
            $table->index('starts_at', 'appointments_starts_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->dropIndex('leads_created_at_idx');
            $table->dropIndex('leads_source_created_idx');
        });

        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropIndex('appointments_starts_at_idx');
        });
    }
};
