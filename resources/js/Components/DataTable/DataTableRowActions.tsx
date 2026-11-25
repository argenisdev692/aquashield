/**
 * DataTableRowActions — per-row action menu for DataTableKit.
 *
 * Base UI's Menu owns the portal, outside-click, Escape, focus restore and
 * roving keyboard navigation. Do not replace this with a local open/close
 * dropdown: that is how the old implementation lost keyboard access.
 */
import type { ReactNode } from 'react';
import { Link } from '@inertiajs/react';
import { MoreVertical } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { cn } from '@/lib/utils';

/**
 * A row action is either navigation (`href`, rendered as a real link so the
 * browser can open it in a new tab) or an in-page command (`onClick`, e.g. the
 * destructive confirmation).
 */
export type DataTableRowAction =
    | {
          label: string;
          icon?: ReactNode;
          destructive?: boolean;
          href: string;
          onClick?: never;
      }
    | {
          label: string;
          icon?: ReactNode;
          destructive?: boolean;
          onClick: () => void;
          href?: never;
      };

interface DataTableRowActionsProps {
    actions: DataTableRowAction[];
    /** Accessible name for the icon-only trigger. */
    label?: string;
}

export default function DataTableRowActions({
    actions,
    label = 'Open row actions',
}: DataTableRowActionsProps) {
    if (actions.length === 0) {
        return null;
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger
                render={
                    <Button variant="ghost" size="icon-sm" aria-label={label}>
                        <MoreVertical className="h-4 w-4" aria-hidden="true" />
                    </Button>
                }
            />
            <DropdownMenuContent align="end">
                {actions.map((action) => {
                    const className = cn(
                        action.destructive &&
                            'text-destructive data-highlighted:bg-destructive/10 data-highlighted:text-destructive',
                    );
                    const content = (
                        <>
                            {action.icon ? (
                                <span className="h-4 w-4" aria-hidden="true">
                                    {action.icon}
                                </span>
                            ) : null}
                            {action.label}
                        </>
                    );

                    return action.href !== undefined ? (
                        <DropdownMenuItem
                            key={action.label}
                            render={<Link href={action.href} />}
                            className={className}
                        >
                            {content}
                        </DropdownMenuItem>
                    ) : (
                        <DropdownMenuItem
                            key={action.label}
                            onClick={action.onClick}
                            className={className}
                        >
                            {content}
                        </DropdownMenuItem>
                    );
                })}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
