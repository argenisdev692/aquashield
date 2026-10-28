<?php

declare(strict_types=1);

namespace Modules\CompanyData\Http\Controllers\Api;

use Modules\CompanyData\Application\Actions\SaveCompanyDataAction;
use Modules\CompanyData\Application\Dtos\CompanyBrandingDto;
use Modules\CompanyData\Application\Dtos\CompanyDataDto;
use Modules\CompanyData\Application\Dtos\SaveCompanyDataData;
use Modules\CompanyData\Application\Repositories\CompanyRepository;
use Modules\CompanyData\Http\Requests\UpdateCompanyDataRequest;
use Shared\Infrastructure\Company\CompanyProfile;

final class CompanyDataController
{
    public function __construct(
        private readonly SaveCompanyDataAction $saveCompanyData,
        private readonly CompanyRepository $companies,
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
        $company = $this->companies->current();

        abort_if($company === null, 404);

        return CompanyDataDto::from($company);
    }

    public function update(UpdateCompanyDataRequest $request): CompanyDataDto
    {
        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        $data = SaveCompanyDataData::from($request->validated());

        return $this->saveCompanyData->execute($data, (int) $user->getAuthIdentifier());
    }
}
