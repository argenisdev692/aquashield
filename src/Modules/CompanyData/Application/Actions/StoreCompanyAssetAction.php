<?php

declare(strict_types=1);

namespace Modules\CompanyData\Application\Actions;

use Modules\CompanyData\Application\Repositories\CompanyAssetStorage;
use Modules\CompanyData\Application\Repositories\CompanyRepository;
use Modules\CompanyData\Domain\Enums\CompanyAsset;
use Modules\CompanyData\Infrastructure\Persistence\CompanyData;
use SplFileInfo;

/**
 * Replaces one brand asset: the new object is written first, the row is pointed
 * at its key, and only then is the previous object deleted. A failure at any
 * earlier step leaves the profile rendering the asset it already had.
 */
final class StoreCompanyAssetAction
{
    public function __construct(
        private readonly CompanyRepository $companies,
        private readonly CompanyAssetStorage $storage,
    ) {}

    public function execute(CompanyData $company, CompanyAsset $asset, SplFileInfo $file): CompanyData
    {
        $previous = $company->getAttribute($asset->column());

        $key = $this->storage->store($asset, (string) $company->uuid, $file);

        // Assignment rather than fill(): the asset columns stay out of the
        // mass-assignment allowlist on purpose, so a profile PUT can never
        // point them at an attacker-chosen key.
        $company->setAttribute($asset->column(), $key);

        $saved = $this->companies->save($company);

        $this->storage->forget(is_string($previous) ? $previous : null);

        return $saved;
    }
}
