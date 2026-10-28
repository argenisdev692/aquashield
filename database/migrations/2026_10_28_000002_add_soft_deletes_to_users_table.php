<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Soft deletes are the Users module's lifecycle primitive: a removed
     * account must stay restorable (and keep its role/permission rows) instead
     * of cascading through the audit trail. The composite index matches the
     * list query, which filters on `deleted_at` and sorts by `created_at`.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->softDeletes();
            $table->index(['deleted_at', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['deleted_at', 'created_at']);
            $table->dropSoftDeletes();
        });
    }
};
