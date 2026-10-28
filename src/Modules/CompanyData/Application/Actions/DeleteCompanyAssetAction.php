<?php

declare(strict_types=1);

namespace Modules\CompanyData\Application\Actions;

use Modules\CompanyData\Application\Repositories\CompanyAssetStorage;
use Modules\CompanyData\Application\Repositories\CompanyRepository;
use Modules\CompanyData\Domain\Enums\CompanyAsset;
use Modules\CompanyData\Infrastructure\Persistence\CompanyData;

/**
 * Clears one brand asset. Logos fall back to the bundled artwork; the signature
 * becomes "none on file". The object is deleted after the row stops pointing at
 * it, never before.
 */
final class DeleteCompanyAssetAction
{
    public function __construct(
        private readonly CompanyRepository $companies,
        private readonly CompanyAssetStorage $storage,
    ) {}

    public function execute(CompanyData $company, CompanyAsset $asset): CompanyData
    {
        $previous = $company->getAttribute($asset->column());

        $company->setAttribute($asset->column(), null);

        $saved = $this->companies->save($company);

        $this->storage->forget(is_string($previous) ? $previous : null);

        return $saved;
    }
}
