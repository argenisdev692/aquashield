<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Server-side memory for the "add a passkey" prompt.
 *
 * Client storage would make the dismissal per-device, which is exactly wrong:
 * the prompt exists to be seen once across sessions, not once per browser.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('passkey_prompt_dismissed_at')->nullable()->after('email_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('passkey_prompt_dismissed_at');
        });
    }
};
