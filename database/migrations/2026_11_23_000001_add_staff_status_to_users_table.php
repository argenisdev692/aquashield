<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Staff lifecycle state for administrator-managed accounts.
     *
     * `invited` is the onboarding state: the account exists, has no usable
     * credential and cannot sign in until the signed invitation is consumed.
     * Existing rows are active by definition — they predate onboarding.
     *
     * `invitation_sent_at` doubles as the single-use marker: the signed URL
     * carries its timestamp, and a resend rewrites the column, which makes the
     * previous link fail even though its signature is still cryptographically
     * valid.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('status', 20)->default('active')->index();
            $table->timestamp('invitation_sent_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->boolean('must_change_password')->default(false);
        });

        DB::table('users')
            ->whereNull('activated_at')
            ->update(['activated_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['status']);
            $table->dropColumn([
                'status',
                'invitation_sent_at',
                'activated_at',
                'suspended_at',
                'must_change_password',
            ]);
        });
    }
};
