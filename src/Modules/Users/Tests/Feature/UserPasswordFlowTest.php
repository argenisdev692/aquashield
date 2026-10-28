<?php

declare(strict_types=1);

use App\Enums\SecurityEvent;
use App\Jobs\SendSecurityAlert;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Modules\Users\Jobs\SendEmailVerificationJob;
use Modules\Users\Jobs\SendUserInvitationJob;
use Modules\Users\Tests\Support\UserTestFixtures;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    UserTestFixtures::seedPermissions();
    $this->admin = UserTestFixtures::superAdmin();
});

it('sends a branded single-use password-set email from the invitation job', function (): void {
    Notification::fake();

    $user = User::factory()->create(['first_name' => 'Ada']);

    (new SendUserInvitationJob($user))->handle();

    Notification::assertSentTo(
        $user,
        ResetPasswordNotification::class,
        function (ResetPasswordNotification $notification) use ($user): bool {
            $mail = $notification->toMail($user);
            $html = $mail->render();
            $url = (string) ($mail->viewData['url'] ?? '');

            return str_starts_with($url, rtrim((string) config('app.url'), '/').'/reset-password/')
                && ! str_contains($url, $user->email)
                && str_contains($html, 'never sends passwords by email')
                && str_contains($html, 'Do not forward or share this link');
        },
    );
});

it('queues a password-set link from the user screen and audits the request', function (): void {
    Queue::fake();

    $target = User::factory()->create();

    $this->actingAs($this->admin)
        ->from('/users/'.$target->uuid)
        ->post('/users/'.$target->uuid.'/password-reset')
        ->assertRedirect('/users/'.$target->uuid);

    Queue::assertPushed(
        SendUserInvitationJob::class,
        fn (SendUserInvitationJob $job): bool => $job->user->is($target),
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

    Queue::assertPushed(SendUserInvitationJob::class);
});

it('refuses a password-set link for an account above the actor ceiling', function (): void {
    Queue::fake();

    UserTestFixtures::role('MANAGER', ['VIEW_ANY_USER', 'VIEW_USER', 'UPDATE_USER']);
    UserTestFixtures::role('ADMIN', ['DELETE_USER']);

    $manager = UserTestFixtures::userWithRole('MANAGER');
    $target = UserTestFixtures::userWithRole('ADMIN');

    $this->actingAs($manager)
        ->postJson('/users/'.$target->uuid.'/password-reset')
        ->assertForbidden();

    Queue::assertNothingPushed();
});

it('clears verification and notifies the old address when an administrator changes an email', function (): void {
    Queue::fake();

    $target = User::factory()->create([
        'email' => 'old@example.com',
    ]);

    $this->actingAs($this->admin)
        ->putJson('/users/'.$target->uuid, [
            'first_name' => 'Ada',
            'email' => 'new@example.com',
        ])
        ->assertOk()
        ->assertJsonPath('email', 'new@example.com')
        ->assertJsonPath('email_verified', false);

    expect($target->refresh()->email_verified_at)->toBeNull();

    Queue::assertPushed(
        SendEmailVerificationJob::class,
        fn (SendEmailVerificationJob $job): bool => $job->user->is($target),
    );

    Queue::assertPushed(
        SendSecurityAlert::class,
        fn (SendSecurityAlert $job): bool => $job->email === 'old@example.com'
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
