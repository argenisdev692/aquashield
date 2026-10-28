/**
 * Browser transport helpers shared by module composables.
 *
 * Laravel's `PreventRequestForgery` accepts the same `X-XSRF-TOKEN` header that
 * Inertia's own client sends, so plain `fetch` calls (multipart uploads and
 * JSON token flows) do not need a second CSRF mechanism.
 */
export function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match?.[1] === undefined ? '' : decodeURIComponent(match[1]);
}
