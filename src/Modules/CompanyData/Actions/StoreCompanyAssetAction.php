<?php

declare(strict_types=1);

namespace Modules\CompanyData\Actions;

use Illuminate\Support\Facades\DB;
use Modules\CompanyData\Enums\CompanyAsset;
use Modules\CompanyData\Models\CompanyData;
use Modules\CompanyData\Support\R2CompanyAssetStorage;
use SplFileInfo;

/**
 * Replaces one brand asset: the new object is written first, the row is pointed
 * at its key, and only then is the previous object deleted. A failure at any
 * earlier step leaves the profile rendering the asset it already had.
 */
final class StoreCompanyAssetAction
{
    public function __construct(
        private readonly R2CompanyAssetStorage $storage,
    ) {}

    public function execute(CompanyData $company, CompanyAsset $asset, SplFileInfo $file): CompanyData
    {
        $previous = $company->getAttribute($asset->column());

        $key = $this->storage->store($asset, (string) $company->uuid, $file);

        // Assignment rather than fill(): the asset columns stay out of the
        // mass-assignment allowlist on purpose, so a profile PUT can never
        // point them at an attacker-chosen key.
        $company->setAttribute($asset->column(), $key);

        // Storage stays outside the transaction; only the row + activity entry
        // are atomic. A failed save leaves the new object as bounded debt.
        DB::transaction(static fn () => $company->save());

        $this->storage->forget(is_string($previous) ? $previous : null);

        return $company;
    }
}
