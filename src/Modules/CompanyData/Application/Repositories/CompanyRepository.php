<?php

declare(strict_types=1);

namespace Modules\CompanyData\Application\Repositories;

use Modules\CompanyData\Infrastructure\Persistence\CompanyData;

interface CompanyRepository
{
    public function current(): ?CompanyData;

    public function blank(): CompanyData;

    public function save(CompanyData $companyData): CompanyData;
}
