<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Company;

use Shared\Domain\Ports\CompanyBrandingPort;

/**
 * Adapter between the Shared branding utility and the module-facing port.
 *
 * `CompanyProfile` stays the single implementation of the DB-first branding
 * and cache rules; modules depend only on {@see CompanyBrandingPort}.
 */
final readonly class CompanyProfileAdapter implements CompanyBrandingPort
{
    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return CompanyProfile::data();
    }

    public function forget(): void
    {
        CompanyProfile::forget();
    }

    public function logoUrl(?string $key, string $fallback): string
    {
        return CompanyProfile::logoUrl($key, $fallback);
    }

    public function fallbackLogo(): string
    {
        return CompanyProfile::FALLBACK_LOGO;
    }

    public function fallbackLogoWhite(): string
    {
        return CompanyProfile::FALLBACK_LOGO_WHITE;
    }

    public function fallbackMark(): string
    {
        return CompanyProfile::FALLBACK_MARK;
    }
}
