<?php

declare(strict_types=1);

use App\Enums\SecurityEvent;
use App\Jobs\SendSecurityAlert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Passkeys\Events\PasskeyDeleted;
use Laravel\Passkeys\Events\PasskeyRegistered;
use Laravel\Passkeys\Passkey;

uses(RefreshDatabase::class);

/**
 * A stored passkey for the given owner. The credential body is irrelevant to
 * the account screen: the allowlisted DTO never exposes it.
 */
function passkeyRecord(User $user, string $name = 'MacBook'): Passkey
{
    $passkey = new Passkey;
    $passkey->user_id = $user->getKey();
    $passkey->name = $name;
    $passkey->credential_id = 'credential-'.bin2hex(random_bytes(8));
    $passkey->credential = ['aaguid' => '00000000-0000-0000-0000-000000000000'];
    $passkey->created_at = now()->subDay();
    $passkey->save();

    return $passkey;
}

it('lists the signed-in user passkeys on the account screen', function (): void {
    $user = User::factory()->create();
    passkeyRecord($user, 'MacBook');
    passkeyRecord($user, 'Phone');

    $this->actingAs($user)
        ->get('/settings/profile')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Settings/Profile')
            ->where('passkeysCount', 2)
            ->has('passkeys', 2)
            ->has('passkeys.0.name')
            ->has('passkeys.0.created_at')
            ->missing('passkeys.0.credential'));
});

it('never lists another user passkeys', function (): void {
    $owner = User::factory()->create();
    passkeyRecord($owner, 'Owner key');

    $this->actingAs(User::factory()->create())
        ->get('/settings/profile')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('passkeysCount', 0)
            ->has('passkeys', 0));
});

it('shows the enrolment prompt to a user with no passkey', function (): void {
    $this->actingAs(User::factory()->create())
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('passkeyPrompt', true));
});

it('hides the enrolment prompt for a user with a passkey', function (): void {
    $user = User::factory()->create();
    passkeyRecord($user);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('passkeyPrompt', false));
});

it('persists the prompt dismissal server-side', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/settings/passkey-prompt/dismiss')
        ->assertOk()
        ->assertJson(['dismissed' => true]);

    expect($user->refresh()->passkey_prompt_dismissed_at)->not->toBeNull();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page->where('passkeyPrompt', false));
});

it('refuses a guest at the dismissal endpoint', function (): void {
    $this->postJson('/settings/passkey-prompt/dismiss')->assertUnauthorized();
});

it('sends no prompt prop to a guest', function (): void {
    $this->get('/login')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('passkeyPrompt', null));
});

it('alerts the owner when a passkey is added', function (): void {
    Bus::fake();

    $user = User::factory()->create();
    $passkey = passkeyRecord($user, 'Work laptop');

    PasskeyRegistered::dispatch($user, $passkey);

    Bus::assertDispatched(
        SendSecurityAlert::class,
        fn (SendSecurityAlert $job): bool => $job->event === SecurityEvent::PasskeyRegistered
            && $job->email === $user->email,
    );
});

it('alerts the owner when a passkey is removed', function (): void {
    Bus::fake();

    $user = User::factory()->create();
    $passkey = passkeyRecord($user, 'Old phone');

    PasskeyDeleted::dispatch($user, $passkey);

    Bus::assertDispatched(
        SendSecurityAlert::class,
        fn (SendSecurityAlert $job): bool => $job->event === SecurityEvent::PasskeyDeleted
            && $job->email === $user->email,
    );
});

it('requires authentication for passkey registration options', function (): void {
    $this->getJson('/user/passkeys/options')->assertUnauthorized();
});
