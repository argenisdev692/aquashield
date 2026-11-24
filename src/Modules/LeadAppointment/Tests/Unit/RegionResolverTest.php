<?php

declare(strict_types=1);

use Modules\LeadAppointment\Domain\Enums\Region;
use Modules\LeadAppointment\Domain\Services\RegionResolver;
use Tests\TestCase;

uses(TestCase::class);

it('resolves Florida from the state', function (): void {
    expect((new RegionResolver)->fromAddress('FL', 'Miami', '33101'))->toBe(Region::Florida);
});

it('resolves the Texas metro from the ZIP prefix', function (): void {
    expect((new RegionResolver)->fromAddress('TX', 'Houston', '75201'))->toBe(Region::Dallas)
        ->and((new RegionResolver)->fromAddress('TX', 'Dallas', '77002'))->toBe(Region::Houston)
        ->and((new RegionResolver)->fromAddress('TX', 'Fort Worth', '76102'))->toBe(Region::Dallas)
        ->and((new RegionResolver)->fromAddress(null, null, '77002'))->toBe(Region::Houston)
        ->and((new RegionResolver)->fromAddress(null, null, '75201'))->toBe(Region::Dallas);
});

it('falls back to the city when the ZIP is missing', function (): void {
    expect((new RegionResolver)->fromAddress('TX', 'Plano', null))->toBe(Region::Dallas)
        ->and((new RegionResolver)->fromAddress('TX', 'Sugar Land', null))->toBe(Region::Houston);
});

it('leaves unsupported or incomplete addresses without a region', function (): void {
    expect((new RegionResolver)->fromAddress('TX', 'Austin', '78701'))->toBeNull()
        ->and((new RegionResolver)->fromAddress('CA', 'Los Angeles', '90001'))->toBeNull()
        ->and((new RegionResolver)->fromAddress('TX', null, null))->toBeNull()
        ->and((new RegionResolver)->fromAddress(null, null, null))->toBeNull();
});

it('trusts a fallback only when there is no address data', function (): void {
    expect((new RegionResolver)->resolve(null, null, null, 'houston'))->toBe(Region::Houston)
        ->and((new RegionResolver)->resolve(null, null, null, 'florida'))->toBe(Region::Florida)
        ->and((new RegionResolver)->resolve('TX', null, null, 'houston'))->toBeNull()
        ->and((new RegionResolver)->resolve('TX', 'Plano', null, 'houston'))->toBe(Region::Dallas)
        ->and((new RegionResolver)->resolve('TX', 'Austin', '78701', 'houston'))->toBeNull();
});
