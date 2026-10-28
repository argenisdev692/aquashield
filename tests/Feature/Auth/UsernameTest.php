<?php

declare(strict_types=1);

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

/**
 * @return array<string, string>
 */
function usernamePayload(string $username, string $email = 'username@example.com'): array
{
    return [
        'first_name' => 'Test',
        'last_name' => 'User',
        'username' => $username,
        'email' => $email,
        'password' => '123456',
        'password_confirmation' => '123456',
    ];
}

it('accepts and normalizes a valid username', function (string $username, string $expected): void {
    $user = app(CreateNewUser::class)->create(usernamePayload($username));

    expect($user->username)->toBe($expected);
})->with([
    'three characters' => ['abc', 'abc'],
    'dot separator' => ['a.b', 'a.b'],
    'underscore separator' => ['a_b', 'a_b'],
    'dash separator' => ['a-b', 'a-b'],
    'mixed case' => ['John.Doe', 'john.doe'],
    'twenty characters' => ['abcdefghijklmnopqrst', 'abcdefghijklmnopqrst'],
    'starts with a number' => ['123abc', '123abc'],
]);

it('rejects an invalid username', function (string $username): void {
    expect(fn () => app(CreateNewUser::class)->create(usernamePayload($username)))
        ->toThrow(ValidationException::class);
})->with([
    'too short' => ['ab'],
    'too long' => ['abcdefghijklmnopqrstu'],
    'leading separator' => ['.john'],
    'trailing separator' => ['john.'],
    'consecutive dots' => ['john..doe'],
    'consecutive dashes' => ['john--doe'],
    'consecutive underscores' => ['john__doe'],
    'space' => ['john doe'],
    'at sign' => ['john@doe'],
    'slash' => ['john/doe'],
    'only numbers' => ['123'],
    'reserved admin' => ['admin'],
    'reserved admin mixed case' => ['ADMIN'],
    'reserved root' => ['root'],
    'non-ascii' => ['jöhn'],
]);

it('rejects a username already owned by another user', function (): void {
    User::factory()->create(['username' => 'johndoe']);

    expect(fn () => app(CreateNewUser::class)->create(usernamePayload('JohnDoe')))
        ->toThrow(ValidationException::class);
});

it('allows the current user to keep their username on update', function (): void {
    $user = User::factory()->create([
        'username' => 'johndoe',
        'first_name' => 'John',
    ]);

    app(UpdateUserProfileInformation::class)->update($user, [
        'first_name' => 'John',
        'email' => $user->email,
        'username' => 'johndoe',
    ]);

    expect($user->refresh()->username)->toBe('johndoe');
});

it('treats an empty username as optional', function (): void {
    $user = app(CreateNewUser::class)->create(usernamePayload(''));

    expect($user->username)->toBeNull();
});
