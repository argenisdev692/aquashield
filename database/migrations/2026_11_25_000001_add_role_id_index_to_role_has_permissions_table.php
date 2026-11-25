<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The role list counts permissions per role (`withCount('permissions')`), and
 * the pivot's primary key is `(permission_id, role_id)` — `role_id` is not a
 * leading column. PostgreSQL does not create an index for a foreign key
 * automatically, so the correlated count scanned the pivot for every role.
 *
 * MySQL may already have an index from the FK; `hasIndex` keeps this additive
 * and idempotent across engines.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = (string) config('permission.table_names.role_has_permissions', 'role_has_permissions');

        if (! Schema::hasIndex($table, ['role_id'])) {
            Schema::table($table, static function (Blueprint $blueprint): void {
                $blueprint->index('role_id');
            });
        }
    }

    public function down(): void
    {
        $table = (string) config('permission.table_names.role_has_permissions', 'role_has_permissions');

        if (Schema::hasIndex($table, ['role_id'])) {
            Schema::table($table, static function (Blueprint $blueprint): void {
                $blueprint->dropIndex(['role_id']);
            });
        }
    }
};
