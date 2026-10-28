<?php

declare(strict_types=1);

namespace Modules\CompanyData\Infrastructure\Persistence;

use Modules\CompanyData\Application\Repositories\CompanyRepository;

final class EloquentCompanyRepository implements CompanyRepository
{
    public function current(): ?CompanyData
    {
        return CompanyData::query()->oldest('id')->first();
    }

    public function blank(): CompanyData
    {
        return new CompanyData;
    }

    public function save(CompanyData $companyData): CompanyData
    {
        $companyData->save();

        return $companyData;
    }
}
