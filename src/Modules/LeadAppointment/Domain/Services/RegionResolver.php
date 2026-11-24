<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Domain\Services;

use Modules\LeadAppointment\Domain\Enums\Region;

/**
 * The one authority for turning an address into a service region.
 *
 * Region is not a label: it decides the IANA timezone and the availability
 * calendar an appointment is booked against. A manually selected region can
 * contradict the address, so every write derives it here instead.
 *
 * ZIP prefixes are the primary signal because a postal code is tighter than a
 * city name. The city list is the fallback for payloads that carry a city but
 * no ZIP (some Meta and API leads). A Texas address outside both metros stays
 * null rather than being silently assigned to Houston.
 */
final readonly class RegionResolver
{
    /** @var list<string> */
    private const DALLAS_ZIP_PREFIXES = ['750', '751', '752', '753', '760', '761', '762'];

    /** @var list<string> */
    private const HOUSTON_ZIP_PREFIXES = ['770', '771', '772', '773', '774', '775', '776', '777', '778'];

    /** @var list<string> */
    private const DALLAS_CITIES = [
        'allen', 'arlington', 'bedford', 'burleson', 'carrollton', 'coppell', 'dallas',
        'denton', 'duncanville', 'euless', 'flower mound', 'fort worth', 'frisco',
        'garland', 'grand prairie', 'grapevine', 'hurst', 'irving', 'keller', 'lewisville',
        'mansfield', 'mckinney', 'mesquite', 'north richland hills', 'plano', 'richardson',
        'rockwall', 'rowlett', 'southlake', 'wylie',
    ];

    /** @var list<string> */
    private const HOUSTON_CITIES = [
        'alvin', 'baytown', 'bellaire', 'conroe', 'cypress', 'friendswood', 'galveston',
        'houston', 'humble', 'katy', 'kingwood', 'la porte', 'league city', 'magnolia',
        'missouri city', 'pasadena', 'pearland', 'richmond', 'rosenberg', 'spring',
        'stafford', 'sugar land', 'the woodlands', 'tomball',
    ];

    /**
     * The address wins. The fallback is only trusted when there is no address
     * data at all (a phone-only lead edited later) and it does not contradict
     * the state; an unsupported address must not inherit an old region.
     */
    public function resolve(
        ?string $state,
        ?string $city,
        ?string $zipCode,
        ?string $fallback = null,
    ): ?Region {
        $derived = $this->fromAddress($state, $city, $zipCode);

        if ($derived !== null) {
            return $derived;
        }

        if ($this->hasAddressData($state, $city, $zipCode)) {
            return null;
        }

        $fallbackRegion = $fallback === null ? null : Region::tryFrom($fallback);

        if ($fallbackRegion === null) {
            return null;
        }

        $normalizedState = $state === null ? '' : mb_strtoupper(trim($state));

        return $normalizedState === '' || $fallbackRegion->state() === $normalizedState
            ? $fallbackRegion
            : null;
    }

    public function fromAddress(?string $state, ?string $city, ?string $zipCode): ?Region
    {
        $normalizedState = $state === null ? '' : mb_strtoupper(trim($state));

        if ($normalizedState === 'FL') {
            return Region::Florida;
        }

        $zipPrefix = $this->zipPrefix($zipCode);

        if (in_array($zipPrefix, self::DALLAS_ZIP_PREFIXES, true)) {
            return Region::Dallas;
        }

        if (in_array($zipPrefix, self::HOUSTON_ZIP_PREFIXES, true)) {
            return Region::Houston;
        }

        if ($normalizedState !== 'TX') {
            return null;
        }

        $normalizedCity = $city === null ? '' : mb_strtolower(trim($city));

        if ($normalizedCity === '') {
            return null;
        }

        if (
            in_array($normalizedCity, self::DALLAS_CITIES, true)
            || str_contains($normalizedCity, 'dallas')
        ) {
            return Region::Dallas;
        }

        if (
            in_array($normalizedCity, self::HOUSTON_CITIES, true)
            || str_contains($normalizedCity, 'houston')
        ) {
            return Region::Houston;
        }

        return null;
    }

    private function hasAddressData(?string $state, ?string $city, ?string $zipCode): bool
    {
        foreach ([$state, $city, $zipCode] as $value) {
            if ($value !== null && trim($value) !== '') {
                return true;
            }
        }

        return false;
    }

    private function zipPrefix(?string $zipCode): string
    {
        if ($zipCode === null) {
            return '';
        }

        $digits = preg_replace('/\D/', '', $zipCode) ?? '';

        return mb_strlen($digits) >= 3 ? mb_substr($digits, 0, 3) : '';
    }
}
