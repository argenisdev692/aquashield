<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            /*
             * Durable brute-force state. The rate limiter (5/min) is the outer
             * bound on a burst; these columns are the account-level penalty:
             * five wrong passwords in a row lock the account for an hour.
             *
             * The counter is reset to zero when the lock is applied, so the
             * user gets a clean five-attempt budget once the hour elapses.
             */
            $table->unsignedTinyInteger('failed_login_attempts')->default(0)->after('password');
            $table->timestamp('locked_until')->nullable()->after('failed_login_attempts')->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['failed_login_attempts', 'locked_until']);
        });
    }
};
