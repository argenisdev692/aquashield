<?php

declare(strict_types=1);

namespace Modules\CompanyData\Infrastructure\Services;

use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Format;
use Intervention\Image\ImageManager;
use LogicException;
use Modules\CompanyData\Application\Repositories\CompanyAssetStorage;
use Modules\CompanyData\Domain\Enums\CompanyAsset;
use Shared\Domain\Ports\StoragePort;
use Shared\Infrastructure\Company\CompanyProfile;
use SplFileInfo;
use Throwable;

/**
 * Cloudflare R2 implementation of the module's asset port.
 *
 * Every upload is decoded and re-encoded before it is stored: that is what
 * strips EXIF/GPS payloads, normalises the format and kills a polyglot file
 * that is a valid image *and* a valid script. The declared MIME type is
 * therefore irrelevant — only bytes GD can decode survive.
 */
final class R2CompanyAssetStorage implements CompanyAssetStorage
{
    /** Retina-safe: wider than any header/footer slot renders. */
    private const int MAX_WIDTH = 1600;

    /** OWASP §8: private objects are reached through a 15-minute signed URL. */
    private const int SIGNED_URL_MINUTES = 15;

    public function __construct(
        private readonly StoragePort $storage,
    ) {}

    public function store(CompanyAsset $asset, string $companyUuid, SplFileInfo $file): string
    {
        $image = ImageManager::usingDriver(GdDriver::class)
            ->decodeSplFileInfo($file)
            ->scaleDown(width: self::MAX_WIDTH);

        $encoded = $asset === CompanyAsset::SIGNATURE
            ? $image->encodeUsingFormat(Format::PNG, strip: true)
            : $image->encodeUsingFormat(Format::WEBP, quality: 90, strip: true);

        $key = sprintf(
            'company/%s/%s-%s.%s',
            $companyUuid,
            $asset->value,
            Str::lower((string) Str::ulid()),
            $asset->extension(),
        );

        return $this->storage->put(
            $key,
            $encoded->toString(),
            $asset->isPrivate() ? 'private' : 'public',
        );
    }

    public function url(CompanyAsset $asset, ?string $key): ?string
    {
        if ($asset === CompanyAsset::SIGNATURE) {
            if ($key === null || $key === '') {
                return null;
            }

            try {
                return $this->storage->temporaryUrl($key, now()->addMinutes(self::SIGNED_URL_MINUTES));
            } catch (Throwable) {
                // Storage unreachable: the page shows "no signature on file"
                // rather than a broken image, and the next render retries.
                return null;
            }
        }

        return CompanyProfile::logoUrl($key, self::logoFallback($asset));
    }

    public function forget(?string $key): void
    {
        if ($key === null || $key === '' || str_starts_with($key, 'img/') || self::isAbsoluteUrl($key)) {
            return;
        }

        try {
            $this->storage->delete($key);
        } catch (Throwable) {
            // A failed cleanup must not roll back a save that already points at
            // the new object. The orphan is bounded by the four asset slots.
        }
    }

    private static function logoFallback(CompanyAsset $asset): string
    {
        return match ($asset) {
            CompanyAsset::LOGO => CompanyProfile::FALLBACK_LOGO,
            CompanyAsset::LOGO_WHITE => CompanyProfile::FALLBACK_LOGO_WHITE,
            CompanyAsset::MARK => CompanyProfile::FALLBACK_MARK,
            CompanyAsset::SIGNATURE => throw new LogicException('A signature has no bundled fallback.'),
        };
    }

    private static function isAbsoluteUrl(string $key): bool
    {
        return str_starts_with($key, 'http://') || str_starts_with($key, 'https://');
    }
}
