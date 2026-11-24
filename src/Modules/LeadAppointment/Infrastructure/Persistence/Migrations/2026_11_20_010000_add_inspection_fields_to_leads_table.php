<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inspection fields carried by the legacy lead table. They are the operator's
 * requested/recorded inspection; the confirmed visit still lives on
 * `appointments`. `LeadData` prefers the confirmed appointment when one exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->date('inspection_date')->nullable()->after('follow_up_date');
            $table->time('inspection_time')->nullable()->after('inspection_date');
            $table->string('inspection_status', 20)->nullable()->after('inspection_time');

            $table->index('inspection_date', 'leads_inspection_date_idx');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->dropIndex('leads_inspection_date_idx');
            $table->dropColumn(['inspection_date', 'inspection_time', 'inspection_status']);
        });
    }
};
