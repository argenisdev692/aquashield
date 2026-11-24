/**
 * NumberField — numeric input with label, hint and error.
 * Controlled via value/onChange. `prefix` / `suffix` cover the CRM units that
 * are not money: `%` probability, `months` term, `seats` quantity.
 */
import FieldShell from './FieldShell';
import { Input } from '@/Components/ui/input';
import {
    InputGroup,
    InputGroupAddon,
    InputGroupInput,
    InputGroupText,
} from '@/Components/ui/input-group';

interface NumberFieldProps {
    label: string;
    name: string;
    value: number | null;
    onChange: (value: number | null) => void;
    onBlur?: () => void;
    error?: string;
    hint?: string;
    required?: boolean;
    placeholder?: string;
    disabled?: boolean;
    min?: number;
    max?: number;
    step?: number;
    prefix?: string;
    suffix?: string;
}

export default function NumberField({
    label,
    name,
    value,
    onChange,
    onBlur,
    error,
    hint,
    required = false,
    placeholder,
    disabled = false,
    min,
    max,
    step,
    prefix,
    suffix,
}: NumberFieldProps) {
    return (
        <FieldShell label={label} name={name} error={error} hint={hint} required={required}>
            {({ id, describedBy, invalid }) => {
                const control = {
                    id,
                    name,
                    type: 'number' as const,
                    value: value ?? 0,
                    onChange: (event: React.ChangeEvent<HTMLInputElement>) => {
                        const next = event.target.value === '' ? null : Number(event.target.value);
                        onChange(Number.isNaN(next) ? null : next);
                    },
                    onBlur,
                    placeholder,
                    disabled,
                    min,
                    max,
                    step,
                    'aria-invalid': invalid || undefined,
                    'aria-describedby': describedBy,
                };

                if (!prefix && !suffix) {
                    return <Input {...control} />;
                }

                return (
                    <InputGroup className="h-auto">
                        {prefix && (
                            <InputGroupAddon>
                                <InputGroupText>{prefix}</InputGroupText>
                            </InputGroupAddon>
                        )}

                        <InputGroupInput {...control} />

                        {suffix && (
                            <InputGroupAddon align="inline-end">
                                <InputGroupText>{suffix}</InputGroupText>
                            </InputGroupAddon>
                        )}
                    </InputGroup>
                );
            }}
        </FieldShell>
    );
}
