/**
 * DataTableRowActions — per-row action menu for DataTableKit.
 *
 * Base UI's Menu owns the portal, outside-click, Escape, focus restore and
 * roving keyboard navigation. Do not replace this with a local open/close
 * dropdown: that is how the old implementation lost keyboard access.
 */
import type { ReactNode } from 'react';
import { MoreVertical } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { cn } from '@/lib/utils';

export interface DataTableRowAction {
    label: string;
    icon?: ReactNode;
    onClick: () => void;
    destructive?: boolean;
}

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
                {actions.map((action) => (
                    <DropdownMenuItem
                        key={action.label}
                        onClick={action.onClick}
                        className={cn(
                            action.destructive &&
                                'text-destructive data-highlighted:bg-destructive/10 data-highlighted:text-destructive',
                        )}
                    >
                        {action.icon ? (
                            <span className="h-4 w-4" aria-hidden="true">
                                {action.icon}
                            </span>
                        ) : null}
                        {action.label}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
