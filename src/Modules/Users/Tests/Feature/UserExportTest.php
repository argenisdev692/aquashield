<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Users\Tests\Support\UserTestFixtures;
use Shared\Domain\Enums\Permission as PermissionName;
use Shared\Domain\Enums\Role as RoleName;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    UserTestFixtures::seedPermissions();
    $this->admin = UserTestFixtures::superAdmin();
});

it('exports CSV with the list filters applied', function (): void {
    User::factory()->create(['email' => 'ada@example.com', 'first_name' => 'Ada']);
    // Pin every searchable column: a random Faker last name or username can
    // contain "ada" and make this assertion fail intermittently.
    User::factory()->create([
        'email' => 'bob@example.com',
        'first_name' => 'Bob',
        'last_name' => 'Barker',
        'username' => 'bob.barker',
    ]);

    $response = $this->actingAs($this->admin)
        ->get('/users/export/csv?search=ada')
        ->assertOk();

    $content = $response->streamedContent();

    expect($content)
        ->toContain('First name')
        ->toContain('ada@example.com')
        ->not->toContain('bob@example.com');
});

it('exports XLSX as a real ZIP container', function (): void {
    User::factory()->create();

    $content = $this->actingAs($this->admin)
        ->get('/users/export/xlsx')
        ->assertOk()
        ->streamedContent();

    expect($content)->toStartWith('PK');
});

it('exports PDF rendered from the module Blade view', function (): void {
    User::factory()->create();

    $response = $this->actingAs($this->admin)
        ->get('/users/export/pdf')
        ->assertOk();

    expect($response->getContent())->toStartWith('%PDF');
});

it('exports the account status column and applies the account status filter', function (): void {
    User::factory()->invited()->create(['email' => 'invited@example.com']);
    User::factory()->create(['email' => 'active@example.com']);

    $content = $this->actingAs($this->admin)
        ->get('/users/export/csv?'.http_build_query([
            'filter' => ['account_status' => 'invited'],
        ]))
        ->assertOk()
        ->streamedContent();

    expect($content)
        ->toContain('Account status')
        ->toContain('invited@example.com')
        ->not->toContain('active@example.com');
});

it('forbids an export without EXPORT_USER', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/users/export/csv')
        ->assertForbidden();
});

it('forbids a super admin who lacks EXPORT_USER', function (): void {
    // The route group's `can:manage-staff` passes for this actor, so the 403
    // can only come from the fine-grained `permission:EXPORT_USER` check.
    Role::findByName(RoleName::SuperAdmin->value, 'web')
        ->revokePermissionTo(PermissionName::ExportUser->value);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($this->admin)
        ->get('/users/export/csv')
        ->assertForbidden();
});

it('forbids a super admin who lacks VIEW_ANY_USER from exporting', function (): void {
    Role::findByName(RoleName::SuperAdmin->value, 'web')
        ->revokePermissionTo(PermissionName::ViewAnyUser->value);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($this->admin)
        ->get('/users/export/csv')
        ->assertForbidden();
});

it('neutralises spreadsheet formula prefixes in exported cells', function (): void {
    User::factory()->create([
        'first_name' => '=cmd|calc',
        'last_name' => null,
        'username' => 'formula',
        'email' => 'formula@example.com',
    ]);

    $content = $this->actingAs($this->admin)
        ->get('/users/export/csv')
        ->assertOk()
        ->streamedContent();

    expect($content)->toContain("'=cmd|calc");
});

it('answers 401 to a guest JSON export request', function (): void {
    $this->getJson('/users/export/csv')->assertUnauthorized();
});

it('refuses an export above the row cap with 422 instead of truncating', function (): void {
    $now = now();

    // 10 000 minimal rows (no bcrypt work) + the admin from beforeEach = the
    // first count above MAX_ROWS. Inserted in chunks to stay under SQLite's
    // bound-parameter limit.
    for ($chunk = 0; $chunk < 100; $chunk++) {
        $rows = [];

        for ($i = 0; $i < 100; $i++) {
            $index = $chunk * 100 + $i;

            $rows[] = [
                'uuid' => (string) Str::uuid7(),
                'first_name' => 'Bulk',
                'email' => 'bulk'.$index.'@example.com',
                'password' => 'x',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('users')->insert($rows);
    }

    $this->actingAs($this->admin)
        ->getJson('/users/export/csv')
        ->assertUnprocessable();
});
