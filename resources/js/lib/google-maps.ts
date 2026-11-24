import { importLibrary, setOptions } from '@googlemaps/js-api-loader';

/**
 * Google Places (New) — the one browser transport for address autocomplete.
 *
 * The key is a browser key restricted by HTTP referrer in Google Cloud; it is
 * public by design (see `config/services.php`). Every request is restricted to
 * US addresses, which is a product rule for this CRM: `includedRegionCodes`
 * filters the suggestions, and `toAddressSelection()` re-checks the selected
 * place so a manually forged response cannot slip a foreign address through
 * the client-side convenience layer.
 *
 * The API shape is Places API (New):
 *   AutocompleteSuggestion.fetchAutocompleteSuggestions()
 *   → PlacePrediction.toPlace()
 *   → Place.fetchFields({ fields: [...] })
 *
 * A session token is generated per search session and discarded after a place
 * is selected, so Google bills one autocomplete session instead of one request
 * per keystroke.
 */

export interface AddressSelection {
    /** Street line only, e.g. "123 Main St". Never includes address_2. */
    address: string;
    formatted_address: string;
    zip_code: string;
    city: string;
    state: string;
    country: string;
    latitude: number | null;
    longitude: number | null;
}

export interface AddressSuggestion {
    id: string;
    main_text: string;
    secondary_text: string;
    prediction: google.maps.places.PlacePrediction;
}

export class PlacesConfigurationError extends Error {
    constructor(message = 'Google Maps is not configured for this environment.') {
        super(message);
        this.name = 'PlacesConfigurationError';
    }
}

export class AddressOutsideUsError extends Error {
    constructor() {
        super('Choose a United States address. Address search is limited to the US.');
        this.name = 'AddressOutsideUsError';
    }
}

let configured = false;

/**
 * One loader per page.
 *
 * `setOptions()` must run before the first `importLibrary()` call and only
 * once. Keeping that state in module scope means a second address field on the
 * same page reuses the already-loaded Places library instead of logging the
 * loader's "options already set" warning.
 */
export async function loadPlacesLibrary(apiKey: string): Promise<google.maps.PlacesLibrary> {
    if (apiKey.trim() === '') {
        throw new PlacesConfigurationError(
            'Address autocomplete needs a Google Maps browser key. Add GOOGLE_MAPS_API_KEY and reload.',
        );
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

    return importLibrary('places');
}

export async function createAutocompleteSessionToken(
    apiKey: string,
): Promise<google.maps.places.AutocompleteSessionToken> {
    const { AutocompleteSessionToken } = await loadPlacesLibrary(apiKey);

    return new AutocompleteSessionToken();
}

export async function fetchAddressSuggestions(
    apiKey: string,
    input: string,
    sessionToken: google.maps.places.AutocompleteSessionToken,
): Promise<AddressSuggestion[]> {
    const { AutocompleteSuggestion } = await loadPlacesLibrary(apiKey);

    const { suggestions } = await AutocompleteSuggestion.fetchAutocompleteSuggestions({
        input,
        sessionToken,
        // Product rule: US addresses only.
        includedRegionCodes: ['us'],
        includedPrimaryTypes: ['street_address', 'premise', 'route'],
        language: 'en-US',
        region: 'us',
    });

    return suggestions.flatMap((suggestion) => {
        const prediction = suggestion.placePrediction;

        if (prediction === null) {
            return [];
        }

        return [
            {
                id: prediction.placeId,
                main_text: prediction.mainText?.text ?? prediction.text.text,
                secondary_text: prediction.secondaryText?.text ?? '',
                prediction,
            },
        ];
    });
}

const componentText = (
    components: readonly google.maps.places.AddressComponent[],
    type: string,
    short = false,
): string => {
    const component = components.find((candidate) => candidate.types.includes(type));

    if (component === undefined) {
        return '';
    }

    const value = short ? component.shortText : component.longText;

    return typeof value === 'string' ? value : '';
};

const streetLine = (
    components: readonly google.maps.places.AddressComponent[],
    formattedAddress: string,
): string => {
    const number = componentText(components, 'street_number');
    const route = componentText(components, 'route');
    const line = [number, route].filter((part) => part !== '').join(' ');

    if (line !== '') {
        return line;
    }

    const premise = componentText(components, 'premise');

    if (premise !== '') {
        return premise;
    }

    return formattedAddress.split(',')[0]?.trim() ?? '';
};

const postalCode = (components: readonly google.maps.places.AddressComponent[]): string => {
    const code = componentText(components, 'postal_code');
    const suffix = componentText(components, 'postal_code_suffix');

    if (code === '') {
        return '';
    }

    return suffix === '' ? code : `${code}-${suffix}`;
};

/**
 * Turn a selected prediction into the exact subset of address fields the
 * profile owns. `address_2` is deliberately absent: an apartment or suite is
 * typed by hand and must never be overwritten by an autocomplete result.
 */
export async function toAddressSelection(suggestion: AddressSuggestion): Promise<AddressSelection> {
    const place = suggestion.prediction.toPlace();

    await place.fetchFields({
        fields: ['addressComponents', 'formattedAddress', 'location'],
    });

    const components = place.addressComponents ?? [];
    const formattedAddress = place.formattedAddress ?? suggestion.main_text;

    // `includedRegionCodes` already filters this, but a selected place is the
    // final authority: never write a non-US address into the form.
    const countryCode = componentText(components, 'country', true);

    if (countryCode !== '' && countryCode !== 'US') {
        throw new AddressOutsideUsError();
    }

    const location = place.location;

    return {
        address: streetLine(components, formattedAddress),
        formatted_address: formattedAddress,
        zip_code: postalCode(components),
        city:
            componentText(components, 'locality') ||
            componentText(components, 'postal_town') ||
            componentText(components, 'sublocality_level_1') ||
            componentText(components, 'administrative_area_level_2'),
        state:
            componentText(components, 'administrative_area_level_1', true) ||
            componentText(components, 'administrative_area_level_1'),
        country: componentText(components, 'country') || 'United States',
        latitude: location?.lat() ?? null,
        longitude: location?.lng() ?? null,
    };
}
