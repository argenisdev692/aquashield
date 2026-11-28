// @vitest-environment jsdom
import { act, cleanup, renderHook } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import type { ReactNode } from 'react';
import { afterEach, describe, expect, it } from 'vitest';
import {
    LEAD_APPOINTMENT_QUERY_PREFIX,
    useLeadAppointmentInvalidator,
} from './useLeadAppointmentInvalidator';

afterEach(() => {
    cleanup();
});

describe('useLeadAppointmentInvalidator', () => {
    it('marks every lead-appointment query stale', async () => {
        const client = new QueryClient({
            defaultOptions: { queries: { retry: false } },
        });
        const key = [...LEAD_APPOINTMENT_QUERY_PREFIX, 'leads-panel', 'page=1'];

        client.setQueryData(key, { rows: [], total: 0 });

        const wrapper = ({ children }: { children: ReactNode }) => (
            <QueryClientProvider client={client}>{children}</QueryClientProvider>
        );

        const { result } = renderHook(() => useLeadAppointmentInvalidator(), { wrapper });

        await act(async () => {
            result.current();
        });

        expect(client.getQueryState(key)?.isInvalidated).toBe(true);
    });
});
