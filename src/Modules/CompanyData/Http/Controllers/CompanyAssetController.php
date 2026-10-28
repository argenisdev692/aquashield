<?php

declare(strict_types=1);

namespace Modules\CompanyData\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Modules\CompanyData\Application\Actions\DeleteCompanyAssetAction;
use Modules\CompanyData\Application\Actions\StoreCompanyAssetAction;
use Modules\CompanyData\Application\Queries\CompanyAssetsQuery;
use Modules\CompanyData\Application\Repositories\CompanyRepository;
use Modules\CompanyData\Domain\Enums\CompanyAsset;
use Modules\CompanyData\Http\Requests\StoreCompanyAssetRequest;

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
        private readonly CompanyRepository $companies,
    ) {}

    public function store(StoreCompanyAssetRequest $request, CompanyAsset $asset): JsonResponse
    {
        $company = $this->companies->current();

        abort_if($company === null, 404);

        $file = $request->file('file');

        abort_unless($file instanceof UploadedFile, 422);

        $updated = $this->storeCompanyAsset->execute($company, $asset, $file);

        return response()->json($this->companyAssets->execute($updated)->toArray());
    }

    public function destroy(CompanyAsset $asset): JsonResponse
    {
        $company = $this->companies->current();

        abort_if($company === null, 404);

        $updated = $this->deleteCompanyAsset->execute($company, $asset);

        return response()->json($this->companyAssets->execute($updated)->toArray());
    }
}
