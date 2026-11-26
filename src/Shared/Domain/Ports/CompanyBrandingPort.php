<?php

declare(strict_types=1);

namespace Shared\Domain\Ports;

/**
 * Read/invalidate the company branding used by the welcome page, emails and
 * PDFs.
 *
 * This port exists so a module never imports `Shared\Infrastructure\Company`
 * directly. The concrete implementation is wired in SharedServiceProvider.
 */
interface CompanyBrandingPort
{
    /**
     * @return array<string, mixed>
     */
    public function data(): array;

    public function forget(): void;

    public function logoUrl(?string $key, string $fallback): string;

    public function fallbackLogo(): string;

    public function fallbackLogoWhite(): string;

    public function fallbackMark(): string;
}
