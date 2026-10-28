<?php

declare(strict_types=1);

namespace Modules\CompanyData\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;
use Modules\CompanyData\Application\Actions\SaveCompanyDataAction;
use Modules\CompanyData\Application\Dtos\CompanyDataDto;
use Modules\CompanyData\Application\Dtos\SaveCompanyDataData;
use Modules\CompanyData\Application\Queries\CompanyAssetsQuery;
use Modules\CompanyData\Application\Repositories\CompanyRepository;
use Modules\CompanyData\Http\Requests\UpdateCompanyDataRequest;

final class CompanyDataController
{
    public function __construct(
        private readonly SaveCompanyDataAction $saveCompanyData,
        private readonly CompanyRepository $companies,
        private readonly CompanyAssetsQuery $companyAssets,
    ) {}

    public function show(): JsonResponse
    {
        $company = $this->companies->current();

        abort_if($company === null, 404);

        return response()->json(CompanyDataDto::from($company)->toArray());
    }

    /**
     * The edit screen. It hands the page the same DTO the JSON contract uses,
     * so the form is typed from the generated frontend contract and never from
     * a hand-written shape.
     */
    public function edit(Request $request): Response
    {
        $company = $this->companies->current();

        abort_if($company === null, 404);

        $user = $request->user();

        return inertia('CompanyData/Edit', [
            'company' => CompanyDataDto::from($company),
            // Resolved URLs, not storage keys: the page previews the assets it
            // is allowed to see, and the signature arrives as a signed URL.
            'assets' => $this->companyAssets->execute($company),
            // The route only requires VIEW; an update-less visitor still sees
            // the profile, with the form read-only instead of a 403 on submit.
            'canUpdate' => $user instanceof User
                && $user->getAllPermissions()->contains('name', 'UPDATE_COMPANY_DATA'),
        ]);
    }

    /**
     * One write path, two transports: the Inertia form gets a redirect back to
     * the edit screen (where the 422 bag and the fresh props land), and a JSON
     * client keeps the existing DTO body.
     */
    public function update(UpdateCompanyDataRequest $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        $data = SaveCompanyDataData::from($request->validated());

        $company = $this->saveCompanyData->execute($data, (int) $user->getAuthIdentifier());

        if (! $request->expectsJson()) {
            return to_route('company-data.edit');
        }

        return response()->json($company->toArray());
    }
}
