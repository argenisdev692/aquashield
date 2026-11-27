// @vitest-environment jsdom
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { renderHook } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import type { ReactNode } from 'react';

const { clearHistory, post } = vi.hoisted(() => ({
    clearHistory: vi.fn<() => void>(),
    post: vi.fn<(endpoint: string) => void>(),
}));

vi.mock('@inertiajs/react', () => ({
    router: { clearHistory, post },
}));

import { useLogout } from './auth';

const wrapper = ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={new QueryClient()}>{children}</QueryClientProvider>
);

describe('useLogout', () => {
    it('clears cached server state and Inertia history before signing out', () => {
        const { result } = renderHook(() => useLogout(), { wrapper });

        result.current();

        expect(clearHistory).toHaveBeenCalledTimes(1);
        expect(post).toHaveBeenCalledWith('/logout');
        expect(clearHistory.mock.invocationCallOrder[0]).toBeLessThan(
            post.mock.invocationCallOrder[0] ?? Number.POSITIVE_INFINITY,
        );
    });
});
