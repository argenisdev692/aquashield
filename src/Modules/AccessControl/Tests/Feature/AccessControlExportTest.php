<?php

declare(strict_types=1);

use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\AccessControl\Tests\Support\AccessControlFixtures;
use Shared\Domain\Enums\Permission as PermissionName;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    AccessControlFixtures::seedPermissions();
    $this->admin = AccessControlFixtures::superAdmin();
});

it('exports role CSV with the list filter applied', function (): void {
    AccessControlFixtures::customRole('CLAIMS_REVIEWER');

    $response = $this->actingAs($this->admin)
        ->get('/roles/export/csv?filter[type]=custom')
        ->assertOk();

    $content = $response->streamedContent();

    expect($content)
        ->toContain('Name')
        ->toContain('CLAIMS_REVIEWER')
        ->not->toContain('SUPER_ADMIN');
});

it('exports role XLSX as a real ZIP container', function (): void {
    AccessControlFixtures::customRole('CLAIMS_REVIEWER');

    $content = $this->actingAs($this->admin)
        ->get('/roles/export/xlsx')
        ->assertOk()
        ->streamedContent();

    expect($content)->toStartWith('PK');
});

it('exports roles as PDF from the module Blade view', function (): void {
    AccessControlFixtures::customRole('CLAIMS_REVIEWER');

    $response = $this->actingAs($this->admin)
        ->get('/roles/export/pdf')
        ->assertOk();

    expect($response->getContent())->toStartWith('%PDF');
});

it('exports permission CSV with group and type filters', function (): void {
    $response = $this->actingAs($this->admin)
        ->get('/permissions/export/csv?'.http_build_query([
            'filter' => ['type' => 'system', 'group' => 'ROLE'],
        ]))
        ->assertOk();

    $content = $response->streamedContent();

    expect($content)
        ->toContain('CREATE_ROLE')
        ->toContain('VIEW_ANY_ROLE')
        ->not->toContain('VIEW_ANY_PERMISSION');
});

it('forbids an export without the export permission', function (): void {
    Role::findByName('SUPER_ADMIN', 'web')
        ->revokePermissionTo(PermissionName::ExportRole->value);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($this->admin)
        ->get('/roles/export/csv')
        ->assertForbidden();
});

it('forbids an export without the view permission', function (): void {
    Role::findByName('SUPER_ADMIN', 'web')
        ->revokePermissionTo(PermissionName::ViewAnyRole->value);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($this->admin)
        ->get('/roles/export/csv')
        ->assertForbidden();
});

it('audits both export surfaces', function (): void {
    // The audit row is written when the rows are produced, so the streamed
    // response has to be consumed for the assertion to be meaningful.
    $this->actingAs($this->admin)
        ->get('/roles/export/csv')
        ->assertOk()
        ->streamedContent();

    $this->actingAs($this->admin)
        ->get('/permissions/export/csv')
        ->assertOk()
        ->streamedContent();

    $this->assertDatabaseHas('activity_log', [
        'event' => 'role.exported',
        'log_name' => 'access-control',
    ]);
    $this->assertDatabaseHas('activity_log', [
        'event' => 'permission.exported',
        'log_name' => 'access-control',
    ]);
});

it('rejects a permission PDF export above its lower cap', function (): void {
    $rows = [];

    for ($i = 0; $i < 1_991; $i++) {
        $rows[] = [
            'name' => 'LOAD_TEST_'.$i,
            'guard_name' => 'web',
            'is_system' => false,
            'uuid' => sprintf('00000000-0000-7000-8000-%012d', $i),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    foreach (array_chunk($rows, 500) as $chunk) {
        DB::table('permissions')->insert($chunk);
    }

    // 10 seeded + 1,991 = 2,001 > PDF cap of 2,000.
    $this->actingAs($this->admin)
        ->get('/permissions/export/pdf')
        ->assertStatus(422);
});
