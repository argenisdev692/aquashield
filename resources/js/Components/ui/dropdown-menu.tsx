import * as React from 'react';
import { Menu as MenuPrimitive } from '@base-ui/react/menu';
import { cn } from 'cn';

function DropdownMenu({ ...props }: MenuPrimitive.Root.Props) {
    return <MenuPrimitive.Root data-slot="dropdown-menu" {...props} />;
}

function DropdownMenuTrigger({ ...props }: MenuPrimitive.Trigger.Props) {
    return <MenuPrimitive.Trigger data-slot="dropdown-menu-trigger" {...props} />;
}

function DropdownMenuContent({
    className,
    sideOffset = 4,
    align = 'end',
    alignOffset = 0,
    side = 'bottom',
    ...props
}: MenuPrimitive.Popup.Props &
    Pick<MenuPrimitive.Positioner.Props, 'sideOffset' | 'align' | 'alignOffset' | 'side'>) {
    return (
        <MenuPrimitive.Portal>
            <MenuPrimitive.Positioner
                align={align}
                alignOffset={alignOffset}
                side={side}
                sideOffset={sideOffset}
                className="isolate z-50"
            >
                <MenuPrimitive.Popup
                    data-slot="dropdown-menu-content"
                    className={cn(
                        'z-50 min-w-[8rem] origin-(--transform-origin) overflow-x-hidden overflow-y-auto rounded-md border border-surface-200 bg-surface-0 p-1 text-surface-700 shadow-md duration-100 dark:border-surface-700 dark:bg-surface-900 dark:text-surface-0 data-[side=bottom]:slide-in-from-top-2 data-[side=inline-end]:slide-in-from-left-2 data-[side=inline-start]:slide-in-from-right-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2 data-open:animate-in data-open:fade-in-0 data-open:zoom-in-95 data-closed:animate-out data-closed:fade-out-0 data-closed:zoom-out-95',
                        className,
                    )}
                    {...props}
                />
            </MenuPrimitive.Positioner>
        </MenuPrimitive.Portal>
    );
}

function DropdownMenuGroup({ ...props }: MenuPrimitive.Group.Props) {
    return <MenuPrimitive.Group data-slot="dropdown-menu-group" {...props} />;
}

function DropdownMenuLabel({ className, ...props }: MenuPrimitive.GroupLabel.Props) {
    return (
        <MenuPrimitive.GroupLabel
            data-slot="dropdown-menu-label"
            className={cn('px-2 py-1.5 text-sm font-medium', className)}
            {...props}
        />
    );
}

function DropdownMenuItem({ className, ...props }: MenuPrimitive.Item.Props) {
    return (
        <MenuPrimitive.Item
            data-slot="dropdown-menu-item"
            className={cn(
                'relative flex cursor-default items-center gap-2 rounded-sm px-2 py-1.5 text-sm outline-hidden select-none data-highlighted:bg-surface-100 data-highlighted:text-surface-800 dark:data-highlighted:bg-surface-800 dark:data-highlighted:text-surface-0 data-disabled:pointer-events-none data-disabled:opacity-50',
                className,
            )}
            {...props}
        />
    );
}

function DropdownMenuSeparator({ className, ...props }: MenuPrimitive.Separator.Props) {
    return (
        <MenuPrimitive.Separator
            data-slot="dropdown-menu-separator"
            className={cn('my-1 h-px bg-border', className)}
            {...props}
        />
    );
}

export {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
};
