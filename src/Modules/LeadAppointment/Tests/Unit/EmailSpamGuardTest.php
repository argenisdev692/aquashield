<?php

declare(strict_types=1);

use Modules\LeadAppointment\Domain\Services\EmailSpamGuard;

function emailGuard(): EmailSpamGuard
{
    return new EmailSpamGuard(
        blockedDomains: ['example.com', 'company.com'],
        disposableDomains: ['mailinator.com'],
        rolePrefixes: ['info', 'sales'],
        threshold: 5,
    );
}

it('blocks reserved, placeholder and disposable domains', function (): void {
    $guard = emailGuard();

    expect($guard->isBlocked('ada@example.com'))->toBeTrue()
        ->and($guard->isBlocked('ada@company.com'))->toBeTrue()
        ->and($guard->isBlocked('ada@company'))->toBeTrue()
        ->and($guard->isBlocked('ada@mailinator.com'))->toBeTrue()
        ->and($guard->isBlocked('ada@aquashieldrestorationusa.com'))->toBeFalse();
});

it('scores role mailboxes without rejecting them', function (): void {
    $assessment = emailGuard()->assess('info@aquashieldrestorationusa.com');

    expect($assessment->score)->toBe(3)
        ->and($assessment->isSpam)->toBeFalse()
        ->and($assessment->reasons)->toContain('email:role_address');
});

it('marks a disposable domain as spam', function (): void {
    $assessment = emailGuard()->assess('ada@mailinator.com');

    expect($assessment->isSpam)->toBeTrue()
        ->and($assessment->reasons)->toContain('email:disposable_domain');
});

it('does not flag a normal personal address', function (): void {
    $assessment = emailGuard()->assess('ada@gmail.com');

    expect($assessment->score)->toBe(0)
        ->and($assessment->isSpam)->toBeFalse()
        ->and($assessment->reasons)->toBe([]);
});
