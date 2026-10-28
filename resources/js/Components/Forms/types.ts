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
 */
export interface AddressSelection {
    address: string;
    formatted_address: string;
    zip_code: string;
    city: string;
    state: string;
    country: string;
    latitude: number | null;
    longitude: number | null;
}
