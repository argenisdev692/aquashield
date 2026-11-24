/**
 * BrandLockup — the one place the app resolves the brand mark for the active
 * theme.
 *
 * `logo-mark.webp` is white artwork built for the night navy; `logo-mark-color.png`
 * is the navy/aqua cut for a light ground. Swapping the file at every call site
 * is how the header ended up white-on-white in light mode, so the swap lives
 * here. `.dark` is already on `<html>` before first paint, so this is CSS-only —
 * no state, no hydration flash.
 *
 * The name is a two-line lockup rather than one line: the legal name is 26
 * characters and truncating it in a 256px rail reads as a rendering bug. The
 * wordmark keeps its size; "Restoration LLC" is the quiet second line.
 */
import { Link } from '@inertiajs/react';
import BrandMark from '@/Components/BrandMark';
import { cn } from '@/lib/utils';

interface BrandLockupProps {
    /** Mark only, for the 72px collapsed rail. */
    compact?: boolean;
    /** Extra classes for the name block, e.g. to hide it below `sm`. */
    nameClassName?: string;
    /** Drawer-only: close the sheet after the brand link is followed. */
    onClick?: () => void;
    className?: string;
}

export default function BrandLockup({
    compact = false,
    nameClassName,
    onClick,
    className,
}: BrandLockupProps) {
    return (
        <Link
            href="/dashboard"
            onClick={onClick}
            aria-label="AquaShield Restoration LLC dashboard"
            className={cn(
                'group flex min-w-0 items-center gap-2.5 rounded-lg transition-opacity duration-150 hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-500 motion-reduce:transition-none',
                className,
            )}
        >
            <BrandMark compact={compact} />

            {!compact && (
                <span className={cn('flex min-w-0 flex-col leading-none', nameClassName)}>
                    <span className="truncate text-[0.8125rem] font-bold tracking-tight text-surface-900 dark:text-surface-0">
                        AquaShield
                    </span>
                    <span className="mt-0.5 truncate text-[0.625rem] font-medium tracking-[0.14em] text-surface-500 uppercase dark:text-surface-400">
                        Restoration LLC
                    </span>
                </span>
            )}
        </Link>
    );
}
