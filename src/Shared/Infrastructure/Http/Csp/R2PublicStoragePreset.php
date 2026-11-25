<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Http\Csp;

use Spatie\Csp\Directive;
use Spatie\Csp\Policy;
use Spatie\Csp\Preset;

/**
 * Public R2 origin for images rendered straight from the bucket
 * (`StoragePort::publicUrl()` — logos, marks and other public company assets).
 *
 * `temporaryUrl()` signatures use the S3 endpoint origin instead; that one is
 * exposed by {@see DirectUploadStoragePreset::uploadOrigin()} and is also
 * needed in `img-src`.
 *
 * Cloudflare recommends a custom domain for production. This preset follows
 * whatever `R2_URL` / `filesystems.disks.r2.url` is configured, so switching
 * from the development `r2.dev` host to a custom domain needs no code change.
 */
final class R2PublicStoragePreset implements Preset
{
    public function configure(Policy $policy): void
    {
        $origin = self::publicOrigin();

        if ($origin !== null) {
            $policy->add(Directive::IMG, $origin);
        }
    }

    #[\NoDiscard]
    public static function publicOrigin(): ?string
    {
        $url = config('filesystems.disks.r2.url');

        if (! is_string($url) || $url === '') {
            return null;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($scheme) || ! is_string($host) || $host === '') {
            return null;
        }

        $scheme = strtolower($scheme);

        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        $port = parse_url($url, PHP_URL_PORT);

        return $scheme.'://'.$host.(is_int($port) ? ':'.$port : '');
    }
}
