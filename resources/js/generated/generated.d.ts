declare namespace Shared {
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
