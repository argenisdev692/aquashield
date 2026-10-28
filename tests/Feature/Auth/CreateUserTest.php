<?php

declare(strict_types=1);

use App\Actions\Fortify\CreateNewUser;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * @return array<string, string>
 */
function createUserPayload(string $email = 'new.user@example.com'): array
{
    return [
        'first_name' => 'New',
        'last_name' => 'User',
        'email' => $email,
        'password' => '123456',
        'password_confirmation' => '123456',
    ];
}

it('records the authenticated creator when the creator can create users', function (): void {
    $admin = User::factory()->create();

    Permission::findOrCreate('CREATE_USER', 'web');
    $admin->givePermissionTo('CREATE_USER');

    $this->actingAs($admin);

    $created = app(CreateNewUser::class)->create(createUserPayload());

    expect($created->registered_by_id)->toBe($admin->id);
});

it('refuses an authenticated creator without the create-user permission', function (): void {
    $admin = User::factory()->create();

    $this->actingAs($admin);

    expect(fn () => app(CreateNewUser::class)->create(createUserPayload()))
        ->toThrow(AuthorizationException::class);

    $this->assertDatabaseMissing('users', ['email' => 'new.user@example.com']);
});

it('leaves the creator empty for public self-registration', function (): void {
    $created = app(CreateNewUser::class)->create(createUserPayload());

    expect($created->registered_by_id)->toBeNull();
});
