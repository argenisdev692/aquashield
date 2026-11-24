<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Modules\LeadAppointment\Domain\Services\DuplicateDetector;
use Modules\LeadAppointment\Domain\ValueObjects\DuplicateCandidate;

function candidate(
    string $uuid,
    ?string $email = null,
    ?string $phone = null,
    string $first = 'Ada',
    string $last = 'Lovelace',
    ?string $address = '123 Main St',
    string $created = '2026-01-01',
): DuplicateCandidate {
    return new DuplicateCandidate(
        uuid: $uuid,
        email: $email,
        phone: $phone,
        firstName: $first,
        lastName: $last,
        address: $address,
        createdAt: CarbonImmutable::parse($created),
    );
}

it('matches a normalised email inside the window', function (): void {
    $match = app(DuplicateDetector::class)->detect(
        'ADA@EXAMPLE.COM',
        null,
        'Ada',
        'Lovelace',
        '123 Main St',
        [candidate('lead-1', email: 'ada@example.com')],
        30,
        CarbonImmutable::parse('2026-01-15'),
    );

    expect($match?->leadUuid)->toBe('lead-1')
        ->and($match?->reason)->toBe('email');
});

it('matches a normalised phone', function (): void {
    $match = app(DuplicateDetector::class)->detect(
        null,
        '(713) 555-0199',
        'Ada',
        'Lovelace',
        '123 Main St',
        [candidate('lead-1', phone: '+17135550199')],
        30,
        CarbonImmutable::parse('2026-01-15'),
    );

    expect($match?->leadUuid)->toBe('lead-1')
        ->and($match?->reason)->toBe('phone');
});

it('matches name + address when no contact field is available', function (): void {
    $match = app(DuplicateDetector::class)->detect(
        null,
        null,
        'Ada',
        'Lovelace',
        '123 Main St',
        [candidate('lead-1')],
        30,
        CarbonImmutable::parse('2026-01-15'),
    );

    expect($match?->leadUuid)->toBe('lead-1')
        ->and($match?->reason)->toBe('name_address');
});

it('ignores a candidate outside the dedupe window', function (): void {
    $match = app(DuplicateDetector::class)->detect(
        'ada@example.com',
        null,
        'Ada',
        'Lovelace',
        '123 Main St',
        [candidate('lead-1', email: 'ada@example.com', created: '2025-01-01')],
        30,
        CarbonImmutable::parse('2026-01-15'),
    );

    expect($match)->toBeNull();
});
