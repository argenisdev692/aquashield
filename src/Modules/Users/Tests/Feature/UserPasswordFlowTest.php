<?php

declare(strict_types=1);

use App\Enums\SecurityEvent;
use App\Jobs\SendSecurityAlert;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\UserInvitationNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Modules\Users\Actions\SendPasswordResetLinkAction;
use Modules\Users\Jobs\SendEmailVerificationJob;
use Modules\Users\Jobs\SendPasswordResetLinkJob;
use Modules\Users\Jobs\SendUserInvitationJob;
use Modules\Users\Tests\Support\UserTestFixtures;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    UserTestFixtures::seedPermissions();
    $this->admin = UserTestFixtures::superAdmin();
});

it('sends a signed single-use activation email from the invitation job', function (): void {
    Notification::fake();

    $user = User::factory()->invited()->create(['first_name' => 'Ada']);

    (new SendUserInvitationJob($user, (int) $user->invitation_sent_at?->getTimestamp()))->handle();

    $notification = Notification::sent($user, UserInvitationNotification::class)->first();

    expect($notification)->toBeInstanceOf(UserInvitationNotification::class);

    if ($notification === null) {
        return;
    }

    $mail = $notification->toMail($user);
    $html = $mail->render();
    $url = $notification->url;

    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    expect($url)
        ->toStartWith(rtrim((string) config('app.url'), '/').'/users/activate/'.$user->uuid)
        ->and($query)->toHaveKeys(['expires', 'signature', 'invitation'])
        ->and((int) $query['invitation'])->toBe($user->invitation_sent_at?->getTimestamp())
        ->and($url)->not->toContain($user->email)
        ->and($html)->toContain('never sends passwords by email')
        ->and($html)->toContain('Activate my account');
});

it('sends the broker password-set email for an active account', function (): void {
    Notification::fake();

    $user = User::factory()->create(['first_name' => 'Ada']);

    (new SendPasswordResetLinkJob($user))->handle();

    Notification::assertSentTo(
        $user,
        ResetPasswordNotification::class,
        function (ResetPasswordNotification $notification) use ($user): bool {
            $mail = $notification->toMail($user);
            $html = $mail->render();
            $url = (string) ($mail->viewData['url'] ?? '');

            return str_starts_with($url, rtrim((string) config('app.url'), '/').'/reset-password/')
                && ! str_contains($url, $user->email)
                && str_contains($html, 'never sends passwords by email');
        },
    );
});

it('does not send an invitation to an active account', function (): void {
    Notification::fake();

    $user = User::factory()->create();

    (new SendUserInvitationJob($user, (int) $user->invitation_sent_at?->getTimestamp()))->handle();

    Notification::assertNothingSent();
});

it('does not send an invitation to a deleted account', function (): void {
    Notification::fake();

    $user = User::factory()->invited()->create();
    $user->delete();

    (new SendUserInvitationJob($user, (int) $user->invitation_sent_at?->getTimestamp()))->handle();

    Notification::assertNothingSent();
});

it('does not send a password-set link to a deleted account', function (): void {
    Notification::fake();

    $user = User::factory()->create();
    $user->delete();

    (new SendPasswordResetLinkJob($user))->handle();

    Notification::assertNothingSent();
});

it('does not send a verification mail to a deleted account', function (): void {
    Notification::fake();

    $user = User::factory()->create();
    $user->delete();

    (new SendEmailVerificationJob($user))->handle();

    Notification::assertNothingSent();
});

it('queues a password-set link from the user screen and audits the request', function (): void {
    Queue::fake();

    $target = User::factory()->create();

    $this->actingAs($this->admin)
        ->from('/users/'.$target->uuid)
        ->post('/users/'.$target->uuid.'/password-reset')
        ->assertRedirect('/users/'.$target->uuid);

    Queue::assertPushed(
        SendPasswordResetLinkJob::class,
        fn (SendPasswordResetLinkJob $job): bool => $job->user->is($target),
    );

    $this->assertDatabaseHas('activity_log', [
        'description' => 'user.password_reset_link_sent',
        'subject_id' => $target->getKey(),
    ]);
});

it('answers the password-set link request with a queued JSON status', function (): void {
    Queue::fake();

    $target = User::factory()->create();

    $this->actingAs($this->admin)
        ->postJson('/users/'.$target->uuid.'/password-reset')
        ->assertAccepted()
        ->assertJsonPath('status', 'queued');

    Queue::assertPushed(SendPasswordResetLinkJob::class);
});

it('refuses a password-set link for an account above the actor ceiling at the action level', function (): void {
    Queue::fake();

    UserTestFixtures::role('MANAGER', ['VIEW_ANY_USER', 'VIEW_USER', 'UPDATE_USER']);
    UserTestFixtures::role('ADMIN', ['DELETE_USER']);

    $manager = UserTestFixtures::userWithRole('MANAGER');
    $target = UserTestFixtures::userWithRole('ADMIN');

    // The HTTP surface is super-admin-only (`can:manage-staff`), so a 403 from
    // the route would prove the gate, not the ceiling. Call the action directly
    // to exercise the row-level rule.
    expect(fn () => app(SendPasswordResetLinkAction::class)->execute($target, $manager))
        ->toThrow(AuthorizationException::class);

    Queue::assertNothingPushed();
});

it('clears verification and notifies the old address when an administrator changes an email', function (): void {
    Queue::fake();

    $target = User::factory()->create([
        'email' => 'old@gmail.com',
    ]);

    $this->actingAs($this->admin)
        ->putJson('/users/'.$target->uuid, [
            'first_name' => 'Ada',
            'email' => 'new@gmail.com',
        ])
        ->assertOk()
        ->assertJsonPath('email', 'new@gmail.com')
        ->assertJsonPath('email_verified', false);

    expect($target->refresh()->email_verified_at)->toBeNull();

    Queue::assertPushed(
        SendEmailVerificationJob::class,
        fn (SendEmailVerificationJob $job): bool => $job->user->is($target),
    );

    Queue::assertPushed(
        SendSecurityAlert::class,
        fn (SendSecurityAlert $job): bool => $job->email === 'old@gmail.com'
            && $job->event === SecurityEvent::EmailChanged,
    );
});

it('keeps verification untouched when the email does not change', function (): void {
    Queue::fake();

    $target = User::factory()->create();

    $this->actingAs($this->admin)
        ->putJson('/users/'.$target->uuid, [
            'first_name' => 'Still Here',
            'email' => $target->email,
        ])
        ->assertOk()
        ->assertJsonPath('email_verified', true);

    expect($target->refresh()->email_verified_at)->not->toBeNull();

    Queue::assertNotPushed(SendEmailVerificationJob::class);
    Queue::assertNotPushed(SendSecurityAlert::class);
});
