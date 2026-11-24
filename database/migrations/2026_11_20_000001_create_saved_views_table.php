<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Named table views, scoped to their owner.
 *
 * Server-side on purpose: a view can contain a search string (often a name or
 * an email) and must follow the account, not the browser. `localStorage` is
 * readable by any script on the origin and is wiped by clearing site data;
 * ownership and validation belong to the server.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_views', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('resource', 32);
            $table->string('name', 60);
            $table->string('search', 255)->nullable();
            $table->string('sort_field', 64)->nullable();
            $table->tinyInteger('sort_order')->default(1);
            $table->unsignedSmallInteger('per_page')->default(15);
            $table->json('filters');
            $table->timestamps();

            // One name per screen per user: saving the same name again updates
            // the view instead of failing on a constraint.
            $table->unique(['user_id', 'resource', 'name']);
            $table->index(['user_id', 'resource']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_views');
    }
};
