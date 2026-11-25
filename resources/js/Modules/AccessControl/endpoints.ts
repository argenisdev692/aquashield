/**
 * The AccessControl URL vocabulary.
 *
 * Routes are runtime closures the frontend cannot import, so a literal is the
 * only thing it can hold. Keeping those literals here makes a backend rename a
 * one-line change instead of a hunt through pages, menus and the cache hook.
 */
export const accessControlUrls = {
    roles: '/roles',
    roleCreate: '/roles/create',
    role: (uuid: string) => `/roles/${uuid}`,
    roleEdit: (uuid: string) => `/roles/${uuid}/edit`,
    permissions: '/permissions',
    permission: (uuid: string) => `/permissions/${uuid}`,
    cacheReset: '/access-control/cache-reset',
} as const;

/**
 * Carry the current filtered index through a create/edit page so the server can
 * return the operator to the view they were working in. The server validates
 * the value; this helper only encodes it.
 */
export const withReturnTo = (url: string, returnTo: string): string => {
    const separator = url.includes('?') ? '&' : '?';

    return `${url}${separator}return_to=${encodeURIComponent(returnTo)}`;
};
