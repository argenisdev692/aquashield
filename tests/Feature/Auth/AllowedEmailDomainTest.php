<?php

declare(strict_types=1);

use App\Rules\AllowedEmailDomain;

it('accepts the company domain', function (): void {
    expect(AllowedEmailDomain::passes('admin@aquashieldrestorationusa.com'))->toBeTrue();
});

it('accepts the consumer providers the product supports', function (): void {
    expect(AllowedEmailDomain::passes('person@gmail.com'))->toBeTrue()
        ->and(AllowedEmailDomain::passes('person@outlook.com'))->toBeTrue();
});

it('rejects disposable and unknown domains', function (): void {
    expect(AllowedEmailDomain::passes('person@mailinator.com'))->toBeFalse()
        ->and(AllowedEmailDomain::passes('person@unknown-domain.test'))->toBeFalse();
});

it('honours a deployment-specific allowlist', function (): void {
    config(['auth.email_domains.allowed' => ['staff.example.org']]);

    expect(AllowedEmailDomain::passes('person@staff.example.org'))->toBeTrue()
        ->and(AllowedEmailDomain::passes('person@gmail.com'))->toBeFalse();
});
