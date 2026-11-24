<?php

declare(strict_types=1);

namespace Modules\CompanyData\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;
use Modules\CompanyData\Actions\SaveCompanyDataAction;
use Modules\CompanyData\Data\CompanyDataDto;
use Modules\CompanyData\Data\SaveCompanyDataData;
use Modules\CompanyData\Http\Requests\UpdateCompanyDataRequest;
use Modules\CompanyData\Models\CompanyData;
use Modules\CompanyData\Queries\CompanyAssetsQuery;

final class CompanyDataController
{
    public function __construct(
        private readonly SaveCompanyDataAction $saveCompanyData,
        private readonly CompanyAssetsQuery $companyAssets,
    ) {}

    public function show(): JsonResponse
    {
        $company = CompanyData::current();

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
        $company = CompanyData::current();

        abort_if($company === null, 404);

        $user = $request->user();

        return inertia('CompanyData/Edit', [
            'company' => CompanyDataDto::from($company),
            // Resolved URLs, not storage keys: the page previews the assets it
            // is allowed to see, and the signature arrives as a signed URL.
            'assets' => $this->companyAssets->execute($company),
            // The route only requires VIEW; an update-less visitor still sees
            // the profile, with the form read-only instead of a 403 on submit.
            'canUpdate' => $user instanceof User && $user->can('UPDATE_COMPANY_DATA'),
            // Browser key for Places Autocomplete (New), restricted by HTTP
            // referrer in Google Cloud. Public by design — the server never
            // calls Places for this screen; the address field does.
            'googleMapsApiKey' => (string) config('services.google.maps_key', ''),
        ]);
    }

    /**
     * One write path, two transports: the Inertia form gets a redirect back to
     * the edit screen (where the 422 bag and the fresh props land), and a JSON
     * client keeps the existing DTO body.
     */
    public function update(UpdateCompanyDataRequest $request): JsonResponse|RedirectResponse
    {
        $data = SaveCompanyDataData::from($request->validated());

        $company = $this->saveCompanyData->execute($data);

        if (! $request->expectsJson()) {
            return to_route('company-data.edit');
        }

        return response()->json(CompanyDataDto::from($company)->toArray());
    }
}
