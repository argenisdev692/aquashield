<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Modules\Users\Jobs\SendUserInvitationJob;
use Modules\Users\Tests\Support\UserTestFixtures;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    UserTestFixtures::seedPermissions();
    $this->admin = UserTestFixtures::superAdmin();
});

it('skips an invitation whose timestamp was superseded', function (): void {
    Notification::fake();

    $user = User::factory()->invited()->create(['invitation_sent_at' => now()->subMinute()]);
    $oldTimestamp = (int) $user->invitation_sent_at?->getTimestamp();

    $user->forceFill(['invitation_sent_at' => now()])->save();

    (new SendUserInvitationJob($user, $oldTimestamp))->handle();

    Notification::assertNothingSent();
});

it('rate-limits and deduplicates the invitation job', function (): void {
    $user = User::factory()->invited()->create();
    $job = new SendUserInvitationJob($user, (int) $user->invitation_sent_at?->getTimestamp());

    expect($job->middleware()[0])->toBeInstanceOf(RateLimited::class)
        ->and($job->uniqueId())->toBe($user->uuid.':'.$job->invitationTimestamp);
});

it('throttles the bulk invitation route at two requests per minute', function (): void {
    Queue::fake();

    $target = User::factory()->invited()->create();

    $this->actingAs($this->admin)
        ->postJson('/users/bulk-invite', ['uuids' => [$target->uuid]])
        ->assertOk();

    $this->actingAs($this->admin)
        ->postJson('/users/bulk-invite', ['uuids' => [$target->uuid]])
        ->assertOk();

    $this->actingAs($this->admin)
        ->postJson('/users/bulk-invite', ['uuids' => [$target->uuid]])
        ->assertTooManyRequests();
});
