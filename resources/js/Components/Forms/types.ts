export interface SelectOption {
    label: string;
    value: string | number;
    disabled?: boolean;
}

/**
 * The subset of a Google Place that maps 1:1 onto the profile address fields.
 *
 * `address` is only the street line; `address_2` is deliberately absent so the
 * component can never overwrite the apartment/suite the user typed by hand.
 *
 * The canonical declaration lives in `lib/google-maps.ts` because the mapping
 * code owns it. This re-export keeps the form-kit public surface stable.
 */
export type { AddressSelection } from '@/lib/google-maps';
