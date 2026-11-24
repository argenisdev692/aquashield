<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Adds the public UUIDv7 to the Spatie tables without touching their primary keys.
 *
 * The pivot columns (`model_has_roles.role_id`, `role_has_permissions.role_id`,
 * `role_has_permissions.permission_id`, `model_has_permissions.permission_id`)
 * keep referencing `id`; `uuid` is the identifier that crosses HTTP. Existing
 * rows are backfilled before the column becomes non-null and unique.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['roles', 'permissions'] as $tableName) {
            if (! Schema::hasColumn($tableName, 'uuid')) {
                Schema::table($tableName, static function (Blueprint $table): void {
                    $table->uuid('uuid')->nullable()->after('id');
                });
            }

            $rows = DB::table($tableName)
                ->whereNull('uuid')
                ->orderBy('id')
                ->get()
                ->map(static function (object $row): array {
                    $data = (array) $row;
                    $data['id'] = (int) $data['id'];
                    $data['uuid'] = (string) Str::uuid7();

                    return $data;
                })
                ->all();

            DB::transaction(static function () use ($rows, $tableName): void {
                foreach (array_chunk($rows, 100) as $chunk) {
                    DB::table($tableName)->upsert($chunk, ['id'], ['uuid']);
                }
            });

            Schema::table($tableName, static function (Blueprint $table): void {
                $table->uuid('uuid')->nullable(false)->change();
            });

            Schema::table($tableName, static function (Blueprint $table): void {
                $table->unique('uuid');
            });
        }

        // Cached Role/Permission objects created before the column existed have no uuid.
        Cache::forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        foreach (['roles', 'permissions'] as $tableName) {
            Schema::table($tableName, static function (Blueprint $table): void {
                $table->dropUnique(['uuid']);
                $table->dropColumn('uuid');
            });
        }
    }
};
