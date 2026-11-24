<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Technicians are `users` with the TECHNICAL_SERVICES role when the Claim +
 * Scope Sheet phase assigns them. This phase stops at the booked inspection,
 * so the parallel technicians table and every technician-scoped availability /
 * appointment column are removed.
 *
 * `up()` is guarded so a rollback + re-migrate on a partially migrated schema
 * cannot fail. `down()` is deliberately empty: the dropped rows cannot be
 * reconstructed, and the table returns with the assignment phase, not here.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('availability_exceptions')) {
            DB::statement('DROP INDEX IF EXISTS availability_exceptions_active_unique');

            if (Schema::hasColumn('availability_exceptions', 'technician_id')) {
                $this->dropForeignIfSupported('availability_exceptions', 'technician_id');
                DB::statement('DROP INDEX IF EXISTS availability_exceptions_scope_date_idx');

                Schema::table('availability_exceptions', function (Blueprint $table): void {
                    $table->dropColumn('technician_id');
                });
            }

            // Removing the technician scope can leave two rows for the same
            // date. Keep the most recently updated one; the rest are soft
            // deleted so the audit trail survives.
            $duplicates = DB::table('availability_exceptions')
                ->select('date')
                ->whereNull('deleted_at')
                ->groupBy('date')
                ->havingRaw('COUNT(*) > 1')
                ->pluck('date');

            foreach ($duplicates as $date) {
                $keepId = DB::table('availability_exceptions')
                    ->whereDate('date', (string) $date)
                    ->whereNull('deleted_at')
                    ->orderByDesc('updated_at')
                    ->orderByDesc('id')
                    ->value('id');

                DB::table('availability_exceptions')
                    ->whereDate('date', (string) $date)
                    ->whereNull('deleted_at')
                    ->where('id', '!=', $keepId)
                    ->update(['deleted_at' => now()]);
            }

            if (! $this->hasIndex('availability_exceptions_active_unique')) {
                DB::statement(
                    'CREATE UNIQUE INDEX availability_exceptions_active_unique
                     ON availability_exceptions (date)
                     WHERE deleted_at IS NULL',
                );
            }
        }

        if (Schema::hasTable('availability_rules')) {
            if (Schema::hasColumn('availability_rules', 'technician_id')) {
                $this->dropForeignIfSupported('availability_rules', 'technician_id');
                DB::statement('DROP INDEX IF EXISTS availability_rules_scope_day_idx');

                Schema::table('availability_rules', function (Blueprint $table): void {
                    $table->dropColumn('technician_id');
                });
            }

            if (! $this->hasIndex('availability_rules_day_idx')) {
                DB::statement(
                    'CREATE INDEX availability_rules_day_idx
                     ON availability_rules (day_of_week, is_available)',
                );
            }
        }

        if (Schema::hasTable('appointments') && Schema::hasColumn('appointments', 'technician_id')) {
            $this->dropForeignIfSupported('appointments', 'technician_id');
            DB::statement('DROP INDEX IF EXISTS appointments_technician_time_idx');

            Schema::table('appointments', function (Blueprint $table): void {
                $table->dropColumn('technician_id');
            });
        }

        Schema::dropIfExists('technicians');
    }

    public function down(): void
    {
        // Intentionally irreversible. Recreate the technicians table with the
        // assignment phase; the deleted rows have no source to restore from.
    }

    private function dropForeignIfSupported(string $table, string $column): void
    {
        // SQLite handles this by rebuilding the table in compileAlter(); the
        // explicit dropForeign command is what forces that path on 3.35+.
        Schema::table($table, function (Blueprint $blueprint) use ($column): void {
            $blueprint->dropForeign([$column]);
        });
    }

    /**
     * Laravel's schema builder has no portable "index exists" check across
     * SQLite and PostgreSQL, so ask the connection's catalog.
     */
    private function hasIndex(string $index): bool
    {
        if (DB::getDriverName() === 'sqlite') {
            return DB::table('sqlite_master')
                ->where('type', 'index')
                ->where('name', $index)
                ->exists();
        }

        return DB::table('pg_indexes')
            ->where('indexname', $index)
            ->exists();
    }
};
