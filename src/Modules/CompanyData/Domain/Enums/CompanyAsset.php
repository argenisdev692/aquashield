<?php

declare(strict_types=1);

namespace Modules\CompanyData\Domain\Enums;

/**
 * The four brand assets the company profile owns.
 *
 * The value is the route segment and the storage filename prefix; the column is
 * where the object key lives. Logos are public (emails and invoices render them
 * without a session); the signature is private and only ever reached through a
 * short-lived signed URL.
 */
enum CompanyAsset: string
{
    case LOGO = 'logo';
    case LOGO_WHITE = 'logo_white';
    case MARK = 'mark';
    case SIGNATURE = 'signature';

    public function column(): string
    {
        return match ($this) {
            self::LOGO => 'logo_path',
            self::LOGO_WHITE => 'logo_white_path',
            self::MARK => 'mark_path',
            self::SIGNATURE => 'signature_path',
        };
    }

    public function isPrivate(): bool
    {
        return $this === self::SIGNATURE;
    }

    /**
     * Logos become WebP (email clients that cannot draw WebP fall back to the
     * bundled PNG, see CompanyProfile::emailSafeLogoUrl). A signature stays PNG
     * so the stroke stays lossless for print.
     */
    public function extension(): string
    {
        return $this === self::SIGNATURE ? 'png' : 'webp';
    }
}
