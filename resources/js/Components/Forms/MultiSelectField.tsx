/**
 * MultiSelectField — the `string[]` preset of FilterSelectField.
 *
 * Kept as a named export because role/permission pickers already speak
 * `string[]`, while FilterSelectField also covers single-value and remote
 * searches. There is one implementation, not two.
 */
import FilterSelectField from './FilterSelectField';
import type { SelectOption } from './types';

interface MultiSelectFieldProps {
    label: string;
    name: string;
    value: string[];
    onChange: (value: string[]) => void;
    options?: SelectOption[];
    fetcher?: (query: string, signal: AbortSignal) => Promise<SelectOption[]>;
    onBlur?: () => void;
    error?: string;
    hint?: string;
    required?: boolean;
    disabled?: boolean;
    placeholder?: string;
    searchPlaceholder?: string;
    emptyText?: string;
    maxVisibleChips?: number;
    clearable?: boolean;
}

export default function MultiSelectField({ value, onChange, ...props }: MultiSelectFieldProps) {
    return (
        <FilterSelectField
            {...props}
            multiple
            value={value}
            onChange={(next) => {
                if (Array.isArray(next)) {
                    onChange(next.map(String));

                    return;
                }

                onChange(next === null ? [] : [String(next)]);
            }}
        />
    );
}
