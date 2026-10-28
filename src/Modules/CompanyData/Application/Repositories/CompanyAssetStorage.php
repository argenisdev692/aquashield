<?php

declare(strict_types=1);

namespace Modules\CompanyData\Application\Repositories;

use Modules\CompanyData\Domain\Enums\CompanyAsset;
use SplFileInfo;

/**
 * Brand-asset storage port, owned by this module so the use cases never see
 * Cloudflare R2, S3 or Intervention Image.
 *
 * The key (not a URL) is what gets persisted: a permanent URL would freeze the
 * bucket origin into the row and make a later migration unrecoverable.
 */
interface CompanyAssetStorage
{
    /** Re-encode, persist, and return the object key. */
    public function store(CompanyAsset $asset, string $companyUuid, SplFileInfo $file): string;

    /**
     * Display URL for a stored key: the permanent public URL for a logo (it is
     * rendered in email and on invoices) or a short-lived signed URL for the
     * signature. Null when there is nothing to show or storage is unreachable.
     */
    public function url(CompanyAsset $asset, ?string $key): ?string;

    /**
     * Best-effort delete of a replaced object. Bundled paths (`img/...`) and
     * legacy absolute URLs are not ours to remove and are ignored.
     */
    public function forget(?string $key): void;
}
