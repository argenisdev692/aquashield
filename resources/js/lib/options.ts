/**
 * Preview policy for large static option lists.
 *
 * The permission catalogue is ~505 names. Mounting every `cmdk` item at once
 * costs a long task on open and makes each keystroke re-render the whole list.
 * An unsearched list shows a bounded preview; typing searches everything, and
 * a selected value is always kept visible so its label never disappears from
 * the trigger or the checklist.
 */

export const OPTION_PREVIEW_LIMIT = 60;

export interface PreviewableOption {
    value: string | number;
}

export interface OptionPreview<T> {
    options: readonly T[];
    /** True when the list shown is a subset and a search will reach the rest. */
    truncated: boolean;
}

export function previewOptions<T extends PreviewableOption>(
    options: readonly T[],
    selected: readonly (string | number)[],
    query: string,
    limit = OPTION_PREVIEW_LIMIT,
): OptionPreview<T> {
    if (query.trim() !== '' || options.length <= limit) {
        return { options, truncated: false };
    }

    const list = options.slice(0, limit);
    const keys = new Set(list.map((option) => String(option.value)));

    for (const option of options) {
        const key = String(option.value);

        if (!keys.has(key) && selected.some((value) => String(value) === key)) {
            list.push(option);
            keys.add(key);
        }
    }

    return { options: list, truncated: true };
}
