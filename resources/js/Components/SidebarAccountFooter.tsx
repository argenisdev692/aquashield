/**
 * SidebarAccountFooter — the account block that closes the rail.
 *
 * One row, two things: who is signed in, and the way out. The identity on the
 * left is a link to the account destination; the icon-only control on the
 * right signs out. The rail owns the identity, so the header does not repeat
 * it — one account, one place, on every screen.
 *
 * Expanded, the sign-out button is a real button with an accessible name and a
 * 36px target, above the 24×24 minimum of WCAG 2.2 SC 2.5.8, and it is the only
 * element in the row that leaves the application — so it sits in its own
 * bordered footer zone rather than inside the navigation list.
 *
 * Collapsed, the avatar becomes the account menu trigger: one 36px control
 * opens the account menu instead of stacking a second button under the avatar.
 * It is click/tap/keyboard activated, never hover-only, so the way out stays
 * discoverable and reachable without a pointer.
 */
import { Link, usePage } from '@inertiajs/react';
import { LogOut } from 'lucide-react';
import { Avatar, AvatarFallback, AvatarImage } from '@/Components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/Components/ui/tooltip';
import { useLogout } from '@/lib/auth';
import { SETTINGS_NAV } from '@/lib/navigation';
import { cn } from '@/lib/utils';

interface SidebarAccountFooterProps {
    /** Collapsed rail mode: labels hide and the avatar opens the account menu. */
    collapsed?: boolean;
}

/**
 * The initials fallback — the first letters of the first two name parts. The
 * same rule the header used before the account moved into the rail.
 */
const initialsOf = (name: string): string => {
    const parts = name.trim().split(/\s+/).filter(Boolean);

    return (
        parts
            .slice(0, 2)
            .map((part) => part.charAt(0).toUpperCase())
            .join('') || 'AS'
    );
};

export default function SidebarAccountFooter({ collapsed = false }: SidebarAccountFooterProps) {
    const page = usePage<{ user?: App.Data.Auth.UserData | null }>();
    const user = page.props.user ?? null;
    const logout = useLogout();

    // A guest never reaches the shell — every screen that renders it sits
    // behind `auth` — so there is no identity to show and nothing to sign out
    // of. The footer simply does not exist.
    if (user === null) {
        return null;
    }

    const initials = initialsOf(user.name);

    /**
     * The avatar carries no border of its own. The `Avatar` primitive already
     * paints a soft inner ring (`after:border-surface-300` with a blend mode),
     * and a hard ring around a photograph crops the face and fights the image.
     */
    const avatar = (sizeClass: string) =>
        user.profile_photo_url ? (
            <Avatar className={cn(sizeClass, 'shrink-0')}>
                <AvatarImage
                    src={user.profile_photo_url}
                    alt={user.name}
                    className="object-cover"
                />
            </Avatar>
        ) : (
            <Avatar className={cn(sizeClass, 'shrink-0 bg-aq-accent/15')}>
                <AvatarFallback className="bg-aq-accent/15 text-xs font-semibold text-aq-action">
                    {initials}
                </AvatarFallback>
            </Avatar>
        );

    /**
     * The way out. It is an icon-only control, so it carries an accessible
     * name and a tooltip on hover AND keyboard focus — never a bare glyph and
     * never a hover-only affordance. A circle, because the avatar beside it is
     * a circle: two shapes in one 40px row have to agree.
     */
    const signOutButton = (
        <button
            type="button"
            onClick={() => {
                logout();
            }}
            aria-label="Sign out"
            className="grid size-9 shrink-0 place-items-center rounded-full border border-content-border bg-surface-0 text-surface-700 transition-colors duration-200 hover:bg-surface-100 hover:text-surface-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-500 dark:bg-surface-800 dark:text-surface-100 dark:hover:bg-surface-700 motion-reduce:transition-none"
        >
            <LogOut className="size-4" aria-hidden="true" />
        </button>
    );

    /**
     * Expanded, the label is the hover affordance, not a hidden button: the
     * icon-only control carries a tooltip on hover AND keyboard focus. The
     * collapsed rail does not stack a second control — the avatar opens the
     * account menu instead (see below).
     */
    const signOutControl = (
        <Tooltip>
            <TooltipTrigger render={signOutButton} />
            <TooltipContent side="top" sideOffset={6}>
                Sign out
            </TooltipContent>
        </Tooltip>
    );

    if (collapsed) {
        const SettingsIcon = SETTINGS_NAV.icon;

        return (
            <div className="flex justify-center">
                <DropdownMenu>
                    <DropdownMenuTrigger
                        render={
                            <button
                                type="button"
                                aria-label={`Account menu for ${user.name}`}
                                className="rounded-full transition-shadow duration-150 hover:ring-2 hover:ring-aq-accent/50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-500 motion-reduce:transition-none"
                            >
                                {avatar('size-9')}
                            </button>
                        }
                    />

                    <DropdownMenuContent side="right" align="end" sideOffset={8} className="w-60">
                        <DropdownMenuGroup>
                            <DropdownMenuLabel className="flex flex-col gap-0.5 px-2 py-1.5">
                                <span className="truncate text-sm font-medium text-surface-900 dark:text-surface-0">
                                    {user.name}
                                </span>
                                <span className="truncate text-xs font-normal text-surface-500 dark:text-surface-300">
                                    {user.email}
                                </span>
                            </DropdownMenuLabel>
                        </DropdownMenuGroup>

                        <DropdownMenuSeparator />

                        <DropdownMenuItem
                            render={<Link href={SETTINGS_NAV.href} />}
                            className="gap-2"
                        >
                            <SettingsIcon className="size-4" aria-hidden="true" />
                            {SETTINGS_NAV.label}
                        </DropdownMenuItem>

                        <DropdownMenuSeparator />

                        <DropdownMenuItem
                            onClick={() => {
                                logout();
                            }}
                            className="gap-2"
                        >
                            <LogOut className="size-4" aria-hidden="true" />
                            Sign out
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        );
    }

    return (
        <div className="flex items-center gap-2">
            {/*
             * The identity. It is a link to the account destination, so the
             * avatar is a control with a purpose instead of decoration, and
             * the name and the email are reachable by keyboard. It carries no
             * `aria-current`: the Settings row in the navigation list is the
             * one that marks the current page.
             */}
            <Link
                href={SETTINGS_NAV.href}
                className="flex min-w-0 flex-1 items-center gap-3 rounded-lg p-1 transition-colors duration-150 hover:bg-surface-0 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-500 motion-reduce:transition-none dark:hover:bg-surface-800"
            >
                {avatar('size-9')}

                <span className="min-w-0 flex-1">
                    <span className="block truncate text-sm font-medium text-surface-900 dark:text-surface-0">
                        {user.name}
                    </span>
                    <span className="block truncate text-xs text-surface-500 dark:text-surface-300">
                        {user.email}
                    </span>
                </span>
            </Link>

            {signOutControl}
        </div>
    );
}
