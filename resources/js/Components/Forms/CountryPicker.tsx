/**
 * CountryPicker — searchable country list with flags and calling codes.
 *
 * The phone field needs one of the 250-odd ITU countries, so a plain dropdown
 * is not enough: it needs to be searchable by name, ISO code and calling code
 * ("spain", "ES", "34", "+34"). It is built from the two primitives the app
 * already owns — Base UI `Popover` for the popup and cmdk `Command` for the
 * filtered, keyboard-navigable list — so it looks and behaves like the rest
 * of the workspace instead of importing a second widget system.
 *
 * The flag is the regional-indicator pair for the ISO code. macOS, iOS and
 * Android draw the flag picture; Windows has no flag glyphs and draws the two
 * letters instead, which is why the ISO code is spelled out in the list.
 *
 * Layout notes: the row reserves one fixed slot per column (flag, name, ISO,
 * calling code, check) so the columns line up down the whole list instead of
 * shifting with the length of each country name. The search stays pinned
 * above the list and the footer carries the keyboard contract.
 */
import { useState } from 'react';
import { getCountries, getCountryCallingCode, type CountryCode } from 'libphonenumber-js';
import { ChevronsUpDown } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import {
    Command,
    CommandEmpty,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/Components/ui/command';
import { Popover, PopoverContent, PopoverTrigger } from '@/Components/ui/popover';
import { countryName } from '@/lib/phone';
import { cn } from '@/lib/utils';

export interface CountryOption {
    code: CountryCode;
    name: string;
    callingCode: string;
    flag: string;
}

/**
 * The flag is the regional-indicator pair for the ISO code. Windows has no
 * flag glyphs and draws the two letters instead, which is still the country.
 */
const flagEmoji = (code: string): string =>
    String.fromCodePoint(
        ...code
            .toUpperCase()
            .split('')
            .map((character) => 0x1f1e6 + character.charCodeAt(0) - 65),
    );

export const COUNTRY_OPTIONS: readonly CountryOption[] = getCountries()
    .map((code) => ({
        code,
        name: countryName(code),
        callingCode: getCountryCallingCode(code),
        flag: flagEmoji(code),
    }))
    .sort((left, right) => left.name.localeCompare(right.name));

interface CountryPickerProps {
    value: CountryCode;
    onChange: (country: CountryCode) => void;
    disabled?: boolean;
    className?: string;
}

export default function CountryPicker({
    value,
    onChange,
    disabled = false,
    className,
}: CountryPickerProps) {
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');

    const selected = COUNTRY_OPTIONS.find((option) => option.code === value);

    return (
        <Popover
            open={open}
            onOpenChange={(next) => {
                setOpen(next);

                // A fresh open starts from the full list: keeping the previous
                // query made the picker look empty for the next country.
                if (next) {
                    setSearch('');
                }
            }}
        >
            <PopoverTrigger
                render={
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        disabled={disabled}
                        aria-label={`Country calling code${
                            selected ? `: ${selected.name} +${selected.callingCode}` : ''
                        }. Open the country search.`}
                        className={cn(
                            'h-7 shrink-0 gap-1.5 rounded-md px-1.5 text-xs text-surface-700 transition-colors duration-150 hover:bg-surface-100 focus-visible:border-aq-accent motion-reduce:transition-none dark:text-surface-100 dark:hover:bg-surface-800',
                            className,
                        )}
                    />
                }
            >
                <span aria-hidden="true" className="text-base leading-none">
                    {selected?.flag ?? '🌐'}
                </span>
                <span className="tabular-nums font-medium">+{selected?.callingCode ?? ''}</span>
                <ChevronsUpDown className="size-3 opacity-50" aria-hidden="true" />
            </PopoverTrigger>

            <PopoverContent
                align="start"
                sideOffset={6}
                className="w-[min(22rem,calc(100vw-2rem))] p-0"
            >
                <Command>
                    <CommandInput
                        aria-label="Search country by name, ISO code or calling code"
                        placeholder="Search country, ISO or +351"
                        value={search}
                        onValueChange={setSearch}
                    />

                    <CommandList className="max-h-72">
                        <CommandEmpty>
                            No country matches “{search.trim()}”.
                            <span className="mt-1 block text-xs text-surface-500 dark:text-surface-400">
                                Try a name, an ISO code or a calling code.
                            </span>
                        </CommandEmpty>

                        {COUNTRY_OPTIONS.map((option) => (
                            <CommandItem
                                key={option.code}
                                value={option.name}
                                keywords={[
                                    option.code,
                                    option.callingCode,
                                    `+${option.callingCode}`,
                                ]}
                                data-checked={option.code === value}
                                onSelect={() => {
                                    onChange(option.code);
                                    setOpen(false);
                                }}
                                className="gap-2.5 px-2 py-1.5"
                            >
                                <span
                                    aria-hidden="true"
                                    className="grid size-6 shrink-0 place-items-center rounded-md bg-surface-100 text-base leading-none dark:bg-surface-800"
                                >
                                    {option.flag}
                                </span>

                                <span className="min-w-0 flex-1 truncate">{option.name}</span>

                                <span className="shrink-0 font-mono text-[11px] tracking-wide text-surface-500 uppercase dark:text-surface-400">
                                    {option.code}
                                </span>

                                <span className="w-11 shrink-0 text-right tabular-nums text-surface-500 dark:text-surface-400">
                                    +{option.callingCode}
                                </span>
                            </CommandItem>
                        ))}
                    </CommandList>

                    <div className="-mx-1 -mb-1 flex items-center justify-between rounded-b-xl border-t border-surface-200/80 px-3 py-2 text-[11px] text-surface-500 dark:border-surface-700 dark:text-surface-400">
                        <span>{COUNTRY_OPTIONS.length} countries</span>
                        <span aria-hidden="true">↑↓ move · ↵ select · esc close</span>
                    </div>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
