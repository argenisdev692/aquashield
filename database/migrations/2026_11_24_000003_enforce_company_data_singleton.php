<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Makes the singleton a database invariant on databases created before the
 * FLAT hardening pass.
 *
 * The migration deliberately refuses to delete rows: if more than one company
 * profile exists, a human resolves the duplicates first.
 */
return new class extends Migration
{
    public function up(): void
    {
        $count = DB::table('company_data')->count();

        if ($count > 1) {
            throw new RuntimeException(
                "company_data must hold at most one row; found {$count}. "
                .'Resolve the duplicates before running this migration.',
            );
        }

        if (Schema::hasColumn('company_data', 'deleted_at')) {
            Schema::table('company_data', static function (Blueprint $table): void {
                $table->dropSoftDeletes();
            });
        }

        if (! Schema::hasColumn('company_data', 'singleton')) {
            Schema::table('company_data', static function (Blueprint $table): void {
                $table->boolean('singleton')->default(true)->unique();
            });
        }

        // Existing PostgreSQL/MySQL installs may still have cascadeOnDelete
        // from the old create migration; deleting the owner must not silently
        // hard-delete the singleton. SQLite tests get the corrected create
        // migration and cannot drop a foreign key in place.
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('company_data', static function (Blueprint $table): void {
                $table->dropForeign(['user_id']);
                $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('company_data', 'singleton')) {
            Schema::table('company_data', static function (Blueprint $table): void {
                $table->dropUnique(['singleton']);
                $table->dropColumn('singleton');
            });
        }
    }
};
