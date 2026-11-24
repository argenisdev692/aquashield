/**
 * OtpField — segmented one-time-code input.
 *
 * A group of single-digit inputs is the visual contract operators expect, but
 * paste, Backspace and arrow keys are handled explicitly so it stays usable
 * without a mouse. The submitted value is one hidden `name` input; individual
 * slots never leak extra form keys.
 */
import { useRef } from 'react';
import { Input } from '@/Components/ui/input';
import FieldShell from './FieldShell';

interface OtpFieldProps {
    label: string;
    name: string;
    value: string;
    onChange: (value: string) => void;
    onComplete?: (value: string) => void;
    onBlur?: () => void;
    error?: string;
    hint?: string;
    required?: boolean;
    disabled?: boolean;
    length?: number;
    autoFocus?: boolean;
}

const normalise = (value: string, length: number): string[] =>
    Array.from({ length }, (_, index) => value.replace(/\D/g, '')[index] ?? '');

export default function OtpField({
    label,
    name,
    value,
    onChange,
    onComplete,
    onBlur,
    error,
    hint,
    required = false,
    disabled = false,
    length = 6,
    autoFocus = false,
}: OtpFieldProps) {
    const digits = normalise(value, length);
    const inputs = useRef<Array<HTMLInputElement | null>>([]);
    const completed = useRef('');

    const commit = (next: string[]) => {
        const joined = next.join('');
        onChange(joined);

        if (joined.length === length && joined !== completed.current) {
            completed.current = joined;
            onComplete?.(joined);
        }

        if (joined.length < length) {
            completed.current = '';
        }
    };

    const setDigit = (index: number, raw: string) => {
        const digit = raw.replace(/\D/g, '').slice(-1);
        const next = [...digits];
        next[index] = digit;
        commit(next);

        if (digit !== '' && index < length - 1) {
            inputs.current[index + 1]?.focus();
        }
    };

    const handlePaste = (index: number, text: string) => {
        const pasted = text.replace(/\D/g, '').slice(0, length - index);

        if (pasted === '') {
            return;
        }

        const next = [...digits];
        for (let offset = 0; offset < pasted.length; offset += 1) {
            next[index + offset] = pasted[offset] ?? '';
        }
        commit(next);
        inputs.current[Math.min(index + pasted.length, length - 1)]?.focus();
    };

    return (
        <FieldShell
            label={label}
            name={name}
            error={error}
            hint={hint}
            required={required}
            className="items-center text-center"
        >
            {({ id, describedBy, invalid }) => (
                <div
                    role="group"
                    aria-label={label}
                    aria-describedby={describedBy}
                    onBlur={(event) => {
                        if (!event.currentTarget.contains(event.relatedTarget as Node | null)) {
                            onBlur?.();
                        }
                    }}
                >
                    <input type="hidden" name={name} value={digits.join('')} />

                    {/* One row, always. `flex-wrap` used to break the sixth slot
                        onto a second line on a 360px phone — five boxes above,
                        one orphan below — and because the group was flush left
                        the row also sat off-centre inside the card. The slots
                        now share the row and may shrink (min-w-0) instead of
                        wrapping. */}
                    <div className="flex justify-center gap-1.5 sm:gap-2">
                        {digits.map((digit, index) => (
                            <Input
                                key={index}
                                ref={(element) => {
                                    inputs.current[index] = element;
                                }}
                                id={index === 0 ? id : `${id}-${index}`}
                                type="text"
                                inputMode="numeric"
                                autoComplete={index === 0 ? 'one-time-code' : 'off'}
                                pattern="[0-9]*"
                                maxLength={1}
                                value={digit}
                                disabled={disabled}
                                autoFocus={autoFocus && index === 0}
                                aria-label={`${label} digit ${index + 1} of ${length}`}
                                aria-invalid={invalid || undefined}
                                aria-describedby={describedBy}
                                className="h-11 w-9 min-w-0 shrink px-0 text-center font-mono text-lg sm:w-10"
                                onChange={(event) => setDigit(index, event.target.value)}
                                onPaste={(event) => {
                                    event.preventDefault();
                                    handlePaste(index, event.clipboardData.getData('text'));
                                }}
                                onKeyDown={(event) => {
                                    if (event.key === 'Backspace' && digit === '' && index > 0) {
                                        event.preventDefault();
                                        const next = [...digits];
                                        next[index - 1] = '';
                                        commit(next);
                                        inputs.current[index - 1]?.focus();
                                    }

                                    if (event.key === 'ArrowLeft' && index > 0) {
                                        event.preventDefault();
                                        inputs.current[index - 1]?.focus();
                                    }

                                    if (event.key === 'ArrowRight' && index < length - 1) {
                                        event.preventDefault();
                                        inputs.current[index + 1]?.focus();
                                    }
                                }}
                            />
                        ))}
                    </div>
                </div>
            )}
        </FieldShell>
    );
}
