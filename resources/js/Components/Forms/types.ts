export interface SelectOption<T extends string | number = string | number> {
    label: string;
    value: T;
    disabled?: boolean;
    /** Secondary line rendered under the label by `RadioGroupField` cards. */
    description?: string;
}

/**
 * The projection a record picker needs from the page's search composable.
 *
 * `value` is the foreign key the form stores; `label` is what the user reads.
 * `description` is the optional second line (company, email, stage…) that makes
 * two records with the same name distinguishable.
 */
export interface LookupOption {
    value: string | number;
    label: string;
    description?: string;
    /** Optional section heading, e.g. `Contacts` vs `Companies`. */
    group?: string;
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
