<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Qualification and intake fields carried by the legacy
 * `TABLA-APPOINMENT-AQUASHIELD` table. Inspection date/time/status are
 * deliberately NOT copied here: they live on `appointments` and are read from
 * the lead's latest appointment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->boolean('insurance_property')->default(false)->after('country');
            $table->text('message')->nullable()->after('damage_description');
            $table->boolean('intent_to_claim')->nullable()->after('message');
            $table->date('follow_up_date')->nullable()->after('intent_to_claim');
            $table->text('additional_note')->nullable()->after('notes');
            $table->double('latitude')->nullable();
            $table->double('longitude')->nullable();

            $table->index('follow_up_date', 'leads_follow_up_date_idx');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->dropIndex('leads_follow_up_date_idx');
            $table->dropColumn([
                'insurance_property',
                'message',
                'intent_to_claim',
                'follow_up_date',
                'additional_note',
                'latitude',
                'longitude',
            ]);
        });
    }
};
