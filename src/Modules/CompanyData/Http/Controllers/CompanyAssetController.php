<?php

declare(strict_types=1);

namespace Modules\CompanyData\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Modules\CompanyData\Actions\DeleteCompanyAssetAction;
use Modules\CompanyData\Actions\StoreCompanyAssetAction;
use Modules\CompanyData\Enums\CompanyAsset;
use Modules\CompanyData\Http\Requests\StoreCompanyAssetRequest;
use Modules\CompanyData\Models\CompanyData;
use Modules\CompanyData\Queries\CompanyAssetsQuery;
use Modules\CompanyData\Support\CompanyAssetProcessingException;

/**
 * Brand-asset transport for the edit screen.
 *
 * Kept out of CompanyDataController because it is a different resource with a
 * different contract: these endpoints answer with the resolved URLs (JSON) so
 * the page can swap the preview without a full Inertia visit.
 */
final class CompanyAssetController
{
    public function __construct(
        private readonly StoreCompanyAssetAction $storeCompanyAsset,
        private readonly DeleteCompanyAssetAction $deleteCompanyAsset,
        private readonly CompanyAssetsQuery $companyAssets,
    ) {}

    public function store(StoreCompanyAssetRequest $request, CompanyAsset $asset): JsonResponse
    {
        $company = CompanyData::current();

        abort_if($company === null, 404);

        $file = $request->file('file');

        abort_unless($file instanceof UploadedFile, 422);

        try {
            $updated = $this->storeCompanyAsset->execute($company, $asset, $file);
        } catch (CompanyAssetProcessingException $exception) {
            throw ValidationException::withMessages(['file' => $exception->getMessage()]);
        }

        return response()->json($this->companyAssets->execute($updated)->toArray());
    }

    public function destroy(CompanyAsset $asset): JsonResponse
    {
        $company = CompanyData::current();

        abort_if($company === null, 404);

        $updated = $this->deleteCompanyAsset->execute($company, $asset);

        return response()->json($this->companyAssets->execute($updated)->toArray());
    }
}
