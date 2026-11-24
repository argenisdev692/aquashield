/**
 * SignatureField — signature capture pad with label, hint and error.
 *
 * Wraps `signature_pad` (v5, MIT) directly rather than a React wrapper: the
 * library owns the canvas and the strokes, this field owns the frame, the
 * a11y wiring and the value contract.
 *
 * The value is a PNG data URL, emitted when the pen lifts. The pad is sized to
 * its box with the device pixel ratio, so the stroke stays crisp instead of
 * being scaled up from the 300×150 default canvas, and the drawing survives a
 * resize because the strokes are restored onto the new backing store.
 *
 * The pen is the dark ink on the white `surface-0` paper in both themes: a
 * signature is a document asset, so it must not come out of the dark theme as
 * a light stroke that vanishes on a printed invoice.
 */
import { useEffect, useEffectEvent, useId, useRef } from 'react';
import FieldShell from './FieldShell';
import { Button } from '@/Components/ui/button';
import { Eraser, PenLine } from 'lucide-react';
import SignaturePad from 'signature_pad';

interface SignatureFieldProps {
    label: string;
    name: string;
    value: string | null;
    onChange: (value: string | null) => void;
    onBlur?: () => void;
    error?: string;
    hint?: string;
    required?: boolean;
    disabled?: boolean;
}

export default function SignatureField({
    label,
    name,
    value,
    onChange,
    onBlur,
    error,
    hint,
    required = false,
    disabled = false,
}: SignatureFieldProps) {
    const uid = useId();
    const id = `${name.replace(/[^a-zA-Z0-9_-]/g, '-')}-${uid}`;

    const canvasRef = useRef<HTMLCanvasElement>(null);
    const padRef = useRef<SignaturePad | null>(null);

    /** The last value this field emitted, so a parent echo is not a redraw. */
    const emittedRef = useRef<string | null>(null);

    /**
     * `useEffectEvent` keeps the canvas listener bound once while still
     * reading the latest `onChange` — the parent passes a fresh closure on
     * every render and the effect must not re-bind for it.
     */
    const emit = useEffectEvent((url: string | null): void => {
        emittedRef.current = url;
        onChange(url);
    });

    useEffect(() => {
        const canvas = canvasRef.current;

        if (canvas === null) {
            return;
        }

        const ratio = Math.max(window.devicePixelRatio || 1, 1);
        const box = canvas.getBoundingClientRect();

        canvas.width = Math.max(Math.round(box.width * ratio), 1);
        canvas.height = Math.max(Math.round(box.height * ratio), 1);

        const pad = new SignaturePad(canvas, {
            backgroundColor: 'rgba(255, 255, 255, 0)',
            // The ink token, resolved from the canvas itself: the pen follows
            // the theme instead of freezing black, which is the ink colour on
            // the paper surface.
            penColor: getComputedStyle(canvas).color,
        });

        padRef.current = pad;

        canvas.getContext('2d')?.scale(ratio, ratio);

        const handleEnd = (): void => {
            emit(pad.isEmpty() ? null : pad.toDataURL('image/png'));
        };

        pad.addEventListener('endStroke', handleEnd);

        const observer = new ResizeObserver(() => {
            const next = canvas.getBoundingClientRect();
            const width = Math.max(Math.round(next.width * ratio), 1);
            const height = Math.max(Math.round(next.height * ratio), 1);

            if (width === canvas.width && height === canvas.height) {
                return;
            }

            const strokes = pad.toData();

            // Assigning width/height resets the context, so the strokes are
            // captured first and replayed onto the scaled context.
            canvas.width = width;
            canvas.height = height;
            canvas.getContext('2d')?.scale(ratio, ratio);

            if (strokes.length > 0) {
                pad.fromData(strokes);
            }
        });

        observer.observe(canvas);

        return () => {
            observer.disconnect();
            pad.removeEventListener('endStroke', handleEnd);
            pad.off();
            padRef.current = null;
        };
    }, []);

    useEffect(() => {
        const pad = padRef.current;

        if (pad === null) {
            return;
        }

        if (disabled) {
            pad.off();

            return;
        }

        pad.on();
    }, [disabled]);

    useEffect(() => {
        const pad = padRef.current;

        if (pad === null || value === emittedRef.current) {
            return;
        }

        if (value === null) {
            pad.clear();

            return;
        }

        void pad.fromDataURL(value);
    }, [value]);

    const clear = (): void => {
        padRef.current?.clear();
        emit(null);
        canvasRef.current?.focus();
    };

    return (
        <FieldShell id={id} label={label} name={name} error={error} hint={hint} required={required}>
            <div className="flex flex-col gap-2">
                <canvas
                    ref={canvasRef}
                    id={id}
                    role="img"
                    aria-label={`${label} drawing area`}
                    tabIndex={0}
                    className="h-48 w-full cursor-crosshair touch-none rounded-lg border border-aq-shell-line bg-surface-0 text-surface-900"
                    onBlur={onBlur}
                />

                <div className="flex items-center gap-2">
                    <Button size="sm" variant="outline" onClick={clear} disabled={disabled}>
                        <Eraser className="mr-2 size-4" aria-hidden="true" />
                        Clear
                    </Button>

                    {value !== null && (
                        <Button size="sm" variant="outline" onClick={clear} disabled={disabled}>
                            <PenLine className="mr-2 size-4" aria-hidden="true" />
                            Redraw
                        </Button>
                    )}
                </div>
            </div>
        </FieldShell>
    );
}
