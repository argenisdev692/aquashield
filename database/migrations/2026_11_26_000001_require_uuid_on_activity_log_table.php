<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * The panel addresses audit rows by `uuid`; a null one is invisible to
 * `GET /activity/{uuid}`. The column was added nullable so the backfill could
 * run on a populated table — this migration adds the constraint that makes
 * "uuid is the only identifier that crosses HTTP" true at the schema level.
 */
return new class extends Migration
{
    /**
     * The defensive backfill and SET NOT NULL must not share one transaction
     * with the column change; each statement commits on its own.
     */
    public $withinTransaction = false;

    public function up(): void
    {
        // Defensive: a row written outside the configured ActivityEntry model
        // since the previous backfill is filled before the constraint lands.
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

        if (DB::getDriverName() === 'pgsql') {
            // A validated CHECK proves NOT NULL without the ACCESS EXCLUSIVE
            // scan a bare SET NOT NULL would take on a large table.
            DB::statement('ALTER TABLE activity_log ADD CONSTRAINT activity_log_uuid_not_null_check CHECK (uuid IS NOT NULL) NOT VALID');
            DB::statement('ALTER TABLE activity_log VALIDATE CONSTRAINT activity_log_uuid_not_null_check');
            DB::statement('ALTER TABLE activity_log ALTER COLUMN uuid SET NOT NULL');
            DB::statement('ALTER TABLE activity_log DROP CONSTRAINT activity_log_uuid_not_null_check');

            return;
        }

        Schema::table('activity_log', function (Blueprint $table): void {
            $table->uuid('uuid')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table): void {
            $table->uuid('uuid')->nullable()->change();
        });
    }
};
