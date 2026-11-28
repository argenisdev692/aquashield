<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * SQLite rewrites the whole table for `->change()` and `dropForeign`, and its
 * index recreation loses the partial-index predicate. PostgreSQL keeps the
 * index as declared. Recreate it at the end of the migration chain so a local
 * SQLite database matches the production PostgreSQL contract: one ACTIVE lead
 * per (external_source, external_id); a soft-deleted lead can be re-captured.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP INDEX IF EXISTS leads_external_unique');
        DB::statement(
            'CREATE UNIQUE INDEX leads_external_unique
             ON leads (external_source, external_id)
             WHERE external_id IS NOT NULL AND deleted_at IS NULL',
        );
    }

    public function down(): void
    {
        // The index is the same shape the original create migration declares.
        // Dropping it would weaken the invariant on rollback, so this stays a
        // no-op by design.
    }
};
