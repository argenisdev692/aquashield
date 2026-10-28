<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Mockery\MockInterface;
use Modules\CompanyData\Application\Actions\SaveCompanyDataAction;
use Modules\CompanyData\Application\Dtos\SaveCompanyDataData;
use Modules\CompanyData\Application\Repositories\CompanyRepository;
use Modules\CompanyData\Infrastructure\Persistence\CompanyData;
use Tests\TestCase;

uses(TestCase::class);

it('saves the singleton through the repository without the database', function (): void {
    $saved = new CompanyData;
    $saved->fill([
        'company_name' => 'AquaShield Restoration USA',
        'email' => 'info@aquashieldrestorationusa.com',
    ]);
    $saved->uuid = (string) Str::uuid7();
    $saved->user_id = 7;

    $this->mock(CompanyRepository::class, function (MockInterface $mock) use ($saved): void {
        $mock->shouldReceive('current')->once()->andReturnNull();
        $mock->shouldReceive('blank')->once()->andReturn($saved);
        $mock->shouldReceive('save')
            ->once()
            ->with(Mockery::on(
                fn (CompanyData $company): bool => $company->company_name === 'AquaShield Restoration USA'
                    && $company->user_id === 7,
            ))
            ->andReturn($saved);
    });

    $dto = app(SaveCompanyDataAction::class)->execute(new SaveCompanyDataData(
        name: 'Victor Lara',
        companyName: 'AquaShield Restoration USA',
        email: 'info@aquashieldrestorationusa.com',
    ), 7);

    expect($dto->companyName)->toBe('AquaShield Restoration USA')
        ->and($dto->uuid)->toBe($saved->uuid)
        ->and($dto->city)->toBeNull();
});
