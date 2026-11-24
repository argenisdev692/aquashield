/**
 * Per-browser presentation preferences for the DataTable kit.
 *
 * Column visibility and row density are display choices, not data: they do
 * not change the request, the export or a saved view. They live in
 * `localStorage` — unlike filters and search, which stay in the URL because
 * they define *which* records are on screen.
 *
 * The stored shape is versioned so a future preference (ordering, pinning)
 * can be added without guessing what an old blob means.
 */
export type TableDensity = 'comfortable' | 'compact';

export interface TablePreferences {
    hidden: readonly string[];
    density: TableDensity;
}

/** The column shape the sanitiser needs; `SimpleColumn` satisfies it. */
export interface PreferenceColumn {
    id: string;
    hidable: boolean;
}

const STORAGE_PREFIX = 'aq:table:';
const STORAGE_VERSION = 1;

export const defaultTablePreferences = (): TablePreferences => ({
    hidden: [],
    density: 'comfortable',
});

/**
 * `/users` → `aq:table:users`; `/leads/archive` → `aq:table:leads:archive`.
 * A resource name, not a URL, keeps the key readable in devtools.
 */
export function tablePreferencesKey(endpoint: string): string {
    const slug = endpoint.replace(/^\/+|\/+$/g, '').replace(/\/+/g, ':');

    return `${STORAGE_PREFIX}${slug === '' ? 'root' : slug}`;
}

const isRecord = (value: unknown): value is Record<string, unknown> =>
    typeof value === 'object' && value !== null;

/**
 * Read preferences defensively. A malformed blob, a cleared storage or a
 * privacy mode that throws must never stop the table from rendering.
 */
export function readTablePreferences(key: string): TablePreferences {
    if (typeof window === 'undefined') {
        return defaultTablePreferences();
    }

    try {
        const raw = window.localStorage.getItem(key);

        if (raw === null) {
            return defaultTablePreferences();
        }

        const parsed: unknown = JSON.parse(raw);

        if (!isRecord(parsed) || parsed.version !== STORAGE_VERSION) {
            return defaultTablePreferences();
        }

        const hidden = Array.isArray(parsed.hidden)
            ? parsed.hidden.filter((id): id is string => typeof id === 'string' && id.trim() !== '')
            : [];

        return {
            hidden,
            density: parsed.density === 'compact' ? 'compact' : 'comfortable',
        };
    } catch {
        return defaultTablePreferences();
    }
}

export function writeTablePreferences(key: string, preferences: TablePreferences): void {
    if (typeof window === 'undefined') {
        return;
    }

    try {
        window.localStorage.setItem(
            key,
            JSON.stringify({
                version: STORAGE_VERSION,
                hidden: preferences.hidden,
                density: preferences.density,
            }),
        );
    } catch {
        // Storage can be unavailable or full. The table still works for the
        // session; persistence is a convenience, never a requirement.
    }
}

/** Drop unknown/locked ids and never let every column be hidden. */
export function sanitizeHiddenColumns(
    hidden: readonly string[],
    columns: readonly PreferenceColumn[],
): string[] {
    const hideable = columns.filter((column) => column.hidable);
    const hideableIds = new Set(hideable.map((column) => column.id));
    const known = new Set(columns.map((column) => column.id));
    const sanitized = [...new Set(hidden.filter((id) => known.has(id) && hideableIds.has(id)))];

    if (hideable.length > 0 && sanitized.length >= hideable.length) {
        return sanitized.filter((id) => id !== hideable[0].id);
    }

    return sanitized;
}

export function visibilityFromHidden(hidden: readonly string[]): Record<string, boolean> {
    const visibility: Record<string, boolean> = {};

    for (const id of hidden) {
        visibility[id] = false;
    }

    return visibility;
}

export function hiddenFromVisibility(visibility: Record<string, boolean>): string[] {
    return Object.entries(visibility)
        .filter(([, visible]) => visible === false)
        .map(([id]) => id);
}
