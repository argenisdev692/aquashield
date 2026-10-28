<?php

declare(strict_types=1);

namespace Modules\CompanyData\Application\Queries;

use Modules\CompanyData\Application\Dtos\CompanyAssetsDto;
use Modules\CompanyData\Application\Repositories\CompanyAssetStorage;
use Modules\CompanyData\Domain\Enums\CompanyAsset;
use Modules\CompanyData\Infrastructure\Persistence\CompanyData;

/**
 * One place that turns the four stored keys into renderable URLs, so the edit
 * screen and the asset endpoints can never disagree about a fallback.
 */
final class CompanyAssetsQuery
{
    public function __construct(
        private readonly CompanyAssetStorage $storage,
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
