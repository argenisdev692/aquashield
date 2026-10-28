<?php

declare(strict_types=1);

namespace Modules\CompanyData\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\CompanyData\Application\Actions\SaveCompanyDataAction;
use Modules\CompanyData\Application\Dtos\CompanyDataDto;
use Modules\CompanyData\Application\Dtos\SaveCompanyDataData;
use Modules\CompanyData\Application\Repositories\CompanyRepository;
use Modules\CompanyData\Http\Requests\UpdateCompanyDataRequest;

final class CompanyDataController
{
    public function __construct(
        private readonly SaveCompanyDataAction $saveCompanyData,
        private readonly CompanyRepository $companies,
    ) {}

    public function show(): JsonResponse
    {
        $company = $this->companies->current();

        abort_if($company === null, 404);

        return response()->json(CompanyDataDto::from($company)->toArray());
    }

    public function update(UpdateCompanyDataRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        $data = SaveCompanyDataData::from($request->validated());

        return response()->json(
            $this->saveCompanyData->execute($data, (int) $user->getAuthIdentifier())->toArray(),
        );
    }
}
