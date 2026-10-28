declare namespace App {
    namespace Data {
        namespace Auth {
            export type AuthTokenData = {
                readonly token: string;
                readonly token_type: string;
                readonly expires_at: string;
                readonly user: App.Data.Auth.UserData;
            };
            export type IdleSessionData = {
                readonly warnAfterSeconds: number;
                readonly graceSeconds: number;
                readonly keepAliveEndpoint: string;
                readonly logoutEndpoint: string;
            };
            export type ProfilePhotoData = {
                readonly profile_photo_url: string | null;
            };
            export type UserData = {
                readonly uuid: string;
                readonly first_name: string;
                readonly last_name: string | null;
                readonly name: string;
                readonly username: string | null;
                readonly email: string;
                readonly email_verified: boolean;
                readonly phone: string | null;
                readonly date_of_birth: string | null;
                readonly address: string | null;
                readonly address_2: string | null;
                readonly zip_code: string | null;
                readonly city: string | null;
                readonly state: string | null;
                readonly country: string | null;
                readonly gender: string | null;
                readonly profile_photo_url: string | null;
                readonly latitude: number | null;
                readonly longitude: number | null;
                readonly terms_and_conditions: boolean;
                readonly registered_by_id: number | null;
                readonly two_factor_enabled: boolean;
                readonly permissions: string[];
            };
        }
    }
    namespace Enums {
        export type SecurityEvent =
            | 'password.changed'
            | '2fa.enabled'
            | '2fa.disabled'
            | '2fa.recovery_codes_regenerated'
            | 'auth.account_locked'
            | 'email.changed';
    }
}
declare namespace Illuminate {
    export type CursorPaginator<TKey, TValue> = {
        data: TKey extends string ? Record<TKey, TValue> : TValue[];
        links: {
            url: string | null;
            label: string;
            active: boolean;
        }[];
        meta: {
            path: string;
            per_page: number;
            next_cursor: string | null;
            next_page_url: string | null;
            prev_cursor: string | null;
            prev_page_url: string | null;
        };
    };
    export type CursorPaginatorInterface<TKey, TValue> = Illuminate.CursorPaginator<TKey, TValue>;
    export type LengthAwarePaginator<TKey, TValue> = {
        data: TKey extends string ? Record<TKey, TValue> : TValue[];
        links: {
            url: string | null;
            label: string;
            active: boolean;
        }[];
        meta: {
            total: number;
            current_page: number;
            first_page_url: string;
            from: number | null;
            last_page: number;
            last_page_url: string;
            next_page_url: string | null;
            path: string;
            per_page: number;
            prev_page_url: string | null;
            to: number | null;
        };
    };
    export type LengthAwarePaginatorInterface<TKey, TValue> = Illuminate.LengthAwarePaginator<
        TKey,
        TValue
    >;
}
declare namespace Modules {
    namespace CompanyData {
        namespace Application {
            namespace Dtos {
                export type CompanyAssetsDto = {
                    readonly logo_url: string | null;
                    readonly logo_white_url: string | null;
                    readonly mark_url: string | null;
                    readonly signature_url: string | null;
                };
                export type CompanyBrandingDto = {
                    readonly name: string;
                    readonly description: string | null;
                    readonly url: string | null;
                    readonly logo_url: string;
                    readonly logo_white_url: string;
                    readonly mark_url: string;
                    readonly address: string | null;
                    readonly address_2: string | null;
                    readonly zip_code: string | null;
                    readonly city: string | null;
                    readonly state: string | null;
                    readonly country: string | null;
                    readonly country_code: string | null;
                    readonly phone: string | null;
                    readonly support_email: string | null;
                    readonly latitude: number | null;
                    readonly longitude: number | null;
                    readonly socials: Modules.CompanyData.Application.Dtos.CompanySocialsDto;
                };
                export type CompanyDataDto = {
                    readonly uuid: string;
                    readonly name: string | null;
                    readonly company_name: string;
                    readonly email: string | null;
                    readonly description: string | null;
                    readonly phone: string | null;
                    readonly nif_nipc: string | null;
                    readonly nie: string | null;
                    readonly bank_beneficiary: string | null;
                    readonly bank_iban: string | null;
                    readonly bank_bic: string | null;
                    readonly bank_name: string | null;
                    readonly invoice_notes: string | null;
                    readonly address: string | null;
                    readonly address_2: string | null;
                    readonly zip_code: string | null;
                    readonly city: string | null;
                    readonly state: string | null;
                    readonly country: string | null;
                    readonly country_code: string | null;
                    readonly latitude: number | null;
                    readonly longitude: number | null;
                    readonly website: string | null;
                    readonly facebook_link: string | null;
                    readonly instagram_link: string | null;
                    readonly linkedin_link: string | null;
                    readonly twitter_link: string | null;
                    readonly tiktok_link: string | null;
                    readonly logo_path: string | null;
                    readonly logo_white_path: string | null;
                    readonly mark_path: string | null;
                };
                export type CompanySocialsDto = {
                    readonly linkedin: string | null;
                    readonly twitter: string | null;
                    readonly instagram: string | null;
                    readonly facebook: string | null;
                    readonly tiktok: string | null;
                    readonly github: string | null;
                };
                export type SaveCompanyDataData = {
                    readonly name: string;
                    readonly company_name: string;
                    readonly email: string;
                    readonly description: string | null;
                    readonly phone: string | null;
                    readonly nif_nipc: string | null;
                    readonly nie: string | null;
                    readonly bank_beneficiary: string | null;
                    readonly bank_iban: string | null;
                    readonly bank_bic: string | null;
                    readonly bank_name: string | null;
                    readonly invoice_notes: string | null;
                    readonly address: string | null;
                    readonly address_2: string | null;
                    readonly zip_code: string | null;
                    readonly city: string | null;
                    readonly state: string | null;
                    readonly country: string | null;
                    readonly country_code: string | null;
                    readonly latitude: number | null;
                    readonly longitude: number | null;
                    readonly website: string | null;
                    readonly facebook_link: string | null;
                    readonly instagram_link: string | null;
                    readonly linkedin_link: string | null;
                    readonly twitter_link: string | null;
                    readonly tiktok_link: string | null;
                };
            }
        }
        namespace Domain {
            namespace Enums {
                export type CompanyAsset = 'logo' | 'logo_white' | 'mark' | 'signature';
            }
        }
    }
    namespace Users {
        namespace Data {
            export type AssignPermissionsData = {
                readonly permissions: string[];
            };
            export type AssignRolesData = {
                readonly roles: string[];
            };
            export type CreateUserData = {
                readonly first_name: string;
                readonly email: string;
                readonly last_name: string | null;
                readonly username: string | null;
                readonly phone: string | null;
                readonly date_of_birth: string | null;
                readonly address: string | null;
                readonly address_2: string | null;
                readonly zip_code: string | null;
                readonly city: string | null;
                readonly state: string | null;
                readonly country: string | null;
                readonly latitude: number | null;
                readonly longitude: number | null;
                readonly roles: string[];
                readonly permissions: string[];
            };
            export type ManagedUserData = {
                readonly uuid: string;
                readonly first_name: string;
                readonly last_name: string | null;
                readonly name: string;
                readonly username: string | null;
                readonly email: string;
                readonly email_verified: boolean;
                readonly phone: string | null;
                readonly date_of_birth: string | null;
                readonly address: string | null;
                readonly address_2: string | null;
                readonly zip_code: string | null;
                readonly city: string | null;
                readonly state: string | null;
                readonly country: string | null;
                readonly latitude: number | null;
                readonly longitude: number | null;
                readonly roles: string[];
                readonly direct_permissions: string[];
                readonly deleted_at: string | null;
                readonly created_at: string | null;
                readonly updated_at: string | null;
            };
            export type RoleOptionData = {
                readonly name: string;
                readonly permissions: string[];
            };
            export type UpdateUserData = {
                readonly first_name: string;
                readonly email: string;
                readonly last_name: string | null;
                readonly username: string | null;
                readonly phone: string | null;
                readonly date_of_birth: string | null;
                readonly address: string | null;
                readonly address_2: string | null;
                readonly zip_code: string | null;
                readonly city: string | null;
                readonly state: string | null;
                readonly country: string | null;
                readonly latitude: number | null;
                readonly longitude: number | null;
            };
            export type UserAccessCatalogData = {
                readonly roles: Modules.Users.Data.RoleOptionData[];
                readonly permissions: string[];
            };
            export type UserFilterData = {
                readonly role: string | null;
                readonly permission: string | null;
                readonly sort_field: string;
                readonly sort_order: number;
                readonly page: number;
                readonly per_page: number;
                search: string | null;
                status: string | null;
                date_from: string | null;
                date_to: string | null;
            };
        }
    }
}
declare namespace Shared {
    namespace Application {
        namespace DTOs {
            export type BulkUuidsData = {
                uuids: string[];
            };
            export type CookieConsentData = {
                readonly message: string;
                readonly acceptLabel: string;
                readonly endpoint: string;
            };
            export type SoftDeleteFilterData = {
                search: string | null;
                status: string | null;
                date_from: string | null;
                date_to: string | null;
            };
        }
    }
    namespace Domain {
        namespace Enums {
            export type BillingUnit = 'UNIT' | 'HOUR' | 'SESSION' | 'DAY' | 'MONTH';
            export type Currency = 'EUR' | 'USD' | 'GBP';
        }
    }
    namespace Infrastructure {
        namespace Resilience {
            namespace CircuitBreaker {
                export type CircuitBreakerState = 'closed' | 'open' | 'half_open';
            }
        }
    }
}
declare namespace Spatie {
    namespace LaravelData {
        export type CursorPaginatedDataCollection<TKey, TValue> = Illuminate.CursorPaginator<
            TKey,
            TValue
        >;
        export type PaginatedDataCollection<TKey, TValue> = Illuminate.LengthAwarePaginator<
            TKey,
            TValue
        >;
    }
}
