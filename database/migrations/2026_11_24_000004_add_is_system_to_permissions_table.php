<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the explicit system/custom boundary for the permission catalogue.
 *
 * Every row that exists before this migration came from RolePermissionSeeder,
 * so it is backfilled as system. Rows created through the AccessControl module
 * are custom and may be renamed/deleted while unassigned.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('permissions', 'is_system')) {
            Schema::table('permissions', static function (Blueprint $table): void {
                $table->boolean('is_system')->default(false);
            });
        }

        DB::table('permissions')->update(['is_system' => true]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('permissions', 'is_system')) {
            Schema::table('permissions', static function (Blueprint $table): void {
                $table->dropColumn('is_system');
            });
        }
    }
};
