<?php

declare(strict_types=1);

namespace Modules\CompanyData\Actions;

use Illuminate\Support\Facades\DB;
use Modules\CompanyData\Enums\CompanyAsset;
use Modules\CompanyData\Models\CompanyData;
use Modules\CompanyData\Support\R2CompanyAssetStorage;

/**
 * Clears one brand asset. Logos fall back to the bundled artwork; the signature
 * becomes "none on file". The object is deleted after the row stops pointing at
 * it, never before.
 */
final class DeleteCompanyAssetAction
{
    public function __construct(
        private readonly R2CompanyAssetStorage $storage,
    ) {}

    public function execute(CompanyData $company, CompanyAsset $asset): CompanyData
    {
        $previous = $company->getAttribute($asset->column());

        $company->setAttribute($asset->column(), null);

        // Same split as the upload: the row + activity entry commit together,
        // the object deletion happens after the row stops pointing at it.
        DB::transaction(static fn () => $company->save());

        $this->storage->forget(is_string($previous) ? $previous : null);

        return $company;
    }
}
