<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\CompanyData\Actions\SaveCompanyDataAction;
use Modules\CompanyData\Data\SaveCompanyDataData;
use Modules\CompanyData\Models\CompanyData;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('updates the provisioned singleton in place', function (): void {
    $user = User::factory()->create();

    $company = new CompanyData;
    $company->fill([
        'name' => 'Victor Lara',
        'company_name' => 'AquaShield Restoration USA',
        'email' => 'info@aquashieldrestorationusa.com',
    ]);
    $company->user_id = $user->id;
    $company->save();

    $updated = app(SaveCompanyDataAction::class)->execute(new SaveCompanyDataData(
        name: 'Victor Lara',
        companyName: 'AquaShield Restoration LLC',
        email: 'info@aquashieldrestorationusa.com',
        updatedAt: $company->updated_at?->toJSON(),
    ));

    expect($updated->uuid)->toBe($company->uuid)
        ->and($updated->companyName)->toBe('AquaShield Restoration LLC')
        ->and(CompanyData::query()->count())->toBe(1)
        ->and(CompanyData::current()?->user_id)->toBe($user->id);
});

it('rejects a stale full-profile write', function (): void {
    $user = User::factory()->create();

    $company = new CompanyData;
    $company->fill([
        'name' => 'Victor Lara',
        'company_name' => 'AquaShield Restoration USA',
        'email' => 'info@aquashieldrestorationusa.com',
    ]);
    $company->user_id = $user->id;
    $company->save();

    $stale = $company->updated_at?->toJSON();
    $company->forceFill([
        'company_name' => 'Newer admin value',
        'updated_at' => now()->addMinute(),
    ])->save();

    expect(fn () => app(SaveCompanyDataAction::class)->execute(new SaveCompanyDataData(
        name: 'Victor Lara',
        companyName: 'Stale admin value',
        email: 'info@aquashieldrestorationusa.com',
        updatedAt: $stale,
    )))->toThrow(HttpException::class);

    expect(CompanyData::current()?->company_name)->toBe('Newer admin value');
});

it('throws when the singleton is not provisioned', function (): void {
    expect(fn () => app(SaveCompanyDataAction::class)->execute(new SaveCompanyDataData(
        name: 'Victor Lara',
        companyName: 'AquaShield Restoration USA',
        email: 'info@aquashieldrestorationusa.com',
    )))->toThrow(ModelNotFoundException::class);

    expect(CompanyData::query()->count())->toBe(0);
});
