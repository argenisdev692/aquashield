/**
 * SidebarAccountFooter — the account block that closes the rail.
 *
 * One row, two targets: the identity opens the account destination; the
 * ellipsis opens a contextual menu with the same destination and sign-out. The
 * way out is no longer a second icon sitting 4px from the profile link, so a
 * mis-aimed click cannot sign the user out.
 *
 * Expanded, the identity and the menu trigger are separate controls. Collapsed,
 * the avatar itself becomes the menu trigger — one 36px target instead of a
 * stacked avatar-plus-button. Both are click/tap/keyboard activated, never
 * hover-only.
 */
import { Link, usePage } from '@inertiajs/react';
import { LogOut, MoreHorizontal } from 'lucide-react';
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
    const SettingsIcon = SETTINGS_NAV.icon;

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
     * The expanded footer opens the menu upward over the rail. The collapsed
     * rail opens it to the right instead: a 240px panel anchored to a 72px
     * column would otherwise hang off the left edge of the screen.
     */
    const accountMenu = (side: 'top' | 'right') => (
        <DropdownMenuContent side={side} align="end" sideOffset={8} className="w-60">
            <DropdownMenuGroup>
                <DropdownMenuLabel className="flex flex-col gap-0.5 px-2 py-1.5">
                    <span className="truncate text-sm font-medium text-surface-900 dark:text-surface-0">
                        {user.name}
                    </span>
                    <span className="truncate text-xs font-normal text-surface-600 dark:text-surface-300">
                        {user.email}
                    </span>
                </DropdownMenuLabel>
            </DropdownMenuGroup>

            <DropdownMenuSeparator />

            <DropdownMenuItem render={<Link href={SETTINGS_NAV.href} />} className="gap-2">
                <SettingsIcon className="size-4" aria-hidden="true" />
                Account settings
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
    );

    if (collapsed) {
        return (
            <div className="flex justify-center">
                <DropdownMenu>
                    <DropdownMenuTrigger
                        render={
                            <button
                                type="button"
                                aria-label={`Account menu for ${user.name}`}
                                className="rounded-full transition-shadow duration-150 hover:ring-2 hover:ring-aq-accent/50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-focus motion-reduce:transition-none"
                            >
                                {avatar('size-9')}
                            </button>
                        }
                    />

                    {accountMenu('right')}
                </DropdownMenu>
            </div>
        );
    }

    return (
        <div className="flex items-center gap-1">
            {/*
             * The identity. It is a link to the account destination, so the
             * avatar is a control with a purpose instead of decoration, and
             * the name and the email are reachable by keyboard. It carries no
             * `aria-current`: the Settings row in the navigation list is the
             * one that marks the current page.
             */}
            <Link
                href={SETTINGS_NAV.href}
                className="flex min-w-0 flex-1 items-center gap-2.5 rounded-lg p-1 transition-colors duration-150 hover:bg-surface-0 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-focus motion-reduce:transition-none dark:hover:bg-surface-800"
            >
                {avatar('size-9')}

                <span className="min-w-0 flex-1">
                    <span
                        className="block truncate text-[13px] leading-tight font-medium text-surface-900 dark:text-surface-0"
                        title={user.name}
                    >
                        {user.name}
                    </span>
                    <span
                        className="mt-0.5 block truncate text-[11px] leading-tight text-surface-600 dark:text-surface-300"
                        title={user.email}
                    >
                        {user.email}
                    </span>
                </span>
            </Link>

            <DropdownMenu>
                <DropdownMenuTrigger
                    render={
                        <button
                            type="button"
                            aria-label={`Account actions for ${user.name}`}
                            className="grid size-8 shrink-0 place-items-center rounded-md text-surface-500 transition-colors duration-150 hover:bg-surface-0 hover:text-surface-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-focus motion-reduce:transition-none dark:text-surface-300 dark:hover:bg-surface-800 dark:hover:text-surface-0"
                        >
                            <MoreHorizontal className="size-4" aria-hidden="true" />
                        </button>
                    }
                />

                {accountMenu('top')}
            </DropdownMenu>
        </div>
    );
}
