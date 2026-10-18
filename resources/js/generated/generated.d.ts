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
