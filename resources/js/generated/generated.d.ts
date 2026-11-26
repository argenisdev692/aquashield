declare namespace App {
    namespace Data {
        export type SavedViewData = {
            readonly uuid: string;
            readonly resource: string;
            readonly name: string;
            readonly search: string | null;
            readonly sort_field: string | null;
            readonly sort_order: number;
            readonly per_page: number;
            readonly filters: Record<string, string | string[]>;
        };
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
            export type PasskeyData = {
                readonly id: number;
                readonly name: string;
                readonly authenticator: string | null;
                readonly created_at: string | null;
                readonly last_used_at: string | null;
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
            | 'email.changed'
            | 'passkey.registered'
            | 'passkey.deleted';
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
    namespace AccessControl {
        namespace Data {
            export type FilterOptionsData = {
                readonly permissions: string[];
                readonly roles: string[];
                readonly groups: string[];
            };
            export type PermissionCatalogData = {
                readonly permissions: Modules.AccessControl.Data.PermissionOptionData[];
            };
            export type PermissionData = {
                readonly uuid: string;
                readonly name: string;
                readonly guard_name: string;
                readonly group: string;
                readonly is_system: boolean;
                readonly roles_count: number;
                readonly users_count: number;
                readonly created_at: string | null;
                readonly updated_at: string | null;
            };
            export type PermissionFilterData = {
                readonly search: string | null;
                readonly type: string | null;
                readonly group: string | null;
                readonly role: string | null;
                readonly date_from: string | null;
                readonly date_to: string | null;
                readonly sort_field: string;
                readonly sort_order: number;
                readonly page: number;
                readonly per_page: number;
            };
            export type PermissionInputData = {
                readonly name: string;
            };
            export type PermissionOptionData = {
                readonly name: string;
                readonly group: string;
                readonly is_system: boolean;
            };
            export type RoleData = {
                readonly uuid: string;
                readonly name: string;
                readonly guard_name: string;
                readonly is_system: boolean;
                readonly permissions_count: number;
                readonly users_count: number;
                readonly created_at: string | null;
                readonly updated_at: string | null;
            };
            export type RoleDetailData = {
                readonly uuid: string;
                readonly name: string;
                readonly guard_name: string;
                readonly is_system: boolean;
                readonly permissions: string[];
                readonly users_count: number;
                readonly created_at: string | null;
                readonly updated_at: string | null;
            };
            export type RoleFilterData = {
                readonly search: string | null;
                readonly type: string | null;
                readonly permission: string | null;
                readonly date_from: string | null;
                readonly date_to: string | null;
                readonly sort_field: string;
                readonly sort_order: number;
                readonly page: number;
                readonly per_page: number;
            };
            export type RoleInputData = {
                readonly name: string;
                readonly permissions: string[];
            };
        }
    }
    namespace ActivityLog {
        namespace Data {
            export type ActivityEntryData = {
                readonly uuid: string;
                readonly log_name: string;
                readonly event: string;
                readonly description: string;
                readonly causer_name: string | null;
                readonly subject_type: string | null;
                readonly properties: Record<string, string | number | string[]>;
                readonly created_at: string;
            };
            export type ActivityFilterData = {
                readonly search: string | null;
                readonly log_name: string | null;
                readonly event: string | null;
                readonly subject_type: string | null;
                readonly subject_id: string | null;
                readonly date_from: string | null;
                readonly date_to: string | null;
                readonly timezone: string | null;
                readonly sort_field: string;
                readonly sort_order: number;
                readonly page: number;
                readonly per_page: number;
            };
        }
    }
    namespace Backups {
        namespace Data {
            export type BackupActivityData = {
                readonly uuid: string;
                readonly event: string;
                readonly description: string;
                readonly causer_name: string | null;
                readonly properties: Record<string, string | number>;
                readonly created_at: string;
            };
            export type BackupArchiveData = {
                readonly uuid: string;
                readonly disk: string;
                readonly filename: string;
                readonly size_bytes: number;
                readonly size_human: string;
                readonly taken_at: string;
                readonly age_days: number;
                readonly is_newest: boolean;
                readonly is_trashed: boolean;
            };
            export type BackupDetailData = {
                readonly backup: Modules.Backups.Data.BackupArchiveData;
                readonly activities: Modules.Backups.Data.BackupActivityData[];
            };
            export type BackupFilterData = {
                readonly search: string | null;
                readonly status: string;
                readonly disk: string | null;
                readonly date_from: string | null;
                readonly date_to: string | null;
                readonly sort_field: string;
                readonly sort_order: number;
                readonly page: number;
                readonly per_page: number;
            };
            export type BackupListData = {
                readonly rows: Modules.Backups.Data.BackupArchiveData[];
                readonly total: number;
                readonly page: number;
                readonly per_page: number;
                readonly last_page: number;
            };
        }
    }
    namespace CompanyData {
        namespace Data {
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
                readonly socials: Modules.CompanyData.Data.CompanySocialsDto;
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
        namespace Enums {
            export type CompanyAsset = 'logo' | 'logo_white' | 'mark' | 'signature';
        }
    }
    namespace LeadAppointment {
        namespace Application {
            namespace Dtos {
                export type AppointmentData = {
                    uuid: string;
                    lead_uuid: string;
                    customer_name: string;
                    customer_email: string | null;
                    customer_phone: string | null;
                    address_line: string;
                    kind: string;
                    status: string;
                    starts_at: string;
                    ends_at: string;
                    starts_at_local: string;
                    ends_at_local: string;
                    timezone: string;
                    region: string;
                    previous_starts_at: string | null;
                    google_event_id: string | null;
                    notes: string | null;
                    cancel_reason: string | null;
                    created_at: string | null;
                    deleted_at: string | null;
                };
                export type AppointmentFilterData = {
                    readonly appointment_status: string | null;
                    readonly record_state: string | null;
                    readonly kind: string | null;
                    readonly region: string | null;
                    readonly sort_field: string;
                    readonly sort_order: number;
                    readonly page: number;
                    readonly per_page: number;
                    search: string | null;
                    status: string | null;
                    date_from: string | null;
                    date_to: string | null;
                };
                export type AssignableOwnerData = {
                    uuid: string;
                    name: string;
                };
                export type AvailabilityCalendarData = {
                    data: Modules.LeadAppointment.Application.Dtos.AvailabilityDayData[];
                };
                export type AvailabilityDayData = {
                    date: string;
                    is_open: boolean;
                    source: string;
                    reason: string | null;
                    windows: {
                        start: string;
                        end: string;
                    }[];
                };
                export type AvailabilityExceptionData = {
                    uuid: string;
                    date: string;
                    is_available: boolean;
                    start_time: string | null;
                    end_time: string | null;
                    reason: string | null;
                    source: string;
                    deleted_at: string | null;
                };
                export type AvailabilityExceptionFilterData = {
                    readonly availability: string | null;
                    readonly source: string | null;
                    readonly record_state: string;
                    readonly sort_field: string;
                    readonly sort_order: number;
                    readonly page: number;
                    readonly per_page: number;
                    search: string | null;
                    status: string | null;
                    date_from: string | null;
                    date_to: string | null;
                };
                export type AvailabilityRuleData = {
                    uuid: string;
                    day_of_week: number;
                    day_label: string;
                    start_time: string;
                    end_time: string;
                    is_available: boolean;
                };
                export type AvailabilityWindowFilterData = {
                    from: string | null;
                    to: string | null;
                    region: string | null;
                };
                export type CalendarDayData = {
                    date: string;
                    is_open: boolean;
                    source: string;
                    reason: string | null;
                    windows: {
                        start: string;
                        end: string;
                    }[];
                    appointments: Modules.LeadAppointment.Application.Dtos.AppointmentData[];
                };
                export type CalendarEventData = {
                    uuid: string;
                    lead_uuid: string;
                    customer_name: string;
                    starts_at_local: string;
                    timezone: string;
                    status: string;
                    kind: string;
                    region: string;
                };
                export type CalendarEventsData = {
                    data: Modules.LeadAppointment.Application.Dtos.CalendarEventData[];
                };
                export type CalendarFeedData = {
                    from: string;
                    to: string;
                    timezone: string;
                    days: Modules.LeadAppointment.Application.Dtos.CalendarDayData[];
                };
                export type CalendarWindowFilterData = {
                    from: string;
                    to: string;
                };
                export type CancelAppointmentData = {
                    reason: string;
                };
                export type CaptureLeadData = {
                    firstName: string;
                    lastName: string;
                    companyName: string | null;
                    email: string | null;
                    phone: string | null;
                    address: string | null;
                    address2: string | null;
                    city: string | null;
                    state: string | null;
                    zipCode: string | null;
                    region: string | null;
                    damageDescription: string | null;
                    isEmergency: boolean;
                    smsConsent: boolean;
                    consentTextVersion: string | null;
                    preferredLanguage: string;
                    sourcePage: string | null;
                    utmSource: string | null;
                    utmMedium: string | null;
                    utmCampaign: string | null;
                    utmContent: string | null;
                    utmTerm: string | null;
                    ip: string | null;
                    userAgent: string | null;
                    ownerUuid: string | null;
                    notes: string | null;
                    insuranceProperty: boolean;
                    message: string | null;
                    intentToClaim: boolean | null;
                    followUpDate: string | null;
                    inspectionDate: string | null;
                    inspectionTime: string | null;
                    inspectionStatus: Modules.LeadAppointment.Domain.Enums.InspectionStatus | null;
                    additionalNote: string | null;
                    latitude: number | null;
                    longitude: number | null;
                };
                export type DashboardMetricData = {
                    current: number;
                    previous: number;
                };
                export type DashboardStatsData = {
                    leads: Modules.LeadAppointment.Application.Dtos.DashboardMetricData;
                    inspections: Modules.LeadAppointment.Application.Dtos.DashboardMetricData;
                    won: Modules.LeadAppointment.Application.Dtos.DashboardMetricData;
                    appointments: Modules.LeadAppointment.Application.Dtos.DashboardMetricData;
                };
                export type HolidayResyncResultData = {
                    purged: number;
                    created: number;
                };
                export type HolidaySyncResultData = {
                    country: string;
                    created: number;
                    skipped: number;
                };
                export type LeadActivityData = {
                    uuid: string;
                    type: string;
                    outcome: string | null;
                    notes: string | null;
                    occurred_at: string;
                    actor_name: string | null;
                };
                export type LeadBoardColumnData = {
                    status: string;
                    label: string;
                    total: number;
                    leads: Modules.LeadAppointment.Application.Dtos.LeadCardData[];
                };
                export type LeadCardData = {
                    uuid: string;
                    full_name: string;
                    phone: string | null;
                    city: string | null;
                    state: string | null;
                    region: string | null;
                    source: string;
                    status: string;
                    is_emergency: boolean;
                    is_spam: boolean;
                    last_contacted_at: string | null;
                    inspection_at: string | null;
                    created_at: string | null;
                };
                export type LeadData = {
                    uuid: string;
                    first_name: string;
                    last_name: string;
                    full_name: string;
                    company_name: string | null;
                    email: string | null;
                    phone: string | null;
                    address: string | null;
                    address2: string | null;
                    city: string | null;
                    state: string | null;
                    zip_code: string | null;
                    address_line: string;
                    region: string | null;
                    source: string;
                    source_detail: string | null;
                    status: string;
                    owner_uuid: string | null;
                    owner_name: string | null;
                    duplicate_of_uuid: string | null;
                    is_emergency: boolean;
                    is_spam: boolean;
                    spam_score: number;
                    spam_reasons: string[];
                    preferred_language: string;
                    damage_description: string | null;
                    insurance_carrier: string | null;
                    claim_number: string | null;
                    sms_consent: boolean;
                    sms_consent_at: string | null;
                    last_contacted_at: string | null;
                    converted_at: string | null;
                    notes: string | null;
                    attribution: Record<string, string> | null;
                    created_at: string | null;
                    updated_at: string | null;
                    deleted_at: string | null;
                    insurance_property: boolean;
                    message: string | null;
                    intent_to_claim: boolean | null;
                    follow_up_date: string | null;
                    additional_note: string | null;
                    latitude: number | null;
                    longitude: number | null;
                    registration_date: string | null;
                    inspection_date: string | null;
                    inspection_time: string | null;
                    inspection_status: string | null;
                };
                export type LeadFilterData = {
                    readonly pipeline_status: string | null;
                    readonly record_state: string | null;
                    readonly source: string | null;
                    readonly region: string | null;
                    readonly owner_uuid: string | null;
                    readonly emergency: string | null;
                    readonly spam: string | null;
                    readonly sort_field: string;
                    readonly sort_order: number;
                    readonly page: number;
                    readonly per_page: number;
                    search: string | null;
                    status: string | null;
                    date_from: string | null;
                    date_to: string | null;
                };
                export type LeadListItemData = {
                    uuid: string;
                    first_name: string;
                    last_name: string;
                    full_name: string;
                    email: string | null;
                    phone: string | null;
                    city: string | null;
                    state: string | null;
                    region: string | null;
                    source: string;
                    status: string;
                    owner_name: string | null;
                    is_emergency: boolean;
                    is_spam: boolean;
                    last_contacted_at: string | null;
                    created_at: string | null;
                    deleted_at: string | null;
                };
                export type LeadReceiptData = {
                    reference: string;
                    status: string;
                };
                export type LeadSourceReportData = {
                    from: string;
                    to: string;
                    rows: Modules.LeadAppointment.Application.Dtos.LeadSourceReportRowData[];
                    average_first_contact_minutes: number | null;
                };
                export type LeadSourceReportFilterData = {
                    from: string | null;
                    to: string | null;
                };
                export type LeadSourceReportRowData = {
                    source: string;
                    source_label: string;
                    leads: number;
                    appointments: number;
                    won: number;
                    conversion_rate: number;
                    average_first_contact_minutes: number | null;
                };
                export type LogLeadActivityData = {
                    type: Modules.LeadAppointment.Domain.Enums.LeadActivityType;
                    outcome: string | null;
                    notes: string | null;
                    occurredAt: string | null;
                };
                export type MergeLeadData = {
                    duplicateUuid: string;
                };
                export type MetaLeadData = {
                    externalId: string;
                    firstName: string;
                    lastName: string;
                    email: string | null;
                    phone: string | null;
                    companyName: string | null;
                    address: string | null;
                    city: string | null;
                    state: string | null;
                    zipCode: string | null;
                    message: string | null;
                    campaignId: string | null;
                    adsetId: string | null;
                    adId: string | null;
                    formId: string | null;
                    pageId: string | null;
                    fieldData: Record<string, string>;
                };
                export type RescheduleAppointmentData = {
                    startsAt: string;
                    durationMinutes: number | null;
                    timezone: string | null;
                    reason: string;
                };
                export type SaveAvailabilityExceptionData = {
                    date: string;
                    isAvailable: boolean;
                    startTime: string | null;
                    endTime: string | null;
                    reason: string | null;
                };
                export type SaveAvailabilityRuleData = {
                    dayOfWeek: number;
                    startTime: string;
                    endTime: string;
                    isAvailable: boolean;
                };
                export type ScheduleAppointmentData = {
                    leadUuid: string;
                    kind: Modules.LeadAppointment.Domain.Enums.AppointmentKind;
                    startsAt: string | null;
                    durationMinutes: number | null;
                    timezone: string | null;
                    region: string | null;
                    notes: string | null;
                    isEmergency: boolean;
                };
                export type UpdateLeadData = {
                    firstName: string;
                    lastName: string;
                    companyName: string | null;
                    email: string | null;
                    phone: string | null;
                    address: string | null;
                    address2: string | null;
                    city: string | null;
                    state: string | null;
                    zipCode: string | null;
                    region: string | null;
                    isEmergency: boolean;
                    preferredLanguage: string;
                    damageDescription: string | null;
                    insuranceCarrier: string | null;
                    claimNumber: string | null;
                    notes: string | null;
                    ownerUuid: string | null;
                    insuranceProperty: boolean;
                    message: string | null;
                    intentToClaim: boolean | null;
                    followUpDate: string | null;
                    inspectionDate: string | null;
                    inspectionTime: string | null;
                    inspectionStatus: Modules.LeadAppointment.Domain.Enums.InspectionStatus | null;
                    additionalNote: string | null;
                    latitude: number | null;
                    longitude: number | null;
                };
                export type UpdateLeadStatusData = {
                    status: Modules.LeadAppointment.Domain.Enums.LeadStatus;
                    note: string | null;
                    inspectionDate: string | null;
                    inspectionTime: string | null;
                    inspectionStatus: Modules.LeadAppointment.Domain.Enums.InspectionStatus | null;
                };
            }
        }
        namespace Domain {
            namespace Enums {
                export type AppointmentKind =
                    'inspection' | 'estimate' | 'adjuster_meeting' | 'job_visit' | 'follow_up';
                export type AppointmentStatus =
                    | 'requested'
                    | 'scheduled'
                    | 'confirmed'
                    | 'en_route'
                    | 'on_site'
                    | 'completed'
                    | 'no_show'
                    | 'cancelled';
                export type AvailabilitySource = 'manual' | 'holiday';
                export type InspectionStatus = 'pending' | 'confirmed' | 'completed' | 'declined';
                export type LeadActivityType = 'call' | 'email' | 'sms' | 'note' | 'meeting';
                export type LeadSource =
                    'website' | 'meta' | 'phone' | 'referral' | 'manual' | 'retell_ai' | 'other';
                export type LeadStatus =
                    | 'new'
                    | 'contacted'
                    | 'qualified'
                    | 'inspection_scheduled'
                    | 'won'
                    | 'lost'
                    | 'nurture';
                export type Region = 'houston' | 'dallas' | 'florida';
            }
        }
        namespace Infrastructure {
            namespace Mail {
                export type AppointmentOfficeEvent = 'scheduled' | 'rescheduled' | 'cancelled';
            }
        }
    }
    namespace Users {
        namespace Data {
            export type ActivationUserData = {
                readonly name: string;
                readonly email: string;
            };
            export type AssignPermissionsData = {
                readonly permissions: string[];
            };
            export type AssignRolesData = {
                readonly roles: string[];
            };
            export type BulkActionResultData = {
                readonly updated: number;
                readonly skipped: Modules.Users.Data.BulkSkipData[];
            };
            export type BulkSkipData = {
                readonly uuid: string;
                readonly reason: string;
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
                readonly gender: string | null;
                readonly roles: string[];
                readonly permissions: string[];
                readonly send_invitation: boolean;
                readonly temporary_password: string | null;
            };
            export type ManagedUserData = {
                readonly uuid: string;
                readonly first_name: string;
                readonly last_name: string | null;
                readonly name: string;
                readonly username: string | null;
                readonly email: string;
                readonly email_verified: boolean;
                readonly status: Modules.Users.Enums.UserStatus;
                readonly status_label: string;
                readonly invitation_sent_at: string | null;
                readonly activated_at: string | null;
                readonly suspended_at: string | null;
                readonly must_change_password: boolean;
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
                readonly gender: string | null;
                readonly roles: string[];
                readonly is_super_admin: boolean;
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
                readonly gender: string | null;
            };
            export type UserAccessCatalogData = {
                readonly roles: Modules.Users.Data.RoleOptionData[];
                readonly permissions: string[];
            };
            export type UserAvailabilityData = {
                readonly available: boolean;
                readonly message: string;
            };
            export type UserFilterData = {
                readonly account_status: string | null;
                readonly role: string[];
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
        namespace Enums {
            export type UserStatus = 'invited' | 'active' | 'suspended';
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
            export type SelectOptionData = {
                value: string;
                label: string;
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
            export type Permission =
                | 'VIEW_ANY_USER'
                | 'VIEW_USER'
                | 'CREATE_USER'
                | 'UPDATE_USER'
                | 'DELETE_USER'
                | 'RESTORE_USER'
                | 'BULK_DELETE_USER'
                | 'BULK_RESTORE_USER'
                | 'EXPORT_USER'
                | 'ASSIGN_ROLES_USER'
                | 'ASSIGN_PERMISSIONS_USER'
                | 'VIEW_ANY_ROLE'
                | 'CREATE_ROLE'
                | 'UPDATE_ROLE'
                | 'DELETE_ROLE'
                | 'EXPORT_ROLE'
                | 'VIEW_ANY_PERMISSION'
                | 'CREATE_PERMISSION'
                | 'UPDATE_PERMISSION'
                | 'DELETE_PERMISSION'
                | 'EXPORT_PERMISSION'
                | 'VIEW_ANY_ACTIVITY_LOGS'
                | 'VIEW_ACTIVITY_LOGS'
                | 'EXPORT_ACTIVITY_LOGS'
                | 'VIEW_ANY_BACKUPS'
                | 'VIEW_BACKUPS'
                | 'DOWNLOAD_BACKUPS'
                | 'CREATE_BACKUPS'
                | 'DELETE_BACKUPS'
                | 'RESTORE_BACKUPS'
                | 'BULK_DELETE_BACKUPS'
                | 'BULK_RESTORE_BACKUPS'
                | 'EXPORT_BACKUPS'
                | 'VIEW_ANY_LEAD'
                | 'VIEW_LEAD'
                | 'CREATE_LEAD'
                | 'UPDATE_LEAD'
                | 'DELETE_LEAD'
                | 'RESTORE_LEAD'
                | 'BULK_DELETE_LEAD'
                | 'BULK_RESTORE_LEAD'
                | 'EXPORT_LEAD'
                | 'VIEW_ANY_APPOINTMENT'
                | 'VIEW_APPOINTMENT'
                | 'CREATE_APPOINTMENT'
                | 'UPDATE_APPOINTMENT'
                | 'DELETE_APPOINTMENT'
                | 'RESTORE_APPOINTMENT'
                | 'BULK_DELETE_APPOINTMENT'
                | 'BULK_RESTORE_APPOINTMENT'
                | 'EXPORT_APPOINTMENT'
                | 'VIEW_ANY_AVAILABILITY_RULE'
                | 'CREATE_AVAILABILITY_RULE'
                | 'UPDATE_AVAILABILITY_RULE'
                | 'DELETE_AVAILABILITY_RULE'
                | 'RESTORE_AVAILABILITY_RULE'
                | 'BULK_DELETE_AVAILABILITY_RULE'
                | 'BULK_RESTORE_AVAILABILITY_RULE'
                | 'VIEW_ANY_AVAILABILITY_EXCEPTION'
                | 'CREATE_AVAILABILITY_EXCEPTION'
                | 'UPDATE_AVAILABILITY_EXCEPTION'
                | 'DELETE_AVAILABILITY_EXCEPTION'
                | 'RESTORE_AVAILABILITY_EXCEPTION'
                | 'BULK_DELETE_AVAILABILITY_EXCEPTION'
                | 'BULK_RESTORE_AVAILABILITY_EXCEPTION'
                | 'EXPORT_AVAILABILITY_EXCEPTION';
            export type Role = 'SUPER_ADMIN' | 'ADMIN';
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
