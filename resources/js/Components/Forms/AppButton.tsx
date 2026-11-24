/**
 * The form kit's primary button.
 * Built on shadcn/ui Button with loading state and an optional CTA shine.
 *
 * Two treatments, deliberately exclusive:
 * - default: subtle hover sheen + lift — feedback for any submit button.
 * - shine (`aq-btn-shine`): the view's single primary CTA. A looping glare.
 */
import type { ComponentProps } from 'react';
import { Button } from '@/Components/ui/button';
import { cn } from '@/lib/utils';

interface AppButtonProps {
    type?: 'button' | 'submit' | 'reset';
    loading?: boolean;
    disabled?: boolean;
    /** Opt into the looping CTA shine for the one primary action on a view. */
    shine?: boolean;
    className?: string;
    onClick?: ComponentProps<typeof Button>['onClick'];
    children: React.ReactNode;
}

export default function AppButton({
    type = 'button',
    loading = false,
    disabled = false,
    shine = false,
    className,
    onClick,
    children,
}: AppButtonProps) {
    return (
        <Button
            type={type}
            disabled={disabled || loading}
            aria-busy={loading || undefined}
            onClick={onClick}
            className={cn(
                // Brand fill comes from the theme-adaptive action tokens: navy
                // on a light ground, aqua on the night navy. Both pairs clear
                // WCAG AA (white on the navy action token; the void ink on the
                // aqua action token). Mirrors the primary button geometry (`px-3 py-2`,
                // `rounded-md`) but pins the brand CTA at the design-system action
                // height (`h-11`, 44px — the same height as the Google auth CTA and
                // the auth error-page action) instead of the shadcn default (`h-8`,
                // `px-2.5`) or a padding-derived auto height, so submit CTAs stay
                // equal-height with the secondary OAuth button they sit next to.
                'h-11 gap-2 rounded-md border border-aq-action bg-aq-action px-3 py-2 text-aq-action-ink hover:bg-aq-action-hover focus-visible:border-aq-accent focus-visible:ring-0 focus-visible:outline-1 focus-visible:outline-offset-2 focus-visible:outline-aq-accent',
                shine ? 'aq-btn-shine' : 'aq-btn-hover',
                className,
            )}
        >
            <span className="relative z-10 inline-flex items-center justify-center gap-2">
                {loading && (
                    <svg
                        className="animate-spin h-4 w-4 text-sm motion-reduce:animate-none"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <circle
                            className="opacity-25"
                            cx="12"
                            cy="12"
                            r="10"
                            stroke="currentColor"
                            strokeWidth="4"
                        ></circle>
                        <path
                            className="opacity-75"
                            fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                        ></path>
                    </svg>
                )}
                {children}
            </span>
        </Button>
    );
}
