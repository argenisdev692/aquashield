import { importLibrary, setOptions } from '@googlemaps/js-api-loader';

/**
 * One loader per page.
 *
 * `setOptions()` must run before the first `importLibrary()` call and only
 * once. Keeping that state in module scope means a second address field on the
 * same page reuses the already-loaded Places library instead of logging the
 * loader's "options already set" warning.
 */
let configured = false;

export async function loadPlacesLibrary(apiKey: string): Promise<void> {
    if (apiKey.trim() === '') {
        throw new Error('Google Maps API key is not configured.');
    }

    if (!configured) {
        setOptions({
            key: apiKey,
            v: 'weekly',
            language: 'en',
            region: 'US',
        });

        configured = true;
    }

    await importLibrary('places');
}
