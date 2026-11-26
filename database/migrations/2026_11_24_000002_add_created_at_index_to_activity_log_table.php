<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The audit list defaults to `created_at desc` and filters on a created_at
 * window. Without an index both are full scans once the log grows.
 * ActivityListQuery uses the range predicate (not `whereDate()`) so the
 * planner can use this index.
 */
return new class extends Migration
{
    /**
     * An index build must not hold a transaction open around it; each
     * statement commits on its own.
     */
    public $withinTransaction = false;

    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS activity_log_created_at_index ON activity_log (created_at)');

            return;
        }

        Schema::table('activity_log', function (Blueprint $table): void {
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table): void {
            $table->dropIndex(['created_at']);
        });
    }
};
