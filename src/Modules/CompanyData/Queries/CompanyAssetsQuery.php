<?php

declare(strict_types=1);

namespace Modules\CompanyData\Queries;

use Modules\CompanyData\Data\CompanyAssetsDto;
use Modules\CompanyData\Enums\CompanyAsset;
use Modules\CompanyData\Models\CompanyData;
use Modules\CompanyData\Support\R2CompanyAssetStorage;

/**
 * One place that turns the four stored keys into renderable URLs, so the edit
 * screen and the asset endpoints can never disagree about a fallback.
 */
final class CompanyAssetsQuery
{
    public function __construct(
        private readonly R2CompanyAssetStorage $storage,
    ) {}

    public function execute(CompanyData $company): CompanyAssetsDto
    {
        return new CompanyAssetsDto(
            logoUrl: $this->storage->url(CompanyAsset::LOGO, $company->logo_path),
            logoWhiteUrl: $this->storage->url(CompanyAsset::LOGO_WHITE, $company->logo_white_path),
            markUrl: $this->storage->url(CompanyAsset::MARK, $company->mark_path),
            signatureUrl: $this->storage->url(CompanyAsset::SIGNATURE, $company->signature_path),
        );
    }
}
