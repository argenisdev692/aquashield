/**
 * Client mirror of Modules\LeadAppointment\Domain\Services\RegionResolver.
 *
 * The server re-derives the region on every write and ignores a contradictory
 * client value. This copy exists so the lead form can show the derived region
 * immediately and keep it in step with the selected Google address. Keep the
 * ZIP prefixes and city lists in lockstep with the PHP resolver.
 */
export type ServiceRegion = '' | 'houston' | 'dallas' | 'florida';

const DALLAS_ZIP_PREFIXES = new Set(['750', '751', '752', '753', '760', '761', '762']);

const HOUSTON_ZIP_PREFIXES = new Set([
    '770',
    '771',
    '772',
    '773',
    '774',
    '775',
    '776',
    '777',
    '778',
]);

const DALLAS_CITIES = new Set([
    'allen',
    'arlington',
    'bedford',
    'burleson',
    'carrollton',
    'coppell',
    'dallas',
    'denton',
    'duncanville',
    'euless',
    'flower mound',
    'fort worth',
    'frisco',
    'garland',
    'grand prairie',
    'grapevine',
    'hurst',
    'irving',
    'keller',
    'lewisville',
    'mansfield',
    'mckinney',
    'mesquite',
    'north richland hills',
    'plano',
    'richardson',
    'rockwall',
    'rowlett',
    'southlake',
    'wylie',
]);

const HOUSTON_CITIES = new Set([
    'alvin',
    'baytown',
    'bellaire',
    'conroe',
    'cypress',
    'friendswood',
    'galveston',
    'houston',
    'humble',
    'katy',
    'kingwood',
    'la porte',
    'league city',
    'magnolia',
    'missouri city',
    'pasadena',
    'pearland',
    'richmond',
    'rosenberg',
    'spring',
    'stafford',
    'sugar land',
    'the woodlands',
    'tomball',
]);

const zipPrefix = (zipCode: string): string => zipCode.replace(/\D/g, '').slice(0, 3);

export const regionForAddress = (state: string, city: string, zipCode: string): ServiceRegion => {
    const normalizedState = state.trim().toUpperCase();

    if (normalizedState === 'FL') {
        return 'florida';
    }

    const prefix = zipPrefix(zipCode);

    if (DALLAS_ZIP_PREFIXES.has(prefix)) {
        return 'dallas';
    }

    if (HOUSTON_ZIP_PREFIXES.has(prefix)) {
        return 'houston';
    }

    if (normalizedState !== 'TX') {
        return '';
    }

    const normalizedCity = city.trim().toLowerCase();

    if (normalizedCity === '') {
        return '';
    }

    if (DALLAS_CITIES.has(normalizedCity) || normalizedCity.includes('dallas')) {
        return 'dallas';
    }

    if (HOUSTON_CITIES.has(normalizedCity) || normalizedCity.includes('houston')) {
        return 'houston';
    }

    return '';
};

export const regionLabel = (region: string): string => {
    switch (region) {
        case 'houston':
            return 'Houston';
        case 'dallas':
            return 'Dallas';
        case 'florida':
            return 'Florida';
        default:
            return '';
    }
};

/**
 * The server resolves a lead's region to this timezone when it parses the
 * wall-clock start. Mirror it so the form hint names the zone that is actually
 * used instead of the configured fallback.
 */
export const regionTimezone = (region: string | null | undefined): string | null => {
    switch (region) {
        case 'houston':
        case 'dallas':
            return 'America/Chicago';
        case 'florida':
            return 'America/New_York';
        default:
            return null;
    }
};

export const isServiceRegion = (value: string | null | undefined): value is ServiceRegion =>
    value === '' || value === 'houston' || value === 'dallas' || value === 'florida';
