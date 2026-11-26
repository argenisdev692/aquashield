<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * DDL and the per-row backfill must not share one transaction: on Postgres
     * the ADD COLUMN lock would be held for the whole backfill, blocking every
     * audit write. Each statement commits on its own.
     */
    public $withinTransaction = false;

    public function up(): void
    {
        Schema::table('activity_log', function (Blueprint $table): void {
            // The panel must never route on the auto-increment id: it is
            // enumerable and this project treats every public identifier as a
            // UUID. Existing rows are backfilled below.
            $table->uuid('uuid')->nullable();
        });

        DB::table('activity_log')
            ->whereNull('uuid')
            ->orderBy('id')
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('activity_log')
                        ->where('id', $row->id)
                        ->update(['uuid' => (string) Str::uuid7()]);
                }
            });

        // Build the unique index after the backfill, concurrently on Postgres,
        // so the index build does not block audit writes.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX CONCURRENTLY IF NOT EXISTS activity_log_uuid_unique ON activity_log (uuid)');
        } else {
            Schema::table('activity_log', function (Blueprint $table): void {
                $table->unique('uuid');
            });
        }
    }

    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table): void {
            $table->dropUnique(['uuid']);
            $table->dropColumn('uuid');
        });
    }
};
