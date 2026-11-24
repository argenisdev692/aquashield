<?php

declare(strict_types=1);

namespace Modules\CompanyData\Http\Controllers\Api;

use Modules\CompanyData\Actions\SaveCompanyDataAction;
use Modules\CompanyData\Data\CompanyBrandingDto;
use Modules\CompanyData\Data\CompanyDataDto;
use Modules\CompanyData\Data\SaveCompanyDataData;
use Modules\CompanyData\Http\Requests\UpdateCompanyDataRequest;
use Modules\CompanyData\Models\CompanyData;
use Shared\Infrastructure\Company\CompanyProfile;

final class CompanyDataController
{
    public function __construct(
        private readonly SaveCompanyDataAction $saveCompanyData,
    ) {}

    /**
     * Public branding. No auth on purpose: the welcome page and the external
     * landing sites need the logo/contact block before anyone signs in.
     *
     * Returning the Data object (not a JsonResponse) lets Scramble document
     * the response schema from the class instead of guessing at an array.
     */
    public function show(): CompanyBrandingDto
    {
        return CompanyBrandingDto::from(CompanyProfile::data());
    }

    /**
     * Authenticated management read: the full profile, including the billing
     * fields that must never appear on the public endpoint.
     */
    public function showManage(): CompanyDataDto
    {
        $company = CompanyData::current();

        abort_if($company === null, 404);

        return CompanyDataDto::from($company);
    }

    public function update(UpdateCompanyDataRequest $request): CompanyDataDto
    {
        $data = SaveCompanyDataData::from($request->validated());

        return $this->saveCompanyData->execute($data);
    }
}
