<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\CompanyData\Models\CompanyData;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('rejects a singleton=false row at the database boundary', function (): void {
    $owner = User::factory()->create();

    $first = new CompanyData;
    $first->fill(['company_name' => 'First']);
    $first->user_id = $owner->id;
    $first->save();

    $second = new CompanyData;
    $second->fill(['company_name' => 'Second']);
    $second->user_id = $owner->id;
    $second->singleton = false;

    expect(fn () => $second->save())->toThrow(QueryException::class);
});
