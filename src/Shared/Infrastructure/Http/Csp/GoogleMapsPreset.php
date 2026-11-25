<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Http\Csp;

use Spatie\Csp\Directive;
use Spatie\Csp\Policy;
use Spatie\Csp\Preset;

/**
 * Opens the narrowest possible hole in the Basic policy for the Places
 * Autocomplete (New) API.
 *
 * The address field talks to Places through
 * `AutocompleteSuggestion.fetchAutocompleteSuggestions()` and renders the
 * results into our own Combobox, so no map tiles, no Maps DOM and no injected
 * inline styles are involved. That keeps this to SCRIPT (the loader bootstrap)
 * and CONNECT (the XHRs it makes) — notably it does NOT require
 * `style-src 'unsafe-inline'`, which embedding the `<gmp-place-autocomplete>`
 * web component would have forced on the whole application.
 */
final class GoogleMapsPreset implements Preset
{
    /**
     * Fallback host sources for browsers that do not implement CSP3
     * `'strict-dynamic'`. Modern browsers trust the nonced loader script and
     * the scripts it creates; the Maps loader copies the page nonce.
     *
     * @return list<string>
     */
    #[\NoDiscard]
    public static function scriptSources(): array
    {
        return ['https://maps.googleapis.com'];
    }

    /**
     * Places API (New) XHRs. No map tiles or Maps UI are used: the address
     * field renders suggestions into the app's own Combobox.
     *
     * @return list<string>
     */
    #[\NoDiscard]
    public static function connectSources(): array
    {
        return [
            'https://maps.googleapis.com',
            'https://places.googleapis.com',
        ];
    }

    public function configure(Policy $policy): void
    {
        $policy
            ->add(Directive::SCRIPT, self::scriptSources())
            ->add(Directive::CONNECT, self::connectSources());
    }
}
