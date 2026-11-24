<?php

declare(strict_types=1);

use Database\Seeders\RolePermissionSeeder;
use Shared\Domain\Enums\Permission as PermissionName;
use Tests\TestCase;

uses(TestCase::class);

it('seeds every users permission from the shared enum', function (): void {
    $seeder = new RolePermissionSeeder;
    $method = new ReflectionMethod($seeder, 'permissionNames');

    /** @var list<string> $names */
    $names = $method->invoke($seeder);

    foreach (PermissionName::cases() as $permission) {
        expect($names)->toContain($permission->value);
    }
});
