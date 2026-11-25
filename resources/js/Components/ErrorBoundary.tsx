/**
 * ErrorBoundary — the one class component in the app, and the only reason a
 * class is allowed: React has no hook for `componentDidCatch`, so a boundary
 * has to be a class.
 *
 * A Suspense boundary renders the *pending* state; this one renders the
 * *failed* state. They belong together, in this order, because a throwing
 * render has to unwind past the Suspense boundary to the nearest error
 * boundary:
 *
 *   <ErrorBoundary fallback={…}><Suspense fallback={…}>{children}</Suspense></ErrorBoundary>
 *
 * The thrown value is never rendered. A server error can carry a payload, and
 * the operator only needs to know that the panel failed and that retrying is
 * safe — the copy belongs to the caller's fallback.
 */
import { Component, type ErrorInfo, type ReactNode } from 'react';

export interface ErrorBoundaryFallback {
    error: Error;
    /** Clears the boundary and re-renders the children. */
    reset: () => void;
}

interface ErrorBoundaryProps {
    children: ReactNode;
    fallback: (state: ErrorBoundaryFallback) => ReactNode;
    /** Runs before the retry render, e.g. to reset the failed query. */
    onReset?: () => void;
}

interface ErrorBoundaryState {
    error: Error | null;
}

export default class ErrorBoundary extends Component<ErrorBoundaryProps, ErrorBoundaryState> {
    state: ErrorBoundaryState = { error: null };

    static getDerivedStateFromError(error: Error): ErrorBoundaryState {
        return { error };
    }

    componentDidCatch(error: Error, info: ErrorInfo): void {
        // The browser console is the only sink: this app has no client error
        // reporting endpoint, and a stack trace must never reach the DOM.
        console.error('Unhandled render error', error, info.componentStack);
    }

    private readonly reset = (): void => {
        this.props.onReset?.();
        this.setState({ error: null });
    };

    render(): ReactNode {
        const { error } = this.state;

        return error === null
            ? this.props.children
            : this.props.fallback({ error, reset: this.reset });
    }
}
