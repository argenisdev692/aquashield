<?php

declare(strict_types=1);

namespace Modules\CompanyData\Application\Actions;

use Modules\CompanyData\Application\Dtos\CompanyDataDto;
use Modules\CompanyData\Application\Dtos\SaveCompanyDataData;
use Modules\CompanyData\Application\Repositories\CompanyRepository;

final class SaveCompanyDataAction
{
    public function __construct(
        private readonly CompanyRepository $companies,
    ) {}

    public function execute(SaveCompanyDataData $data, int $userId): CompanyDataDto
    {
        $company = $this->companies->current() ?? $this->companies->blank();

        $company->fill($data->toArray());

        if (! $company->exists) {
            $company->user_id = $userId;
        }

        return CompanyDataDto::from($this->companies->save($company));
    }
}
