<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\UserInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Users\Actions\ResendInvitationAction;
use Modules\Users\Jobs\SendUserInvitationJob;
use Modules\Users\Tests\Support\UserTestFixtures;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    UserTestFixtures::seedPermissions();
    $this->admin = UserTestFixtures::superAdmin();
});

/**
 * Runs the real invitation job and returns the signed URL it mailed.
 */
function usersInvitationUrlForTest(User $user): string
{
    Notification::fake();

    (new SendUserInvitationJob($user, (int) $user->invitation_sent_at?->getTimestamp()))->handle();

    $notification = Notification::sent($user, UserInvitationNotification::class)->first();

    expect($notification)->toBeInstanceOf(UserInvitationNotification::class);

    return $notification instanceof UserInvitationNotification ? $notification->url : '';
}

it('renders the activation page for a valid signed invitation', function (): void {
    $user = User::factory()->invited()->create(['email' => 'ada@gmail.com']);
    $url = usersInvitationUrlForTest($user);

    $this->get($url)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Users/Activate')
            ->where('state', 'ready')
            ->where('user.email', 'ada@gmail.com')
            ->where('user.name', $user->name)
            ->whereNot('actionUrl', null)
        );
});

it('does not expose the account identity for an unsigned link', function (): void {
    $user = User::factory()->invited()->create([
        'first_name' => 'Ada',
        'email' => 'ada@gmail.com',
    ]);

    // No `signature`/`expires` query string: the page may explain the state,
    // but it must not confirm who owns the UUID.
    $this->get('/users/activate/'.$user->uuid)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Users/Activate')
            ->where('state', 'invalid')
            ->where('user', null)
        );
});

it('does not write the account email into the activation audit payload', function (): void {
    $user = User::factory()->invited()->create(['email' => 'ada@gmail.com']);
    $url = usersInvitationUrlForTest($user);

    $this->post($url, [
        'password' => 'secret12',
        'password_confirmation' => 'secret12',
    ])->assertRedirect('/dashboard');

    $activity = Activity::query()->where('event', 'user.activated')->first();

    expect($activity)->not->toBeNull()
        ->and($activity?->properties?->toArray() ?? [])->not->toHaveKey('email');
});

it('activates the account, sets the password, verifies the email and signs in', function (): void {
    $user = User::factory()->invited()->create(['email' => 'ada@gmail.com']);
    $url = usersInvitationUrlForTest($user);

    $this->post($url, [
        'password' => 'secret12',
        'password_confirmation' => 'secret12',
    ])
        ->assertRedirect('/dashboard');

    $user->refresh();

    expect($user->status->value)->toBe('active')
        ->and($user->hasVerifiedEmail())->toBeTrue()
        ->and($user->invitation_sent_at)->toBeNull()
        ->and($user->must_change_password)->toBeFalse()
        ->and(Hash::check('secret12', (string) $user->password))->toBeTrue();

    $this->assertAuthenticatedAs($user);
});

it('rejects a reused invitation after activation', function (): void {
    $user = User::factory()->invited()->create();
    $url = usersInvitationUrlForTest($user);

    $this->post($url, [
        'password' => 'secret12',
        'password_confirmation' => 'secret12',
    ])->assertRedirect('/dashboard');

    // A later click arrives without the activated session; the endpoint itself
    // must reject the consumed invitation rather than relying on `guest`.
    $this->post('/logout');

    $this->post($url, [
        'password' => 'secret99',
        'password_confirmation' => 'secret99',
    ])->assertForbidden();

    $this->get($url)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Users/Activate')
            ->where('state', 'used')
        );
});

it('shows an expired state and rejects the post after the signed TTL', function (): void {
    $user = User::factory()->invited()->create();
    $url = usersInvitationUrlForTest($user);

    $this->travel(49)->hours();

    $this->get($url)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Users/Activate')
            ->where('state', 'expired')
        );

    $this->post($url, [
        'password' => 'secret12',
        'password_confirmation' => 'secret12',
    ])->assertForbidden();
});

it('invalidates an older link when the invitation is resent', function (): void {
    Queue::fake();

    $user = User::factory()->invited()->create();
    $user->forceFill(['invitation_sent_at' => now()->subMinute()])->save();
    $oldUrl = usersInvitationUrlForTest($user);

    app(ResendInvitationAction::class)->execute($user, $this->admin);

    $this->get($oldUrl)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Users/Activate')
            ->where('state', 'superseded')
        );

    $this->post($oldUrl, [
        'password' => 'secret12',
        'password_confirmation' => 'secret12',
    ])->assertForbidden();
});

it('rejects a password that breaks the shared policy with 422', function (): void {
    $user = User::factory()->invited()->create();
    $url = usersInvitationUrlForTest($user);

    $this->postJson($url, [
        'password' => 'secret12',
        'password_confirmation' => 'different',
    ])->assertUnprocessable();

    expect($user->refresh()->status->value)->toBe('invited');
});

it('404s an activation link for a user that no longer exists', function (): void {
    $this->get('/users/activate/'.Str::uuid7()->toString())
        ->assertNotFound();
});
