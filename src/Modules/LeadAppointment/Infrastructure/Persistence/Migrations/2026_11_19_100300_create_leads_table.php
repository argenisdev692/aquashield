<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->string('first_name');
            $table->string('last_name');
            $table->string('company_name')->nullable();

            $table->string('email')->nullable();
            $table->string('phone', 32)->nullable();
            $table->text('address')->nullable();
            $table->text('address_2')->nullable();
            $table->string('city')->nullable();
            $table->string('state', 2)->nullable();
            $table->string('zip_code', 10)->nullable();
            $table->string('country', 2)->default('US');
            $table->string('region', 20);

            $table->string('source', 20);
            $table->string('source_detail', 100)->nullable();
            $table->string('external_source', 20)->nullable();
            $table->string('external_id', 100)->nullable();

            $table->string('status', 30)->default('new');
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('duplicate_of_id')->nullable()->constrained('leads')->nullOnDelete();

            $table->boolean('is_emergency')->default(false);
            $table->string('preferred_language', 5)->default('en');
            $table->text('damage_description')->nullable();
            $table->string('insurance_carrier')->nullable();
            $table->string('claim_number')->nullable();
            $table->json('attribution')->nullable();

            $table->boolean('is_spam')->default(false);
            $table->unsignedSmallInteger('spam_score')->default(0);
            $table->json('spam_reasons')->nullable();

            $table->boolean('sms_consent')->default(false);
            $table->timestamp('sms_consent_at')->nullable();
            $table->string('sms_consent_ip', 45)->nullable();
            $table->string('sms_consent_user_agent', 500)->nullable();
            $table->string('sms_consent_source')->nullable();
            $table->string('consent_text_version', 20)->nullable();
            $table->timestamp('opted_out_at')->nullable();

            $table->timestamp('last_contacted_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->text('notes')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['status', 'created_at'], 'leads_status_created_idx');
            $table->index(['region', 'status'], 'leads_region_status_idx');
            $table->index('email', 'leads_email_idx');
            $table->index('phone', 'leads_phone_idx');
            $table->index(['owner_id', 'status'], 'leads_owner_status_idx');
            $table->index('duplicate_of_id', 'leads_duplicate_idx');
            $table->index('last_contacted_at', 'leads_last_contact_idx');
            $table->index(['deleted_at', 'created_at'], 'leads_deleted_created_idx');
        });

        // One Meta/Retell event may create one lead. Website leads have no
        // external id; soft-deleted rows are ignored so a lead can be re-captured.
        DB::statement(
            'CREATE UNIQUE INDEX leads_external_unique
             ON leads (external_source, external_id)
             WHERE external_id IS NOT NULL AND deleted_at IS NULL',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
